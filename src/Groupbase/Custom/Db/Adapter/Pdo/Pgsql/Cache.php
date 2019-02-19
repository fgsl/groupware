<?php
/**
 * Tine 2.0
 *
 * @package     Custom
 * @subpackage  Model
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2015 Serpro (http://www.serpro.gov.br)
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 */

/**
 * class Custom_Db_Adapter
 *
 * @package     Custom
 * @subpackage  Db
 */
class Custom_Db_Adapter_Pdo_Pgsql_Cache
{
    public function getCache()
    {
        Tinebase_Core::setupCache();
        $cache = Tinebase_Core::getCache();
        return $cache;
    }
}