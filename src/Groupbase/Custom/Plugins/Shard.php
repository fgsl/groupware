<?php

/**
 * Tine 2.0
 *
 * @package     Custom
 * @subpackage  Plugins_Shard
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2015 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Emerson Faria Nobre <emerson-faria.nobre@serpro.gov.br>
 *
 */

/**
 * Custom Shard class
 *
 * @package    Custom
 * @subpackage Plugins_Shard
 */
class Custom_Plugins_Shard
{
    /**
     * Get shard dbConfig
     *
     * @param  Tinebase_Config_Struct $dbConfig
     * @param  string $dbName
     * @return Tinebase_Config_Struct
     */
    public static function getShardDbConfig($dbConfig, $dbName)
    {
        $config = Tinebase_Core::getConfig();

        if (isset($dbConfig) && isset($dbName) && isset($config->shard) && isset($config->shard->$dbName) && Tinebase_Config_Manager::isMultidomain()) {
            return Tinebase_Shard_Manager::getInstance()->getConnectionConfig($dbName);
        }

        return $dbConfig;
    }
}
