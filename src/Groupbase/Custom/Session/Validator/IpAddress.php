<?php

/**
 * Tine 2.0
 *
 * @package     Custom
 * @subpackage  Session
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @copyright   Copyright (c) 2014 Serpro (http://www.serpro.gov.br)
 * @author      Marcelo Teixeira <marcelo.teixeira@serpro.gov.br>
 *
 */

/**
 * Custom Session Validator IpAddress class
 *
 * @package    Custom
 * @subpackage Session
 */
class Custom_Session_Validator_IpAddress extends Zend_Session_Validator_IpAddress
{

    /**
     * Setup() - this method will get the remote ip address and store it in the session
     * as 'valid data'
     *
     * @return void
     */
    public function setup()
    {
        $config = Tinebase_Config::getInstance()->get(Tinebase_Config::SESSIONIPVALIDATION, TRUE);
        $ip = null;
        if(!is_bool($config) && $config->source == 'header' ){
            $headers = getallheaders();
            if(isset($config->header) && isset($headers[$config->header])){
                $ip = $headers[$config->header];
            }
            $ip = (empty($ip) ? $_SERVER['REMOTE_ADDR'] : $ip);
        } else {
            $ip = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        }
        $this->setValidData($ip);
    }

    /**
     * Validate() - this method will determine if the remote ip address matches the
     * remote ip address we stored when we initialized this variable.
     *
     * @return bool
     */
    public function validate()
    {
        $config = Tinebase_Config::getInstance()->get(Tinebase_Config::SESSIONIPVALIDATION, TRUE);
        $currentIpAddress = null;
        if(!is_bool($config) && $config->source == 'header' ){
            $headers = getallheaders();
            if(isset($config->header) && isset($headers[$config->header])){
                $currentIpAddress = $headers[$config->header];
            }
            $currentIpAddress = (empty($currentIpAddress) ? $_SERVER['REMOTE_ADDR'] : $currentIpAddress);
        } else {
            $currentIpAddress = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        }

        return $currentIpAddress === $this->getValidData();
    }

}
