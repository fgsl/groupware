<?php
/**
 * Tine 2.0
 *
 * @package     Addressbook
 * @subpackage  Controller
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * Industry controller for Addressbook
 *
 * @package     Addressbook
 * @subpackage  Controller
 */
class Addressbook_Controller_Industry extends Tinebase_Controller_Record_Abstract
{
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct()
    {
        $this->_doContainerACLChecks = false;
        $this->_applicationName = 'Addressbook';
        $this->_modelName = 'Addressbook_Model_Industry';
        $this->_backend = new Tinebase_Backend_Sql(array(
            'modelName'     => 'Addressbook_Model_Industry',
            'tableName'     => 'addressbook_industry',
            'modlogActive'  => true
        ));
        $this->_purgeRecords = FALSE;
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }
    
    /**
     * holds the instance of the singleton
     *
     * @var Addressbook_Controller_Industry
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return Addressbook_Controller_Industry
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Addressbook_Controller_Industry();
        }
        
        return self::$_instance;
    }
}
