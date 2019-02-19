<?php
namespace Fgsl\Groupware\Addressbook\Controller;

use Fgsl\Groupware\Groupbase\Controller\Record\AbstractRecord as AbstractControllerRecord;
use Fgsl\Groupware\Addressbook\Backend\Certificate as BackendCertificate;
use Fgsl\Groupware\Addressbook\Config;
/**
*
* @package     Addressbook
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/

/**
 * certicate controller for Addressbook
 *
 * @package     Addressbook
 * @subpackage  Controller
 */
class Certificate extends AbstractControllerRecord {
    
    /**
     * application name (is needed in checkRight())
     *
     * @var string
     */
    protected $_applicationName = 'Addressbook';
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'Addressbook_Model_Certificate';
    
    /**
     * check for container ACLs
     *
     * @var boolean
     */
    protected $_doContainerACLChecks = FALSE;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() {
        $this->_applicationName = 'Addressbook';
        $this->_modelName = 'Fgsl\Groupware\Addressbook\Model\Certificate';
        $this->_backend = new BackendCertificate();
        $this->_updateMultipleValidateEachRecord = TRUE;
        $this->_duplicateCheckFields = Config::getInstance()->get(Config::CERTIFICATE_DUP_FIELDS, array(
            array('hash', 'auth_key_identifier'),
        ));
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
     * @var Certificate
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return Certificate
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Certificate();
        }
        
        return self::$_instance;
    }
}