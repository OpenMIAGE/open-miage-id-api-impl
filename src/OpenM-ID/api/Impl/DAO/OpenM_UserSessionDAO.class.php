<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");

/**
 * Description of OpenM_UserSessionDAO
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_UserSessionDAO extends OpenM_ID_DAO {

    const USER_SESSION_TABLE_NAME = "OpenM_ID_USER_SESSION";
    const USER_ID = "user_id";
    const SESSION_ID = "session_id";
    const SESSION_BEGIN_TIME = "begin_time";
    const USER_IP_HASH = "ip_hash";

    public function get($sessionId, $userIp_hash = null) {
        OpenM_Log::debug($sessionId . " ($userIp_hash)", __CLASS__, __METHOD__, __LINE__);
        if ($userIp_hash == null)
            $return = self::$db->request_fetch_HashtableString(OpenM_DB::select(self::USER_SESSION_TABLE_NAME, array(
                        self::SESSION_ID => $sessionId
                    )));
        else
            $return = self::$db->request_fetch_HashtableString(OpenM_DB::select(self::USER_SESSION_TABLE_NAME, array(
                        self::SESSION_ID => $sessionId,
                        self::USER_IP_HASH => $userIp_hash
                    )));
        if ($return == null)
            return;
        $begin_time = new Date($return->get(self::SESSION_BEGIN_TIME)->toInt());
        return $return->put(self::SESSION_BEGIN_TIME, $begin_time);
    }

    public function remove($sessionId) {
        OpenM_Log::debug($sessionId, __CLASS__, __METHOD__, __LINE__);
        self::$db->request(OpenM_DB::delete(self::USER_SESSION_TABLE_NAME, array(self::SESSION_ID => $sessionId)));
        return true;
    }

    public function removeUser($userId, $ip_hash) {
        OpenM_Log::debug("$userId ($ip_hash)", __CLASS__, __METHOD__, __LINE__);
        self::$db->request(OpenM_DB::delete(self::USER_SESSION_TABLE_NAME, array(
                    self::USER_ID => $userId,
                    self::USER_IP_HASH => $ip_hash
                )));
        return true;
    }

    public function removeBefore(Date $date) {
        OpenM_Log::debug($date->toString(), __CLASS__, __METHOD__, __LINE__);
        self::$db->request(OpenM_DB::delete(self::USER_SESSION_TABLE_NAME) . " WHERE " . self::SESSION_BEGIN_TIME . "<" . $date->getTime());
        return true;
    }

    public function create($sessionId, $userId, $userIp_hash) {
        OpenM_Log::debug("$sessionId, $userId, $userIp_hash", __CLASS__, __METHOD__, __LINE__);
        self::$db->request(OpenM_DB::insert(self::USER_SESSION_TABLE_NAME, array(
                    self::SESSION_ID => $sessionId,
                    self::USER_ID => $userId,
                    self::SESSION_BEGIN_TIME => time(),
                    self::USER_IP_HASH => $userIp_hash
                )));
        return true;
    }

}

?>