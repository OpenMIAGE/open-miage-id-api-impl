<?php
Import::php("OpenM-ID.api.Impl.DAO.*");
Import::php("OpenM-SSO.api.OpenM_SSO");
Import::php("OpenM-Controller.api.OpenM_RESTController");
Import::php("util.crypto.OpenM_Crypto");
Import::php("util.http.OpenM_URL");
Import::php("OpenM-Services.api.Impl.OpenM_ServiceImpl");
Import::php("util.Properties");
Import::php("OpenM-ID.api.OpenM_ID_Tool");
Import::php("OpenM-ID.api.Impl.OpenM_ID_ConnectedUserController");
Import::php("util.OpenM_Log");
Import::php("OpenM-ID.api.Impl.OpenM_ID_ReturnToController");

$abs = Import::getAbsolutePath("Auth/OpenID/Server.php");
if ($abs != null)
    Import::addInPhpClassPath(dirname(dirname(dirname($abs))));
else
    throw new ImportException("Auth/OpenID/Server");

Import::php('Auth/OpenID/Server.php');
Import::php('Auth/OpenID/FileStore.php');
Import::php('Auth/OpenID/SReg.php');
Import::php('Auth/OpenID/CryptUtil.php');

/**
 * Description of GetOpenID
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl 
 * @author Gaël Saunier
 */
class OpenM_ID_OpenID extends OpenM_ServiceImpl {

    const SPECIFIC_CONFIG_FILE_NAME = "OpenM_OpenID.config.file.path";
    const STORE_PATH = "OpenM_OpenID.store.path";
    const HASH_SECRET = "OpenM_OpenID.hash.secret";
    const HASH_ALGO = "OpenM_OpenID.hash.algo";
    const TOKEN_DEFAULT_ACTIVATION = "OpenM_OpenID.token.activation.default";
    const TOKEN_DEFAULT_ACTIVATION_TRUE = "true";

    /**
     *
     * @var type Auth_OpenID_Server
     */
    private $server;
    private $init = false;
    private $secret;
    private $hashAlgo;
    private $tokenDefaultActivation = false;
    private $returnTo;
    private $userController;

    public function __construct() {
        $this->returnTo = new OpenM_ID_ReturnToController();
        $this->userController = new OpenM_ID_ConnectedUserController();
    }

    private function init() {
        if (!$this->init) {
            $p = Properties::fromFile(self::CONFIG_FILE_NAME);
            $path = $p->get(self::SPECIFIC_CONFIG_FILE_NAME);
            if ($path == null)
                throw new OpenM_ServiceImplException(self::SPECIFIC_CONFIG_FILE_NAME . " property is missing in " . self::SPECIFIC_CONFIG_FILE_NAME);
            $p2 = Properties::fromFile(dirname(self::CONFIG_FILE_NAME) . "/" . $path);
            $path2 = $p2->get(self::STORE_PATH);
            if ($path2 == null)
                throw new OpenM_ServiceImplException(self::STORE_PATH . " property is not defined in $path");
            $secret = $p2->get(self::HASH_SECRET);
            if ($secret == null)
                throw new OpenM_ServiceImplException(self::HASH_SECRET . " property is not defined in $path");
            $this->secret = $secret;
            if ($p2->get(self::HASH_ALGO) == null)
                throw new OpenM_ServiceImplException(self::HASH_ALGO . " property is not defined in $path");
            $this->hashAlgo = $p2->get(self::HASH_ALGO);
            if (!OpenM_Crypto::isAlgoValid($this->hashAlgo))
                throw new OpenM_ServiceImplException(self::HASH_ALGO . " property is not a valid crypto algo in $path");
            $server = new Auth_OpenID_Server(new Auth_OpenID_FileStore($p2->get(self::STORE_PATH)), OpenM_URL::getDirURL());
            $this->server = $server;
            $this->init = true;
            $this->tokenDefaultActivation = ($p2->get(self::TOKEN_DEFAULT_ACTIVATION) == self::TOKEN_DEFAULT_ACTIVATION_TRUE);

            if ($p->get(self::LOG_MODE_PROPERTY) == self::LOG_MODE_ACTIVATED)
                OpenM_Log::init($p->get(self::LOG_PATH_PROPERTY), $p->get(self::LOG_LEVEL_PROPERTY), $p->get(self::LOG_FILE_NAME), $p->get(self::LOG_LINE_MAX_SIZE));
        }
    }

