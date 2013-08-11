<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");

/**
 * 
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_Site_AllowedDAO extends OpenM_ID_DAO {

    const OpenM_ID_SITE_ALLOWED_TABLE_NAME = "OpenM_ID_SITE_ALLOWED";
    const SITE_ID = "site_id";
    const ADDED_BY = "added_by";
    const DATE_ADDED = "date_added";

    public function get($siteId) {
        return self::$db->request_fetch_HashtableString(OpenM_DB::select(self::OpenM_ID_SITE_ALLOWED_TABLE_NAME, array(
                            self::SITE_ID => $siteId
                        )));
    }

    public function remove($siteId) {
        return self::$db->request_fetch_HashtableString(OpenM_DB::delete(self::OpenM_ID_SITE_ALLOWED_TABLE_NAME, array(
                            self::SITE_ID => $siteId
                        )));
    }

    public function create($siteId, $userId) {
        $time = time();
        self::$db->request(OpenM_DB::insert(self::OpenM_ID_SITE_ALLOWED_TABLE_NAME, array(
                    self::SITE_ID => $siteId,
                    self::ADDED_BY => $userId,
                    self::DATE_ADDED => $time
                )));

        $return = new HashtableString();
        return $return->put(self::SITE_ID, $siteId)
                        ->put(self::ADDED_BY, $userId)
                        ->put(self::DATE_ADDED, $time);
    }

}

?>