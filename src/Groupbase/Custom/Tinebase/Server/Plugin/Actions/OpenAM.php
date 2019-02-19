<?php

/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Server
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009-2013 Serpro (http://www.serpro.gov.br)
 * @copyright   Copyright (c) 2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Marcelo Teixeira <marcelo.teixeira@serpro.gov.br>
 */

/**
 * OpenAM server plugin actions
 *
 * @package     Tinebase
 * @subpackage  Auth
 */
class Custom_Tinebase_Server_Plugin_Actions_OpenAM implements Tinebase_Server_Plugin_Actions_Interface
{

    public static function init() {

    }

    /**
     * Action for Http Server when OpenAM is enabled
     */
    public function executeAction() {
        if (Custom_Tinebase_Auth_OpenAM::hasSessionToken()) {
            $_REQUEST['method'] = 'Tinebase.openAM';
        }
    }
}
