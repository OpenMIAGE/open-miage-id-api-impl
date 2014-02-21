<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_UserDAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_TokenDAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_SiteDAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_Data_Access_RightsDAO");
Import::php("OpenM-SSO.api.OpenM_SSO");
Import::php("OpenM-Controller.api.OpenM_RESTController");
Import::php("util.crypto.OpenM_Crypto");
Import::php("util.http.OpenM_URL");
Import::php("OpenM-Services.api.Impl.OpenM_ServiceImpl");
Import::php("util.Properties");
Import::php("OpenM-ID.api.OpenM_ID_Tool");
Import::php("OpenM-ID.api.Impl.OpenM_ID_ConnectedUserController");
Import::php("util.OpenM_Log");

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
class OpenM_OpenID extends OpenM_ServiceImpl {

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
    private static $server;
    private static $init = false;
    private static $secret;
    private static $hashAlgo;
    private static $tokenDefaultActivation = false;

    private static function init() {
        if (!self::$init) {
            $p = Properties::fromFile(self::CONFIG_FILE_NAME);
            $path = $p->get(self::SPECIFIC_CONFIG_FILE_NAME);
            if ($path == null)
                throw new OpenM_ServiceImplException(self::SPECIFIC_CONFIG_FILE_NAME . " property is missing in " . self::SPECIFIC_CONFIG_FILE_NAME);
            $p2 = Properties::fromFile($path);
            $path2 = $p2->get(self::STORE_PATH);
            if ($path2 == null)
                throw new OpenM_ServiceImplException(self::STORE_PATH . " property is not defined in $path");
            $secret = $p2->get(self::HASH_SECRET);
            if ($secret == null)
                throw new OpenM_ServiceImplException(self::HASH_SECRET . " property is not defined in $path");
            self::$secret = $secret;
            if ($p2->get(self::HASH_ALGO) == null)
                throw new OpenM_ServiceImplException(self::HASH_ALGO . " property is not defined in $path");
            self::$hashAlgo = $p2->get(self::HASH_ALGO);
            if (!OpenM_Crypto::isAlgoValid(self::$hashAlgo))
                throw new OpenM_ServiceImplException(self::HASH_ALGO . " property is not a valid crypto algo in $path");
            $server = new Auth_OpenID_Server(new Auth_OpenID_FileStore($p2->get(self::STORE_PATH)), OpenM_URL::getDirURL());
            self::$server = $server;
            self::$init = true;
            self::$tokenDefaultActivation = ($p2->get(self::TOKEN_DEFAULT_ACTIVATION) == self::TOKEN_DEFAULT_ACTIVATION_TRUE);

            if ($p->get(self::LOG_MODE_PROPERTY) == self::LOG_MODE_ACTIVATED)
                OpenM_Log::init($p->get(self::LOG_PATH_PROPERTY), $p->get(self::LOG_LEVEL_PROPERTY), $p->get(self::LOG_FILE_NAME), $p->get(self::LOG_LINE_MAX_SIZE));
        }
    }

    public static function handle() {
        self::init();

        $request = self::$server->decodeRequest();

        if (!$request) {
            OpenM_RESTDefaultServer::handle(array("OpenM_ID"));
            exit(0);
        }

        OpenM_Log::debug("request=" . $_SERVER["REQUEST_URI"], __CLASS__, __METHOD__, __LINE__);
        OpenM_Log::debug("waited parameters: mode=" . $request->mode . " identity=" . $request->identity . " immediate=" . $request->immediate . " claimed_id=" . $request->claimed_id, __CLASS__, __METHOD__, __LINE__);
        if ($request->mode == 'checkid_setup' && $request->identity && !$request->immediate && (self::isOIDValid($request->claimed_id) || self::isOIDValid($request->identity))) {
            $oid = (String::isString($request->claimed_id) && $request->claimed_id !== "") ? $request->claimed_id : $request->identity;
            OpenM_Log::debug("Identity check", __CLASS__, __METHOD__, __LINE__);
            $user = OpenM_ID_ConnectedUserController::get();
            if ($user == null)
                self::returnError();
            if (!$user->get(OpenM_UserDAO::USER_IS_VALID))
                self::returnError();

            $userId = OpenM_ID_Tool::getId($oid);

            if ($user->get(OpenM_UserDAO::USER_ID) != $userId)
                self::returnError();

            OpenM_Log::debug("create token for $userId", __CLASS__, __METHOD__, __LINE__);
            $token = OpenM_Crypto::hash(self::$hashAlgo, OpenM_URL::encode(self::$secret . Auth_OpenID_CryptUtil::randomString(200) . $oid . self::$secret));
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
            if (self::$tokenDefaultActivation) {
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
                $response = self::$server->handleRequest($request);
            } catch (Exception $exc) {
                OpenM_Log::error($exc->getTraceAsString(), __CLASS__, __METHOD__, __LINE__);
                self::returnError();
            }
        }

        $webResponse = self::$server->encodeResponse($response);
        self::finalyzeRequest($webResponse);
    }

    private static function isOIDValid($oid) {
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

    private static function returnError() {
        $error = new Auth_OpenID_WebResponse(AUTH_OPENID_HTTP_ERROR);
        self::finalyzeRequest($error);
    }

    private static function finalyzeRequest($webResponse) {
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

}

?>
