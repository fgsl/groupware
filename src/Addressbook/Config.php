<?php
namespace Fgsl\Groupware\Addressbook;
use Fgsl\Groupware\Groupbase\Config\AbstractConfig;

/**
 *
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Addressbook config class
 * 
 * @package     Addressbook
 * @subpackage  Config
 */
class Config extends AbstractConfig
{
    /**
     * fields for contact record duplicate check
     * 
     * @var string
     */
    const CONTACT_DUP_FIELDS = 'contactDupFields';
    
        /**
     * fields for list record duplicate check
     * 
     * @var string
     */
    const LIST_DUP_FIELDS = 'contactDupFields';
    
     /**
     * fields for certificate record duplicate check
     * 
     * @var string
     */
    const CERTIFICATE_DUP_FIELDS = 'certificateDupFields';
    
    /**
     * fields for contact salutations
     * 
     * @var string
     */
    const CONTACT_SALUTATION = 'contactSalutation';
    
    /**
     * config for address parsing rules file
     * 
     * @var string
     */
    const CONTACT_ADDRESS_PARSE_RULES_FILE = 'parseRulesFile';
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Definition::$_properties
     */
    protected static $_properties = array(
        self::CONTACT_DUP_FIELDS => array(
                                   //_('Contact duplicate check fields')
            'label'                 => 'Contact duplicate check fields',
                                   //_('These fields are checked when a new contact is created. If a record with the same data in the fields is found, a duplicate exception is thrown.')
            'description'           => 'These fields are checked when a new contact is created. If a record with the same data in the fields is found, a duplicate exception is thrown.',
            'type'                  => 'array',
            'contents'              => 'array',
            'clientRegistryInclude' => TRUE,
        // @todo make default work
            'default'               => array(               // array of alternatives
                array('n_given', 'n_family', 'org_name'),   // all fields must match
                array('email'),                             // single field that needs to match
            ),
        ),
      self::LIST_DUP_FIELDS => array(
                                   //_('Contact duplicate check fields')
            'label'                 => 'List duplicate check fields',
                                   //_('These fields are checked when a new contact is created. If a record with the same data in the fields is found, a duplicate exception is thrown.')
            'description'           => 'These fields are checked when a new list is created. If a record with the same data in the fields is found, a duplicate exception is thrown.',
            'type'                  => 'array',
            'contents'              => 'array',
            'clientRegistryInclude' => TRUE,
        // @todo make default work
            'default'               => array(               // array of alternatives
                array('name')   // all fields must match
            ),
        ),
        self::CERTIFICATE_DUP_FIELDS => array(
                                   //_('Contact duplicate check fields')
            'label'                 => 'Certificate duplicate check fields',
                                   //_('These fields are checked when a new contact is created. If a record with the same data in the fields is found, a duplicate exception is thrown.')
            'description'           => 'These fields are checked when a new list is created. If a record with the same data in the fields is found, a duplicate exception is thrown.',
            'type'                  => 'array',
            'contents'              => 'array',
            'clientRegistryInclude' => TRUE,
        // @todo make default work
            'default'               => array(               // array of alternatives
                array('hash', 'auth_key_identifier')   // all fields must match
            ),
        ),
        self::CONTACT_SALUTATION => array(
        //_('Contact salutations available')
            'label'                 => 'Task priorities available',
        //_('Possible contact salutations. Please note that additional values might impact other Addressbook systems on export or syncronisation.')
            'description'           => 'Possible contact salutations. Please note that additional values might impact other Addressbook systems on export or syncronisation.',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'Addressbook_Model_Salutation'),
            'clientRegistryInclude' => TRUE,
        //            'default'               => 'MR'
        ),
        self::CONTACT_ADDRESS_PARSE_RULES_FILE => array(
        //_('Parsing rules for addresses')
            'label'                 => 'Parsing rules for addresses',
        //_('Path to a XML file with address parsing rules.')
            'description'           => 'Path to a XML file with address parsing rules.',
            'type'                  => 'string',
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => FALSE,
        ),
    );
    
    protected $_appName = 'Addressbook';
    
    /**
     * holds the instance of the singleton
     *
     * @var Config
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */    
    private function __construct() {}
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */    
    private function __clone() {}
    
    /**
     * Returns instance of Tinebase_Config
     *
     * @return Config
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        
        return self::$_instance;
    }
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::getProperties()
     */
    public static function getProperties()
    {
        return self::$_properties;
    }
}
