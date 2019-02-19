<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Acl\Rights;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\Helper;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/

/**
 * class Tinebase_Model_Preference
 * 
 * @package     Tinebase
 * @subpackage  Record
 */
class Preference extends AbstractRecord
{
    /**
     * normal user/group preference
     *
     */
    const TYPE_USER = 'user';
    
    /**
     * default preference for anyone who has no specific preference
     *
     */
    const TYPE_DEFAULT = 'default';

    /**
     * admin default preference
     *
     */
    const TYPE_ADMIN = 'admin';
    
    /**
     * admin forced preference (can not be changed by users)
     *
     */
    const TYPE_FORCED = 'forced';

    /**
     * default preference value
     *
     */
    const DEFAULT_VALUE = '_default_';
    
    /**
     * identifier field name
     *
     * @var string
     */
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
        'id'                => array('allowEmpty' => TRUE),
        'account_id'        => array('presence' => 'required', 'allowEmpty' => TRUE, 'default' => '0'),
        'account_type'      => array('presence' => 'required', 'allowEmpty' => FALSE, array('InArray', array(
            Rights::ACCOUNT_TYPE_ANYONE,
            Rights::ACCOUNT_TYPE_USER,
            Rights::ACCOUNT_TYPE_GROUP
        ))),
        'application_id'    => array('presence' => 'required', 'allowEmpty' => FALSE, 'Alnum'),
        'name'              => array('presence' => 'required', 'allowEmpty' => FALSE, 'Alnum'),
        'value'             => array('presence' => 'required', 'allowEmpty' => TRUE),
        'type'              => array('presence' => 'required', 'allowEmpty' => FALSE, array('InArray', array(
            self::TYPE_USER,        // user defined
            self::TYPE_DEFAULT,     // code default
            self::TYPE_ADMIN,       // admin default
            self::TYPE_FORCED,      // admin forced
        ))),
    // xml field with select options for this preference => only available in TYPE_DEFAULT prefs
        'options'            => array('allowEmpty' => TRUE),
    // don't allow to set this preference in admin mode
        'personal_only'      => array(Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => 0),
    // don't allow user to change value
        'locked'      => array(Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => 0),
        // multiselection preference
        //'multiselect'        =>  array(Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => false),
        'uiconfig'        =>  array(Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => array()),
        'recordConfig'    =>  array(Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => array()),
    );

    /**
     * TODO remove when converted to ModelConfig
     * TODO generalize / support other models
     */
    public function runConvertToRecord()
    {
        // convert value property if necessary
        if (Helper::is_json($this->value)) {
            $value = Helper::jsonDecode($this->value);
            switch ($value['modelName']) {
                case 'Tinebase_Model_Container':
                    $containers = array();
                    foreach ($value['ids'] as $containerId) {
                        try {
                            $container = Container::getInstance()->getContainerById($containerId);
                            // TODO should be converted to array by json frontend
                            $containers[] = $container->toArray();
                        } catch (\Exception $e) {
                            // not found / no access / ...
                        }
                    }
                    $this->value = $containers;
                    break;
                default:
                    throw new InvalidArgument('model not supported');

            }
        } else {
            parent::runConvertToRecord();
        }
    }

    /**
     * TODO remove when converted to ModelConfig
     * TODO generalize / support other models
     */
    public function runConvertToData()
    {
        // convert value property if necessary
        if (is_array($this->value) && is_array($this->recordConfig) && isset($this->recordConfig['modelName'])) {
            $value = array(
                'modelName' => $this->recordConfig['modelName'],
                'ids' => array(),
            );
            foreach ($this->value as $record) {
                if (isset($record['id'])) {
                    $value['ids'][] = $record['id'];
                }
            }
            $this->value = json_encode($value);
        } else {
            parent::runConvertToData();
        }
    }
}
