<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");

/**
 * Description of OpenM_ServiceClient
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_ServiceClientDAO extends OpenM_ID_DAO {

    const CLIENT_TABLE_NAME = "OpenM_ID_SERVICE_CLIENT";
    const SERVICE_ID = "service_id";
    const CLIENT_IP_HASH = "client_ip_hash";
    const USER_ID = "user_id";
    const TIME = "time";

    public function get($serviceId, $clientIp) {
        OpenM_Log::debug("$serviceId, $clientIp", __CLASS__, __METHOD__, __LINE__);
        return self::$db->request_fetch_HashtableString(OpenM_DB::select(self::CLIENT_TABLE_NAME, array(
                            self::SERVICE_ID => $serviceId,
                            self::CLIENT_IP_HASH => $clientIp
                        )));
    }

    public function remove($serviceId, $clientIp) {
        OpenM_Log::debug("$serviceId, $clientIp", __CLASS__, __METHOD__, __LINE__);
        return self::$db->request(OpenM_DB::delete(self::CLIENT_TABLE_NAME, array(
                            self::SERVICE_ID => $serviceId,
                            self::CLIENT_IP_HASH => $clientIp
                        )));
    }

    public function create($serviceId, $clientIp, $oid) {
        $time = time();
        OpenM_Log::debug("$serviceId, $clientIp, $oid", __CLASS__, __METHOD__, __LINE__);
        self::$db->request(OpenM_DB::insert(self::CLIENT_TABLE_NAME, array(
                    self::SERVICE_ID => $serviceId,
                    self::CLIENT_IP_HASH => $clientIp,
                    self::USER_ID => OpenM_ID_Tool::getId($oid),
                    self::TIME => $time
                )));

        $return = new HashtableString();
        return $return->put(self::SERVICE_ID, $serviceId)->put(self::CLIENT_IP_HASH, $clientIp)
                        ->put(self::USER_ID, OpenM_ID_Tool::getId($oid))->put(self::TIME, $time);
    }

}

?>