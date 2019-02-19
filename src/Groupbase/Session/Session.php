<?php
namespace Fgsl\Groupware\Groupbase\Session;

use Fgsl\Groupware\Groupbase\Session\Validator\AccountStatus;
use Zend\Session\SessionManager;
use Zend\Session\Validator\HttpUserAgent;
use Fgsl\Groupware\Groupbase\Session\Validator\MaintenanceMode;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * class for Session and Session Namespaces in Core
 * 
 * @package     Groupbase
 * @subpackage  Session
 */
class Session extends AbstractSession
{
    /**
     * Session namespace for Groupbase Core data
     */
    const NAMESPACE_NAME = 'Core_Session_Namespace';
    
    /**
     * Register Validator for account status
     */
    public static function registerValidatorAccountStatus()
    {
        $manager = new SessionManager();
        $manager->getValidatorChain()
        ->attach('session.validate', [new AccountStatus(), 'isValid']);
    }
    
    /**
     * Register Validator for Http User Agent
     */
    public static function registerValidatorHttpUserAgent()
    {
        $manager = new SessionManager();
        $manager->getValidatorChain()
        ->attach('session.validate', [new HttpUserAgent(), 'isValid']);
    }
    
    /**
     * Register Validator for Ip Address
     */
    public static function registerValidatorIpAddress()
    {
        $manager = new SessionManager();
        $manager->getValidatorChain()
        ->attach('session.validate', [new (), 'isValid']);
    }

    public static function registerValidatorMaintenanceMode()
    {
        $manager = new SessionManager();
        $manager->getValidatorChain()
        ->attach('session.validate', [new MaintenanceMode(), 'isValid']);
    }
}
