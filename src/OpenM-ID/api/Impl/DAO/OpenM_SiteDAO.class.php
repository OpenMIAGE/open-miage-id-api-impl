<?php

Import::php("OpenM-ID.api.Impl.DAO.OpenM_ID_DAO");

/**
 * 
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_SiteDAO extends OpenM_ID_DAO {

    const OpenM_ID_SITE_TABLE_NAME = "OpenM_ID_SITE";
    const SITE_ID = "site_id";
    const DNS = "dns";

    public function get($siteId) {
        return self::$db->request_fetch_HashtableString(OpenM_DB::select(self::OpenM_ID_SITE_TABLE_NAME, array(
                            self::SITE_ID => $siteId
                        )));
    }

    public function getFromDNS($dns) {
        return self::$db->request_fetch_HashtableString(OpenM_DB::select(self::OpenM_ID_SITE_TABLE_NAME, array(
                            self::DNS => $dns
                        )));
    }

    public function remove($siteId) {
        return self::$db->request_fetch_HashtableString(OpenM_DB::delete(self::OpenM_ID_SITE_TABLE_NAME, array(
                            self::SITE_ID => $siteId
                        )));
    }

    public function create($dns) {
        self::$db->request(OpenM_DB::insert(self::OpenM_ID_SITE_TABLE_NAME, array(
                    self::DNS => $dns
                )));

        return $this->getFromDNS($dns);
    }

}

?>