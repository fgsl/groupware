<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * defines the datatype for roles
 * 
 * @package     Groupbase
 * @subpackage  Acl
 *
 * @property string                     $id
 * @property string                     $name
 * @property RecordSet  $members
 * @property RecordSet  $rights
 *
 */
class Role extends AbstractRecord
{
    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var ModelConfiguration
     */
    protected static $_configurationObject = NULL;

    protected static $_isReplicable = true;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = array(
        'recordName'        => 'Role',
        'recordsName'       => 'Roles', // ngettext('Role', 'Roles', n)
        'hasRelations'      => FALSE,
        'hasCustomFields'   => FALSE,
        'hasNotes'          => FALSE,
        'hasTags'           => FALSE,
        'modlogActive'      => TRUE,
        'hasAttachments'    => FALSE,
        'createModule'      => FALSE,

        'titleProperty'     => 'name',
        'appName'           => 'Tinebase',
        'modelName'         => 'Role',

        'fields' => array(
            'name'              => array(
                'label'             => 'Name', //_('Name')
                'type'              => 'string',
                'queryFilter'       => TRUE,
                'validators'        => array(Input::ALLOW_EMPTY => false, 'presence' => 'required'),
                'inputFilters'      => array('Zend_Filter_StringTrim' => NULL),
            ),
            'description'       => array(
                'label'             => 'Description', //_('Description')
                'type'              => 'string',
                'queryFilter'       => TRUE,
                'validators'        => array(Input::ALLOW_EMPTY => TRUE),
            ),
            'rights'            => array(
                'label'             => 'Rights', // _('Rights')
                'type'              => 'records', // be careful: records type has no automatic filter definition!
                'config'            => array(
                    'appName'               => 'Tinebase',
                    'modelName'             => 'RoleRight',
                    'controllerClassName'   => 'Tinebase_RoleRight',
                    'refIdField'            => 'role_id',
                ),
                'validators'        => array(Input::ALLOW_EMPTY => TRUE, Input::DEFAULT_VALUE => NULL),
            ),
            'members'           => array(
                'label'             => 'Members', // _('Members')
                'type'              => 'records', // be careful: records type has no automatic filter definition!
                'config'            => array(
                    'appName'               => 'Tinebase',
                    'modelName'             => 'RoleMember',
                    'controllerClassName'   => 'Tinebase_RoleMember',
                    'refIdField'            => 'role_id',
                ),
                'validators'        => array(Input::ALLOW_EMPTY => TRUE, Input::DEFAULT_VALUE => NULL),
            ),
        )
    );
    
    /**
     * returns role name
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    public static function setIsReplicable($bool = true)
    {
        static::$_isReplicable = (bool)$bool;
    }

    /**
     * returns true if this record should be replicated
     *
     * @return boolean
     */
    public function isReplicable()
    {
        return static::$_isReplicable;
    }

    /**
     * converts a int, string or Role to a roleid
     *
     * @param   int|string|Role $_roleId the roleid to convert
     * @return  string
     * @throws  InvalidArgument
     *
     * @todo rename this function because we now have string ids
     */
    static public function convertRoleIdToInt($_roleId)
    {
        return self::convertId($_roleId, 'Fgsl\Groupware\Groupbase\Model\Role');
    }

    public function runConvertToRecord()
    {
        if (isset($this->_properties['deleted_time']) && $this->_properties['deleted_time'] == '1970-01-01 00:00:00') {
            unset($this->_properties['deleted_time']);
        }

        parent::runConvertToRecord();
    }

    public function runConvertToData()
    {
        if (array_key_exists('deleted_time', $this->_properties) && null === $this->_properties['deleted_time']) {
            unset($this->_properties['deleted_time']);
        }

        parent::runConvertToData();
    }
}
