<?php

/**
 * Tine 2.0
 *
 * @package     Custom
 * @subpackage  AccessLog
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @copyright   Copyright (c) 2014 Serpro (http://www.serpro.gov.br)
 * @author      Marcelo Teixeira <marcelo.teixeira@serpro.gov.br>
 *
 */

/**
 * Custom strategy class for AccessLog
 *
 * @package     Custom
 * @subpackage  AccessLog
 */
class Custom_AccessLog_Strategy_Default
{

    /**
     * get client remote ip address according to config
     * It can be a header attribute or $_SERVER['REMOTE_ADDR']
     *
     * @return string
     */
    public static function getRemoteIpAddress()
    {
        $config = Tinebase_Config::getInstance()->get(Tinebase_Config::SESSIONIPVALIDATION, TRUE);
        $ip = null;
        if(!is_bool($config) && $config->source == 'header' ){
            $headers = getallheaders();
            if(isset($config->header) && isset($headers[$config->header])){
                $ip = substr($headers[$config->header], 0, 40);
            }
            $ip = (empty($ip) ? $_SERVER['REMOTE_ADDR'] : $ip);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

}