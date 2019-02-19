<?php
namespace Fgsl\Groupware\Addressbook\Controller;

use Fgsl\Groupware\Groupbase\Controller\Record\AbstractRecord as AbstractControllerRecord;
use Fgsl\Groupware\Groupbase\Backend\Sql;
/**
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * ListRole controller for Addressbook
 *
 * @package     Addressbook
 * @subpackage  Controller
 */
class ListRole extends AbstractControllerRecord
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
        $this->_modelName = 'Addressbook_Model_ListRole';
        $this->_backend = new Sql(array(
            'modelName'     => 'Addressbook_Model_ListRole',
            'tableName'     => 'addressbook_list_role',
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
     * @var ListRole
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return ListRole
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new ListRole();
        }
        
        return self::$_instance;
    }

    public static function destroyInstance()
    {
        self::$_instance = null;
    }
}