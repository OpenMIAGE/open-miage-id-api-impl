<?php

Import::php("OpenM-Services.api.Impl.DAO.OpenM_DAO");
Import::php("util.OpenM_Log");
Import::php("OpenM-ID.api.OpenM_ID_Tool");

/**
 * Description of OpenM_DAO
 *
 * @package OpenM 
 * @subpackage OpenM\OpenM-ID\api\Impl\DAO 
 * @author Gaël Saunier
 */
class OpenM_ID_DAO extends OpenM_DAO {

    const DAO_CONFIG_FILE_NAME = "OpenM_ID.DAO.config.file.path";
    const PREFIX = "OpenM_ID.DAO.prefix";
    
    public function getDaoConfigFileName() {
        return self::DAO_CONFIG_FILE_NAME;
    }

    public function getPrefixPropertyName() {
        return self::PREFIX;
    }
}

?>