    public function handle() {
        $this->init();
        $request = $this->server->decodeRequest();

        if (!$request) {
            OpenM_RESTDefaultServer::handle(array("OpenM_ID"));
            exit(0);
        }

        OpenM_Log::debug("request=" . $_SERVER["REQUEST_URI"], __CLASS__, __METHOD__, __LINE__);
        OpenM_Log::debug("waited parameters: mode=" . $request->mode . " identity=" . $request->identity . " immediate=" . $request->immediate . " claimed_id=" . $request->claimed_id, __CLASS__, __METHOD__, __LINE__);
        if ($request->mode == 'checkid_setup' && $request->identity && !$request->immediate && (self::isOIDValid($request->claimed_id) || $this->isOIDValid($request->identity))) {
            $oid = (String::isString($request->claimed_id) && $request->claimed_id !== "") ? $request->claimed_id : $request->identity;
            OpenM_Log::debug("Identity check", __CLASS__, __METHOD__, __LINE__);
            $user = $this->userController->get();
            if ($user == null)
                $this->returnError();
            if (!$user->get(OpenM_UserDAO::USER_IS_VALID))
                $this->returnError();

            $userId = OpenM_ID_Tool::getId($oid);

            if ($user->get(OpenM_UserDAO::USER_ID) != $userId)
                $this->returnError();

            OpenM_Log::debug("create token for $userId", __CLASS__, __METHOD__, __LINE__);
            $token = OpenM_Crypto::hash($this->hashAlgo, OpenM_URL::encode($this->secret . Auth_OpenID_CryptUtil::randomString(200) . $oid . $this->secret));
            $token .= "_" . microtime(true);

            $tokenDAO = new OpenM_TokenDAO();
            $tokenDAO->create($token, $oid);

            OpenM_Log::debug("extract sreg request", __CLASS__, __METHOD__, __LINE__);
            $sreg_request = Auth_OpenID_SRegRequest::fromOpenIDRequest($request);

            $sreg_data = array();

            $trust_root = $request->trust_root;
            $dns = OpenM_URL::getURLWithoutHttpAndWww($trust_root);
            if (substr($dns, -1) == "/")
                $dns = substr($dns, 0, -1);

            $dataDAO = new OpenM_Data_Access_RightsDAO();

            OpenM_Log::debug("add token in sreg", __CLASS__, __METHOD__, __LINE__);
            if ($this->tokenDefaultActivation) {
                OpenM_Log::debug("token added by default activated", __CLASS__, __METHOD__, __LINE__);
                $sreg_data[OpenM_ID::TOKEN_PARAMETER] = $token;
            } else {
                OpenM_Log::debug("search allowed data access for token in DAO", __CLASS__, __METHOD__, __LINE__);
                $data = $dataDAO->getAllowed($userId, $dns, OpenM_Data_Access_RightsDAO::TOKEN_DATA_ID);
                if ($data->get(OpenM_Data_Access_RightsDAO::TOKEN_DATA_ID) != null) {
                    OpenM_Log::debug("allowed data access for token found in DAO", __CLASS__, __METHOD__, __LINE__);
                    $sreg_data[OpenM_ID::TOKEN_PARAMETER] = $token;
                } else {
                    //todo => ask for rights
                }
            }

            OpenM_Log::debug("check if email is required", __CLASS__, __METHOD__, __LINE__);
            if ($sreg_request->contains(OpenM_ID::EMAIL_PARAMETER)) {
                OpenM_Log::debug("search allowed data access in DAO", __CLASS__, __METHOD__, __LINE__);
                $data = $dataDAO->getAllowed($userId, $dns, OpenM_Data_Access_RightsDAO::EMAIL_DATA_ID);
                if ($data->get(OpenM_Data_Access_RightsDAO::EMAIL_DATA_ID) != null) {
                    OpenM_Log::debug("allowed data access found in DAO", __CLASS__, __METHOD__, __LINE__);
                    $sreg_data[OpenM_ID::EMAIL_PARAMETER] = $user->get(OpenM_UserDAO::USER_MAIL);
                } else {
                    //todo => ask for rights
                }
            }

            $sreg_response = Auth_OpenID_SRegResponse::extractResponse($sreg_request, $sreg_data);
            $response = $request->answer(true, null, $request->identity);
            $sreg_response->toMessage($response->fields);
        } else {
            OpenM_Log::debug("Default request treatment", __CLASS__, __METHOD__, __LINE__);
            try {
                $response = $this->server->handleRequest($request);
            } catch (Exception $exc) {
                OpenM_Log::error($exc->getTraceAsString(), __CLASS__, __METHOD__, __LINE__);
                $this->returnError();
            }
        }

        $webResponse = $this->server->encodeResponse($response);
        $this->finalyzeRequest($webResponse);
    }

