<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");

/**
 * Description of OpenM_ServiceDAO
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_ServiceDAO extends OpenM_ID_DAO {

    const SERVICE_TABLE_NAME = "OpenM_ID_SERVICE";
    const SERVICE_ID = "service_id";
    const SERVICE_NAME = "service_name";
    const USER_ID = "user_id";
    const SERVICE_VALID = "is_valid";
    const SERVICE_IP = "ip";

    public function get($serviceId) {
        OpenM_Log::debug($serviceId, __CLASS__, __METHOD__, __LINE__);
        return self::$db->request_fetch_HashtableString(OpenM_DB::select(self::SERVICE_TABLE_NAME, array(self::SERVICE_ID => $serviceId)));
    }

    public function remove($serviceId) {
        OpenM_Log::debug($serviceId, __CLASS__, __METHOD__, __LINE__);
        return self::$db->request(OpenM_DB::delete(self::SERVICE_TABLE_NAME, array(self::SERVICE_ID => $serviceId)));
    }

    public function create($serviceId, $serviceName, $oid, $serviceIp, $isValid = false) {
        OpenM_Log::debug("$serviceId ($serviceName)", __CLASS__, __METHOD__, __LINE__);
        self::$db->request(OpenM_DB::insert(self::SERVICE_TABLE_NAME, array(
                    self::SERVICE_ID => $serviceId,
                    self::SERVICE_IP => $serviceIp,
                    self::SERVICE_NAME => $serviceName,
                    self::USER_ID => OpenM_ID_Tool::getId($oid),
                    self::SERVICE_VALID => ($isValid) ? "1" : "0"
                )));
    }

}

?>