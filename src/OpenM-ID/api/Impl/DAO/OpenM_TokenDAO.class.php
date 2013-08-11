<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");
Import::php("util.time.Date");

/**
 * Description of OpenM_TokenDAO
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_TokenDAO extends OpenM_ID_DAO {

    const DB_TOKEN_TABLE = "OpenM_ID_TOKEN";
    const DB_TOKEN_API_TABLE = "OpenM_ID_API_TOKEN";
    const TOKEN_ID = "token_id";
    const BEGIN_TIME = "begin_time";
    const SERVICE_ID = "service_id";
    const USER_ID = "user_id";

    public function get($token) {
        OpenM_Log::debug($token, __CLASS__, __METHOD__, __LINE__);
        return self::$db->request_fetch_HashtableString(
                        OpenM_DB::select((( OpenM_ID_Tool::isTokenApi($token)) ? self::DB_TOKEN_API_TABLE : self::DB_TOKEN_TABLE), array(self::TOKEN_ID => $token)));
    }

    public function remove($token, Date $outOfDate, Date $outOfDateAPI) {
        OpenM_Log::debug($token . " (" . $outOfDate->getTime() . ")", __CLASS__, __METHOD__, __LINE__);
        if (!OpenM_ID_Tool::isTokenApi($token))
            self::$db->request(OpenM_DB::delete(self::DB_TOKEN_TABLE, array(self::TOKEN_ID => $token)));

        self::$db->request(OpenM_DB::delete(self::DB_TOKEN_TABLE) . " WHERE " . self::BEGIN_TIME . "<" . $outOfDate->getTime());
        self::$db->request(OpenM_DB::delete(self::DB_TOKEN_API_TABLE) . " WHERE " . self::BEGIN_TIME . "<" . $outOfDateAPI->getTime());        
    }

    public function create($token, $oid, $serviceId = null) {
        OpenM_Log::debug("$token, $oid, $serviceId", __CLASS__, __METHOD__, __LINE__);
        if (OpenM_ID_Tool::isTokenApi($token)) {
            self::$db->request(OpenM_DB::insert(self::DB_TOKEN_API_TABLE, array(
                        self::TOKEN_ID => $token,
                        self::BEGIN_TIME => time(),
                        self::SERVICE_ID => $serviceId,
                        self::USER_ID => OpenM_ID_Tool::getId($oid)
                    )));
        } else {
            self::$db->request(OpenM_DB::insert(self::DB_TOKEN_TABLE, array(
                        self::TOKEN_ID => $token,
                        self::BEGIN_TIME => time(),
                        self::USER_ID => OpenM_ID_Tool::getId($oid)
                    )));
        }
    }

}

?>