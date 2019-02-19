<?php
namespace Fgsl\Groupware\Groupbase\Acl;

use Fgsl\Groupware\Groupbase\Translation;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * this class handles the rights for a given application
 * 
 * a right is always specific to an application and not to a record
 * examples for rights are: admin, run
 * 
 * NOTE: This is a hibrite class. On the one hand it serves as the general
 *       Rights class to retreave rights for all apss for.
 *       On the other hand it also handles the Tinebase specific rights.
 * @package     Groupbase
 * @subpackage  Acl
 */
class Rights extends AbstractRights
{
    /**
     * the right to send bugreports
     * @staticvar string
     */
    const REPORT_BUGS = 'report_bugs';
    
    /**
     * the right to check for new versions
     * @staticvar string
     */
    const CHECK_VERSION = 'check_version';
    
    /**
     * the right to manage the own profile
     * @staticvar string
     */
    const MANAGE_OWN_PROFILE = 'manage_own_profile';
    
    /**
     * the right to manage the own (client) state
     * @staticvar string
     */
    const MANAGE_OWN_STATE = 'manage_own_state';

    /**
     * the right to use the installation in maintenance mode
     * @staticvar string
     */
    const MAINTENANCE = 'maintenance';

    /**
     * the right to access the replication data of all applications
     * @staticvar string
     */
    const REPLICATION = 'replication';
    
    /**
     * account type anyone
     * @staticvar string
     */
    const ACCOUNT_TYPE_ANYONE   = 'anyone';
    
    /**
     * account type user
     * @staticvar string
     */
    const ACCOUNT_TYPE_USER     = 'user';

    /**
     * account type group
     * @staticvar string
     */
    const ACCOUNT_TYPE_GROUP    = 'group';

    /**
     * account type role
     * @staticvar string
     */
    const ACCOUNT_TYPE_ROLE     = 'role';
    
    /**
     * holds the instance of the singleton
     *
     * @var Rights
     */
    private static $_instance = NULL;
    
    /**
     * the clone function
     *
     * disabled. use the singleton
     */
    private function __clone() 
    {
    }
    
    /**
     * the constructor
     *
     * disabled. use the singleton
     * temporarly the constructor also creates the needed tables on demand and fills them with some initial values
     */
    private function __construct() {
    }    
    
    /**
     * the singleton pattern
     *
     * @return Rights
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Rights;
        }
        
        return self::$_instance;
    }
    
    /**
     * get all possible application rights
     *
     * @param   string  $_application application name
     * @return  array   all application rights
     */
    public function getAllApplicationRights($_application = NULL)
    {
        $allRights = parent::getAllApplicationRights();
                
        if ( $_application === NULL || $_application === 'Tinebase' ) {
            $addRights = array(
                self::REPORT_BUGS,
                self::CHECK_VERSION,
                self::MANAGE_OWN_PROFILE,
                self::MANAGE_OWN_STATE,
                self::MAINTENANCE,
                self::REPLICATION,
            );
        } else {
            $addRights = array();
        }
        
        $allRights = array_merge($allRights, $addRights);
        
        return $allRights;
    }

    /**
     * get translated right descriptions
     * 
     * @return  array with translated descriptions for this applications rights
     */
    public static function getTranslatedRightDescriptions()
    {
        $translate = Translation::getTranslation('Groupbase');

        $rightDescriptions = array(
            self::REPORT_BUGS        => array(
                'text'                  => $translate->_('Report bugs'),
                'description'           => $translate->_('Report bugs to the software vendor directly when they occur.'),
            ),
            self::CHECK_VERSION      => array(
                'text'                  => $translate->_('Check version'),
                'description'           => $translate->_('Check for new versions of this software.'),
            ),
            self::MANAGE_OWN_PROFILE => array(
                'text'                  => $translate->_('Manage own profile'),
                'description'           => $translate->_('The right to manage the own profile (selected contact data).'),
            ),
            self::MANAGE_OWN_STATE   => array(
                'text'                  => $translate->_('Manage own client state'),
                'description'           => $translate->_('The right to manage the own client state.'),
            ),
            self::MAINTENANCE        => array(
                'text'                  => $translate->_('Maintenance'),
                'description'           => $translate->_('The right to use the installation in maintenance mode.'),
            ),
            self::REPLICATION        => array(
                'text'                  => $translate->_('Replication'),
                'description'           => $translate->_('The right to access the replication data of all applications.'),
            ),
        );
        
        $rightDescriptions = array_merge($rightDescriptions, parent::getTranslatedRightDescriptions());
        return $rightDescriptions;
    }
    
    /**
     * only return admin / run rights
     * 
     * @return  array with translated descriptions for admin and run rights
     * 
     * @todo this should be called in getTranslatedRightDescriptions / parent::getTranslatedRightDescriptions() renamed
     */
    public static function getTranslatedBasicRightDescriptions()
    {
        return parent::getTranslatedRightDescriptions();
    }
}