    private function isOIDValid($oid) {
        if (!OpenM_URL::isValid($oid))
            return false;
        $api = OpenM_URL::getURLWithoutHttpAndWww();
        $oidBase = OpenM_URL::getURLWithoutHttpAndWww($oid);
        if ($api != $oidBase)
            return false;

        $oidParameter = substr($oid, strlen(OpenM_URL::getURLwithoutParameters($oid)));
        if (!RegExp::ereg("^\?" . OpenM_ID::URI_API . "=[a-f0-9]+$", $oidParameter))
            return false;
        return true;
    }

    private function returnError() {
        $error = new Auth_OpenID_WebResponse(AUTH_OPENID_HTTP_ERROR);
        $this->finalyzeRequest($error);
    }

    private function finalyzeRequest($webResponse) {
        if ($webResponse->code != AUTH_OPENID_HTTP_OK) {
            header(sprintf("HTTP/1.1 %d ", $webResponse->code), true, $webResponse->code);
        }

        foreach ($webResponse->headers as $k => $v) {
            header("$k: $v");
        }

        header("Connection: close");
        print $webResponse->body;
        exit(0);
    }

    public function uriDisplay() {
        $id = $_GET[OpenM_ID::URI_API];
        ?>
        <html>
            <head>    
                <link rel="openid.server openid2.provider" href="<?php echo OpenM_URL::getURLwithoutParameters(); ?>" />
                <link rel="openid.delegate openid2.local_id" href="<?php echo OpenM_URL::getURLwithoutParameters() . "?" . OpenM_ID::URI_API . "=" . $id; ?>" />
            </head>
            <body>
                <h1>OpenM-ID server v<?php echo OpenM_IDImpl::VERSION; ?> : OpenID</h1>
                Hello <?php echo $id; ?>
                <br>
            </body>
        </html>
        <?php
        die();
    }

    public function getOpenID() {
        if (!$this->returnTo->isReturnTo())
            die("No return_to parameter found");

        $return_to = $this->returnTo->getReturnTo();

        if (RegExp::ereg(OpenM_ID::OID_PARAMETER . "=", $return_to)) {
            exit(0);
        }

        if (RegExp::ereg(str_replace("www.", "", OpenM_ID::OID_PARAMETER . "="), $return_to)) {
            OpenM_Header::error(400);
        }

        $user = $this->userController->get();

        if (!isset($_GET[OpenM_ID::NO_REDIRECT_TO_LOGIN_PARAMETER]) && $user == null)
            OpenM_Header::redirect(OpenM_URL::getDirURL() . "?" . OpenM_ID::LOGIN_API . "&return_to=" . OpenM_URL::encode(substr(OpenM_URL::getURL(), strlen(OpenM_URL::getHost()))));

        if (RegExp::ereg("\?", $return_to))
            $return_to .= "&" . OpenM_ID::OID_PARAMETER . "=";
        else
            $return_to .= "?" . OpenM_ID::OID_PARAMETER . "=";

        $return_to .= (isset($_GET[OpenM_ID::NO_REDIRECT_TO_LOGIN_PARAMETER]) && $user == null) ? OpenM_SSO::RETURN_ERROR_MESSAGE_NOT_CONNECTED_VALUE : OpenM_URL::encode(OpenM_URL::getURLwithoutParameters() . "?" . OpenM_ID::URI_API . "=" . $user->get(OpenM_UserDAO::USER_ID));

        OpenM_Header::redirect($return_to);
    }

    public function logout($redirectToLogin = true) {
        $this->userController->remove();
        if ($this->returnTo->isReturnTo())
            $this->returnTo->returnTo();
        else if ($redirectToLogin) {
            OpenM_Header::redirect(OpenM_URL::getDirURL() . "?" . OpenM_ID::LOGIN_API);
        }
    }

}
?>