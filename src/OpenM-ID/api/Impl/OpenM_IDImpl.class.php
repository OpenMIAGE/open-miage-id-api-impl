<?php

Import::php("OpenM-ID.api.OpenM_ID");
Import::php("OpenM-Services.api.Impl.OpenM_ServiceImpl");
Import::php("OpenM-ID.api.OpenM_ID_Tool");
Import::php("util.Properties");
Import::php("util.http.OpenM_Server");
Import::php("util.time.Date");
Import::php("util.http.OpenM_URL");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_TokenDAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_ServiceDAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_ServiceClientDAO");
Import::php("util.OpenM_Log");
if(!Import::php("Auth/OpenID/CryptUtil.php"))
    throw new ImportException("Auth/OpenID/CryptUtil");

/**
 * Description of OpenM_IDImpl
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl 
 * @author Gaël Saunier
 */
class OpenM_IDImpl extends OpenM_ServiceImpl implements OpenM_ID {

    const HASH_SECRET = "OpenM_ID.hash.secret";
    const HASH_ALGO = "OpenM_ID.hash.algo";
    const IP_HASH_ALGO = "OpenM_ID.ip.hash.algo";
    const SPECIFIC_CONFIG_FILE_NAME = "OpenM_ID.config.file.path";
    const TOKEN_SESSION_VALIDITY = "OpenM_ID.token.session.validity";
    const TOKEN_API_SESSION_VALIDITY = "OpenM_ID.token.api.session.validity";

    private static $OpenM_ID;
    private $properties;

    public function __construct() {
        self::$OpenM_ID = $this;
        $this->init();
    }

    public static function getInstance() {
        if (self::$OpenM_ID == null)
            self::$OpenM_ID = new OpenM_IDImpl();
        return self::$OpenM_ID;
    }

    private function init() {
        if ($this->properties != null)
            return;

        $p = Properties::fromFile(self::CONFIG_FILE_NAME);
        $path = $p->get(self::SPECIFIC_CONFIG_FILE_NAME);
        if ($path == null)
            throw new OpenM_ServiceImplException(self::SPECIFIC_CONFIG_FILE_NAME . " property is not defined in " . self::CONFIG_FILE_NAME);
        $this->properties = Properties::fromFile(dirname(self::CONFIG_FILE_NAME)."/".$path);
        if ($this->properties->get(self::HASH_SECRET) == null)
            throw new OpenM_ServiceImplException(self::HASH_SECRET . " property is not defined in $path");
        if ($this->properties->get(self::TOKEN_API_SESSION_VALIDITY) == null)
            throw new OpenM_ServiceImplException(self::TOKEN_API_SESSION_VALIDITY . " property is not defined in $path");
        if ($this->properties->get(self::TOKEN_API_SESSION_VALIDITY) == null)
            throw new OpenM_ServiceImplException(self::TOKEN_SESSION_VALIDITY . " property is not defined in $path");
        if ($this->properties->get(self::HASH_ALGO) == null)
            throw new OpenM_ServiceImplException(self::HASH_ALGO . " property is not defined in $path");
        if ($this->properties->get(self::IP_HASH_ALGO) == null)
            throw new OpenM_ServiceImplException(self::IP_HASH_ALGO . " property is not defined in $path");
        if (!OpenM_Crypto::isAlgoValid($this->properties->get(self::HASH_ALGO)))
            throw new OpenM_ServiceImplException(self::HASH_ALGO . " property is not a valid crypto algo in $path");
    }

    public function addServiceClient($serviceId, $oid, $token, $clientIp) {
        $check = $this->checkService($serviceId);
        if ($check != null)
            return $check;

        $check = $this->checkOIDwithToken($oid, $token);
        if ($check != null)
            return $check;
        
        if(!OpenM_ID_Tool::isTokenValid($clientIp))
            return $this->error("clienIp not in a valid format");

        $serviceClientDAO = new OpenM_ServiceClientDAO();
        $serviceClientDAO->create($serviceId, $clientIp, $oid);
        return $this->ok();
    }

