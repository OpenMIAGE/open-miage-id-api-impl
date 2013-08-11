<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_SiteDAO");
Import::php("OpenM-ID.api.Impl.DAO.OpenM_Site_AllowedDAO");

/**
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_Data_Access_RightsDAO extends OpenM_ID_DAO {

    const DATA_ACCESS_RIGHTS_TABLE_NAME = "OpenM_ID_DATA_ACCESS_RIGHTS";
    const USER_ID = "user_id";
    const SITE_ID = "site_id";
    const DATA_ID = "data_id";
    const DATE_VALIDATION = "date_validation";
    const EMAIL_DATA_ID = 1;
    const TOKEN_DATA_ID = 0;

    public function get($userId, $siteId) {
        return self::$db->request_fetch_HashtableString(OpenM_DB::select(self::DATA_ACCESS_RIGHTS_TABLE_NAME, array(
                            self::USER_ID => $userId,
                            self::SITE_ID => $siteId
                        )));
    }

    public function getFromDSN($userId, $dns) {
        return self::$db->request_HashtableString(OpenM_DB::select(self::DATA_ACCESS_RIGHTS_TABLE_NAME, array(
                            self::USER_ID => $userId
                        ))
                        . " AND "
                        . self::SITE_ID
                        . "=("
                        . OpenM_DB::select(OpenM_SiteDAO::OpenM_ID_SITE_TABLE_NAME, array(
                            OpenM_SiteDAO::DNS => $dns
                                ), array(
                            OpenM_SiteDAO::SITE_ID
                        ))
                        . ")"
                        , self::DATA_ID);
    }

    public function getAllowed($userId, $dns, $dataId) {
        return self::$db->request_HashtableString(OpenM_DB::select(self::DATA_ACCESS_RIGHTS_TABLE_NAME, array(
                            self::USER_ID => $userId,
                            self::DATA_ID => $dataId
                                ), array(
                            self::USER_ID,
                            self::DATA_ID
                        ))
                        . " AND "
                        . self::SITE_ID
                        . "=("
                        . OpenM_DB::select(OpenM_SiteDAO::OpenM_ID_SITE_TABLE_NAME, array(
                            OpenM_SiteDAO::DNS => $dns
                                ), array(
                            OpenM_SiteDAO::SITE_ID
                        ))
                        . ")"
                        . " UNION "
                        . "SELECT " . OpenM_Site_AllowedDAO::SITE_ID . ", $dataId as " . self::DATA_ID
                        . " FROM " . OpenM_Site_AllowedDAO::OpenM_ID_SITE_ALLOWED_TABLE_NAME
                        . " WHERE " . OpenM_Site_AllowedDAO::SITE_ID . " IN ("
                        . OpenM_DB::select(OpenM_SiteDAO::OpenM_ID_SITE_TABLE_NAME, array(
                            OpenM_SiteDAO::DNS => $dns
                                ), array(
                            OpenM_SiteDAO::SITE_ID
                        ))
                        . ")"
                        , self::DATA_ID);
    }

    public function remove($userId, $siteId, $dataId = null) {
        $array = array(
            self::USER_ID => $userId,
            self::SITE_ID => $siteId
        );

        if ($dataId != null)
            $array[self::DATA_ID] = $dataId;

        self::$db->request(OpenM_DB::delete(self::DATA_ACCESS_RIGHTS_TABLE_NAME, $array));
    }

    public function create($userId, $siteId, $dataId) {
        $time = time();
        self::$db->request(OpenM_DB::insert(self::DATA_ACCESS_RIGHTS_TABLE_NAME, array(
                    self::USER_ID => $userId,
                    self::SITE_ID => $siteId,
                    self::DATA_ID => $dataId,
                    self::DATE_VALIDATION => $time
                )));
        $return = new HashtableString();
        return $return->put(self::USER_ID, $userId)
                        ->put(self::DATA_ID, $dataId)
                        ->put(self::SITE_ID, $siteId)
                        ->put(self::DATE_VALIDATION, $time);
    }

}

?>