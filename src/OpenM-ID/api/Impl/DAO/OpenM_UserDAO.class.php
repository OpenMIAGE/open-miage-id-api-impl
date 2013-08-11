<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");
Import::php("OpenM-Mail.api.OpenM_MailTool");

/**
 * Description of OpenM_UserDAO
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_UserDAO extends OpenM_ID_DAO {
    
    const USER_TABLE_NAME = "OpenM_ID_USER";
    const USER_ID = "user_id";
    const USER_MAIL = "mail";
    const USER_PASSWORD = "password";
    const USER_IS_VALID = "is_valid";
    
    public function get($userId) {
        OpenM_Log::debug($userId, __CLASS__, __METHOD__, __LINE__);
        if(OpenM_ID_Tool::isTokenValid($userId))
            $return = self::$db->request_fetch_HashtableString(OpenM_DB::select(self::USER_TABLE_NAME, array(self::USER_ID => $userId)));
        else if(OpenM_MailTool::isEMailValid($userId))
            $return = self::$db->request_fetch_HashtableString(OpenM_DB::select(self::USER_TABLE_NAME, array(self::USER_MAIL => $userId)));
        
        if($return==null)
            return;
        
        return $return->put(self::USER_IS_VALID, ($return->get(self::USER_IS_VALID)=="1")?true:false);
    }

    public function remove($userId) {
        OpenM_Log::debug($userId, __CLASS__, __METHOD__, __LINE__);
        if(OpenM_ID_Tool::isTokenValid($userId))
            self::$db->request(OpenM_DB::delete(self::USER_TABLE_NAME, array(self::USER_ID => $userId)));
        else if(OpenM_MailTool::isEMailValid($userId))
            self::$db->request(OpenM_DB::delete(self::USER_TABLE_NAME, array(self::USER_MAIL => $userId)));
        else
            return false;
        return true;
    }

    public function create($userId, $userMail, $userPassword, $isValid = false) {
        OpenM_Log::debug("$userId, $userMail, $isValid", __CLASS__, __METHOD__, __LINE__);
        self::$db->request(OpenM_DB::insert(self::USER_TABLE_NAME, array(
                    self::USER_ID => $userId,
                    self::USER_MAIL => $userMail,
                    self::USER_PASSWORD => $userPassword,
                    self::USER_IS_VALID => ($isValid) ? "1" : "0"
                )));
        return true;
    }
}
?>