    public function checkUserRights($serviceId, $oid, $token, $clientIp = null) {
        $check = $this->checkService($serviceId);
        if ($check != null)
            return $check;

        $check = $this->checkOIDwithToken($oid, $token, $clientIp);
        if ($check != null)
            return $check;

        if ($clientIp != null) {
            $serviceClientDao = new OpenM_ServiceClientDAO();
            $service = $serviceClientDao->get($serviceId, $clientIp);
            if($service==null)
                return $this->error ("Client not declared");
        }

        $tokenId = OpenM_ID_Tool::getTokenApi(OpenM_Crypto::hash($this->properties->get(self::HASH_ALGO), OpenM_URL::encode($this->properties->get(self::HASH_SECRET) . $oid . Auth_OpenID_CryptUtil::randomString(200) . $this->properties->get(self::HASH_SECRET))));
        $tokenId .= "_" . microtime(true);
        $tokenDAO = new OpenM_TokenDAO();
        $tokenDAO->create($tokenId, $oid, $serviceId);
        return $this->ok()->put(self::RETURN_TOKEN_PARAMETER, $tokenId);
    }

    public function installService($serviceName, $oid, $token) {
        $check = $this->checkOIDwithToken($oid, $token);
        if ($check != null)
            return $check;

        $serviceId = OpenM_Crypto::hash($this->properties->get(self::HASH_ALGO), $this->properties->get(self::HASH_SECRET) . $serviceName . Auth_OpenID_CryptUtil::randomString(200) . $this->properties->get(self::HASH_SECRET));
        $serviceId .= "_" . microtime(true);
        $serviceDAO = new OpenM_ServiceDAO();
        $serviceDAO->create($serviceId, $serviceName, $oid, OpenM_ID_Tool::getClientIp($this->properties->get(self::IP_HASH_ALGO)));
        return $this->ok()->put(self::RETURN_SERVICE_ID_PARAMETER, $serviceId);
    }

    public function removeServiceClient($serviceId, $clientIp) {
        $check = $this->checkService($serviceId);
        if ($check != null)
            return $check;
        
        $serviceClientDOA = new OpenM_ServiceClientDAO();
        $serviceClientDOA->remove($serviceId, $clientIp);
        return $this->ok();
    }

    private function checkService($serviceId) {
        if (!String::isString($serviceId))
            return $this->error("serviceId must be a string");
        if (!OpenM_ID_Tool::isTokenValid($serviceId))
            return $this->error("serviceId must be in a valid format");

        $serviceDAO = new OpenM_ServiceDAO();
        $service = $serviceDAO->get($serviceId);

        if ($service == null)
            return $this->error("serviceId not found");
        if ($service->get(OpenM_ServiceDAO::SERVICE_IP) . "" != OpenM_ID_Tool::getClientIp($this->properties->get(self::IP_HASH_ALGO)))
            return $this->error("it's not your serviceId");
        if ($service->get(OpenM_ServiceDAO::SERVICE_VALID) . "" != "1")
            return $this->error("serviceId not validated");

        return null;
    }

    private function checkOIDwithToken($oid, $token, $clientIp = null) {

        if (!String::isString($token))
            return $this->error("token must be a string");
        if (!OpenM_ID_Tool::isTokenValid($token))
            return $this->error("token must be in a valid format");

        $isTokenApi = OpenM_ID_Tool::isTokenApi($token);

        if (!String::isStringOrNull($clientIp))
            return $this->error("clientIp must be a string");
        if (!is_null($clientIp) && !$isTokenApi)
            return $this->error("clientIp must be set only in case of API request access to API");
        if (!is_null($clientIp) && !OpenM_ID_Tool::isTokenValid($clientIp))
            return $this->error("clientIp must be in a valid format");

        if (!String::isString($oid))
            return $this->error("oid must be a string");
        if (!OpenM_URL::isValid($oid))
            return $this->error("oid must be a valid URL");
        if (OpenM_ID_Tool::getId($oid) == "")
            return $this->error("oid not valid");

        $tokenDAO = new OpenM_TokenDAO();

        $t = $tokenDAO->get($token);
        if ($t == null)
            return $this->error("token not found");

        if ($t->get(OpenM_TokenDAO::USER_ID) != OpenM_ID_Tool::getId($oid))
            return $this->error("oid not valid");

        $validityApi = new Delay($this->properties->get(self::TOKEN_API_SESSION_VALIDITY));
        $validity = new Delay($this->properties->get(self::TOKEN_SESSION_VALIDITY));

        $now = new Date();
        $outOfDate = $now->less($validity);
        $outOfDateApi = $now->less($validityApi);

        $tokenDAO->remove($token, $outOfDate, $outOfDateApi);

        $begin_time = new Date($t->get(OpenM_TokenDAO::BEGIN_TIME)->toInt());
        if ($begin_time->compareTo(($isTokenApi) ? $outOfDateApi : $outOfDate) < 0)
            return $this->error("token timeOut");

        return null;
    }

}

?>