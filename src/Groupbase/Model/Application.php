<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Application\Application as GroupbaseApplication;
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
 * defines the datatype for one application
 * 
 * @package     Groupbase
 * @subpackage  Record
 *
 * @property    string  $id
 * @property    string  $name
 * @property    string  $status
 * @property    string  $version
 * @property    string  $order
 *
 * TODO remove this state property in Release12 and obviously don't use it anymore
 * @property    array   $state
 */
class Application extends AbstractRecord
{
    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var ModelConfiguration
     */
    protected static $_configurationObject = NULL;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = array(
        self::VERSION       => 5,
        'recordName'        => 'Application',
        'recordsName'       => 'Applications', // ngettext('Application', 'Applications', n)
        'hasRelations'      => false,
        'hasCustomFields'   => false,
        'hasNotes'          => false,
        'hasTags'           => false,
        'modlogActive'      => false,
        'hasAttachments'    => false,
        'createModule'      => false,

        'titleProperty'     => 'name',
        'appName'           => 'Tinebase',
        'modelName'         => 'Application',
        self::TABLE         => [
            self::NAME          => 'applications',
            self::INDEXES       => [
                'name'                      => [
                    self::COLUMNS               => ['name'],
                    self::UNIQUE                => true,
                ],
                'status'                    => [
                    self::COLUMNS               => ['status'],
                ],
            ],
        ],

        'fields' => array(
            'name'              => array(
                'label'             => 'Name', //_('Name')
                self::TYPE          => self::TYPE_STRING,
                self::LENGTH        => 25,
                self::NULLABLE      => false,
                'queryFilter'       => TRUE,
                'validators'        => array(Input::ALLOW_EMPTY => false, 'presence' => 'required'),
                'inputFilters'      => array('StringTrim' => NULL),
            ),
            'status'            => array(
                'label'             => 'Status', //_('Status')
                self::TYPE          => self::TYPE_STRING,
                self::LENGTH        => 32,
                self::NULLABLE      => false,
                self::DEFAULT_VAL   => GroupbaseApplication::ENABLED,
                'queryFilter'       => TRUE,
                'validators'        => [['InArray', [
                    GroupbaseApplication::ENABLED,
                    GroupbaseApplication::DISABLED
                ]]],
            ),
            'order'             => array(
                'label'             => 'Order', //_('Order')
                self::TYPE          => self::TYPE_INTEGER,
                self::UNSIGNED      => true,
                self::NULLABLE      => false,
                'queryFilter'       => TRUE,
                'validators'        => array('Digits', 'presence' => 'required'),
            ),
            'version'           => array(
                'label'             => 'Version', //_('Version')
                self::TYPE          => self::TYPE_STRING,
                self::LENGTH        => 25,
                self::NULLABLE      => false,
                'queryFilter'       => TRUE,
                'validators'        => array(Input::ALLOW_EMPTY => false, 'presence' => 'required'),
                'inputFilters'      => array('Zend_Filter_StringTrim' => NULL),
            ),
            // hack for modlogs to be written
            'created_by'        => array(
                self::TYPE          => self::TYPE_VIRTUAL,
                self::VALIDATORS    => [Input::ALLOW_EMPTY => true],
            ),
        )
    );
    
    /**
     * converts a int, string or Tinebase_Model_Application to an accountid
     *
     * @param   int|string|Application $_applicationId the app id to convert
     * @return  int
     * @throws  InvalidArgument
     */
    static public function convertApplicationIdToInt($_applicationId)
    {
        if($_applicationId instanceof Application) {
            if(empty($_applicationId->id)) {
                throw new InvalidArgument('No application id set.');
            }
            $applicationId = $_applicationId->id;
        } elseif (!ctype_digit($_applicationId) && is_string($_applicationId) && strlen($_applicationId) != 40) {
            $applicationId = GroupbaseApplication::getInstance()->getApplicationByName($_applicationId)->getId();
        } else {
            $applicationId = $_applicationId;
        }
        
        return $applicationId;
    }
        
    /**
     * returns applicationname
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }    
    
    /**
     * return the version of the application
     *
     * @return  array with minor and major version
     * @throws  InvalidArgument
     */
    public function getMajorAndMinorVersion()
    {
        if (empty($this->version)) {
            throw new InvalidArgument('No version set.');
        }

        if (strpos($this->version, '.') === false) {
            $minorVersion = 0;
            $majorVersion = $this->version;
        } else {
            list($majorVersion, $minorVersion) = explode('.', $this->version);
        }

        return array('major' => $majorVersion, 'minor' => $minorVersion);
    }

    /**
     * get major app version
     *
     * @return string
     * @throws InvalidArgument
     */
    public function getMajorVersion()
    {
        $versions = $this->getMajorAndMinorVersion();
        return $versions['major'];
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
