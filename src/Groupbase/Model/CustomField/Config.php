<?php
namespace Fgsl\Groupware\Groupbase\Model\CustomField;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\InputFilter\Input;
use Zend\Validator\NotEmpty;
use Fgsl\Groupware\Groupbase\Config\Struct;
use Fgsl\Groupware\Groupbase\Exception\Record\DefinitionFailure;
use Fgsl\Groupware\Groupbase\Model\InputFilter\RemoveWhitespace;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class Config
 * 
 * @package     Groupbase
 * @subpackage  Record
 *
 * @property    string      id
 * @property    string      application_id
 * @property    string      model
 * @property    string      name
 * @property    string      definition
 * @property    string      account_grants
 * @property    string      value
 * @property    string      label
 * @property    boolean     is_system
 */
class Config extends AbstractRecord
{
    const DEF_FIELD = 'fieldDef';
    const DEF_HOOK = 'hook';
    const CONTROLLER_HOOKS = 'controllerHooks';

    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';

    /**
     * record validators
     *
     * @var array
     */
    protected $_validators = array(
        'id'                    => array('allowEmpty' => true ),
        'application_id'        => array('presence' => 'required', 'allowEmpty' => false, 'Alnum' ),
        'model'                 => array('presence' => 'required', 'allowEmpty' => false ),
        'name'                  => array('presence' => 'required', 'allowEmpty' => false ),
        'definition'            => array('presence' => 'required', 'allowEmpty' => false ),
        'is_system'             => [Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => 0],
        'account_grants'        => array('allowEmpty' => true ),
        'value'                 => array('allowEmpty' => true ),
        // Set label from definition if extended resolving is enabled
        'label'                 => array('allowEmpty' => true ),
        // fake properties for modlog purpose only
        'created_by'            => array('allowEmpty' => true ),
        'creation_time'         => array('allowEmpty' => true ),
        'last_modified_by'      => array('allowEmpty' => true ),
        'last_modified_time'    => array('allowEmpty' => true ),
    );

    /**
     * list of zend inputfilter
     *
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_filters = [
        'is_system'          => [NotEmpty::class => true],
    ];
    
    /**
     * checks if customfield value is empty
     * 
     * @param string $_value
     * @return boolean
     */
    public function valueIsEmpty($_value)
    {
        if ($this->definition['type'] === 'bool') {
            $result = empty($_value);
        } else {
            $result = ($_value === '' || $_value === NULL);
        }
        
        return $result;
    }
    
    /**
     * @see tine20/Tinebase/Record/Abstract.php::setFromArray()
     * @param array $_data
     * @return array
     */
    public function setFromArray(array &$_data)
    {
        if (isset($_data['definition'])) {
            if (is_string($_data['definition'])) {
                $_data['definition'] = json_decode($_data['definition']);
            }

            if (is_array($_data['definition'])) {
                $_data['definition'] = new Struct($_data['definition']);
            }
        }
        
        return parent::setFromArray($_data);
    }

    /**
     * Default constructor
     * Constructs an object and sets its record related properties.
     *
     * @param mixed $_data
     * @param bool $bypassFilters sets {@see this->bypassFilters}
     * @param mixed $convertDates sets {@see $this->convertDates} and optionaly {@see $this->$dateConversionFormat}
     * @throws DefinitionFailure
     */
    public function __construct($_data = NULL, $_bypassFilters = false, $_convertDates = true)
    {
        $this->_filters = array('name' => new RemoveWhitespace());

        parent::__construct($_data, $_bypassFilters, $_convertDates);
    }

    /**
     * returns true if this record should be replicated
     *
     * @return boolean
     */
    public function isReplicable()
    {
        return true;
    }
}