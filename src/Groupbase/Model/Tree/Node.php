<?php
namespace Fgsl\Groupware\Groupbase\Model\Tree;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\FileSystem\FileSystem;
use Fgsl\Groupware\Groupbase\FileSystem\Previews;
use Zend\Filter\StringTrim;
use Zend\Validator\NotEmpty;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Exception\Record\Validation;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class to hold data representing one node in the tree
 *
 * @package     Tinebase
 * @subpackage  Model
 * @property    string                      contenttype
 * @property    Tinebase_DateTime           creation_time
 * @property    string                      hash
 * @property    string                      indexed_hash
 * @property    string                      name
 * @property    Tinebase_DateTime           last_modified_time
 * @property    string                      object_id
 * @property    string                      parent_id
 * @property    string                      size
 * @property    string                      revision_size
 * @property    string                      type
 * @property    string                      revision
 * @property    string                      available_revisions
 * @property    string                      description
 * @property    string                      acl_node
 * @property    array                       revisionProps
 * @property    array                       notificationProps
 * @property    string                      preview_count
 * @property    integer                     quota
 * @property    Tinebase_Record_RecordSet   grants
 * @property    string                      pin_protected_node
 * @property    string                      path
 * @property    Tinebase_DateTime           lastavscan_time
 * @property    boolean                     is_quarantined
 */
class Node extends AbstractRecord
{
    const XPROPS_REVISION = 'revisionProps';
    const XPROPS_REVISION_NODE_ID = 'nodeId';
    const XPROPS_REVISION_ON = 'keep';
    const XPROPS_REVISION_NUM = 'keepNum';
    const XPROPS_REVISION_MONTH = 'keepMonth';

    /**
     * {"notificationProps":[{"active":true,....},{"active":true....},{....}]}
     */
    const XPROPS_NOTIFICATION = 'notificationProps';
    const XPROPS_NOTIFICATION_ACTIVE = 'active';
    const XPROPS_NOTIFICATION_SUMMARY = 'summary';
    const XPROPS_NOTIFICATION_ACCOUNT_ID = 'accountId';
    const XPROPS_NOTIFICATION_ACCOUNT_TYPE = 'accountType';
    
    /**
     * key in $_validators/$_properties array for the filed which
     * represents the identifier
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
    protected static $_modelConfiguration = [
        'hasRelations'      => true,
        'hasCustomFields'   => true,
        'hasNotes'          => true,
        'hasTags'           => true,
        'hasXProps'         => false,
        'modlogActive'      => true,
        'hasAttachments'    => false,
        'createModule'      => false,
        'exposeHttpApi'     => false,
        'exposeJsonApi'     => false,

        'titleProperty'     => 'name',
        'appName'           => 'Tinebase',
        'modelName'         => 'Tree_Node',
        'idProperty'        => 'id',
        'table'             => [
            'name'              => 'tree_nodes',
        ],

        'filterModel'       => [],

        'fields'            => [
            'parent_id'                     => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'object_id'                     => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
            ],
            'revisionProps'                 => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'notificationProps'             => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            // contains id of node with acl info
            'acl_node'                      => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'name'                          => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
            ],
            'islink'                        => [
                'type'                          => 'integer',
                'validators'                    => [Input::DEFAULT_VALUE => 0],
            ],
            'quota'                         => [
                'type'                          => 'integer',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            // fields from filemanager_objects table (ro)
            'type'                          => [
                'type'                          => 'string',
                'validators'                    => ['inArray' => [
                    FileObject::TYPE_FILE,
                    FileObject::TYPE_FOLDER,
                    FileObject::TYPE_PREVIEW,
                ], Input::ALLOW_EMPTY => true,],
            ],
            'description'                   => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'contenttype'                   => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'revision'                      => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'available_revisions'           => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'hash'                          => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'indexed_hash'                  => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'isIndexed'                     => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'size'                          => [
                'type'                          => 'integer',
                'modlogOmit'                    => true,
                'validators'                    => [
                    Input::ALLOW_EMPTY => true,
                    NotEmpty::class => true,
                    Input::DEFAULT_VALUE => 0
                ],
            ],
            'revision_size'                 => [
                'type'                          => 'integer',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'preview_count'                 => [
                'type'                          => 'integer',
                'modlogOmit'                    => true,
                'validators'                    => [
                    Input::ALLOW_EMPTY => true,
                    'Digits',
                    Input::DEFAULT_VALUE => 0,
                ],
            ],
            'pin_protected_node'            => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'lastavscan_time'            => [
                'type'                          => 'datetime',
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'modlogOmit'                    => true,
            ],
            'is_quarantined'            => [
                'type'                          => 'boolean',
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'default'                       => 0,
                'modlogOmit'                    => true,
            ],

            // not persistent
            'container_name'                => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],

            // this is needed should be sent by / delivered to client (not persistent in db atm)
            'path'                          => [
                'type'                          => 'string',
                'modlogOmit'                    => true,
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'account_grants'                => [
                //'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'tempFile'                      => [
                //'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'stream'                        => [
                //'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            // acl grants
            'grants'                        => [
                //'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
        ],
    ];

    /**
     * if foreign Id fields should be resolved on search and get from json
     * should have this format:
     *     array('Calendar_Model_Contact' => 'contact_id', ...)
     * or for more fields:
     *     array('Calendar_Model_Contact' => array('contact_id', 'customer_id), ...)
     * (e.g. resolves contact_id with the corresponding Model)
     *
     * @var array
     */
    protected static $_resolveForeignIdFields = array(
        'Tinebase_Model_User' => array('created_by', 'last_modified_by')
    );

    public function runConvertToRecord()
    {
        if (isset($this->_properties['deleted_time']) && $this->_properties['deleted_time'] == '1970-01-01 00:00:00') {
            unset($this->_properties['deleted_time']);
        }
        if (isset($this->_properties['available_revisions']) && is_string($this->_properties['available_revisions'])) {
            $this->_properties['available_revisions'] = explode(',', ltrim(
                rtrim($this->_properties['available_revisions'], '}'), '{'));
        }

        parent::runConvertToRecord();
    }

    public function runConvertToData()
    {
        if (array_key_exists('deleted_time', $this->_properties) && null === $this->_properties['deleted_time']) {
            unset($this->_properties['deleted_time']);
        }
        if (isset($this->_properties[self::XPROPS_REVISION]) && is_array($this->_properties[self::XPROPS_REVISION])) {
            if (count($this->_properties[self::XPROPS_REVISION]) > 0) {
                $this->_properties[self::XPROPS_REVISION] = json_encode($this->_properties[self::XPROPS_REVISION]);
            } else {
                $this->_properties[self::XPROPS_REVISION] = null;
            }
        }

        if (isset($this->_properties[self::XPROPS_NOTIFICATION]) &&
                is_array($this->_properties[self::XPROPS_NOTIFICATION])) {
            if (count($this->_properties[self::XPROPS_NOTIFICATION]) > 0) {
                $this->_properties[self::XPROPS_NOTIFICATION] =
                    json_encode($this->_properties[self::XPROPS_NOTIFICATION]);
            } else {
                $this->_properties[self::XPROPS_NOTIFICATION] = null;
            }
        }

        parent::runConvertToData();
    }

    /**
     * returns real filesystem path
     *
     * @param string $baseDir
     * @throws NotFound
     * @return string
     */
    public function getFilesystemPath($baseDir = null)
    {
        if (empty($this->hash)) {
            throw new NotFound('file object hash is missing');
        }

        if ($baseDir === null) {
            $baseDir = Core::getConfig()->filesdir;
        }

        return $baseDir . DIRECTORY_SEPARATOR . substr($this->hash, 0, 3) . DIRECTORY_SEPARATOR .
            substr($this->hash, 3);
    }

    public function isValid($_throwExceptionOnInvalidData = false)
    {
        if ($this->_isValidated === true) {
            return true;
        }

        $invalid = array();
        if (!empty($this->xprops(static::XPROPS_REVISION))) {
            if (count($this->revisionProps) > 4 || !isset($this->revisionProps[static::XPROPS_REVISION_NODE_ID])
                    || !isset($this->revisionProps[static::XPROPS_REVISION_MONTH]) ||
                    !isset($this->revisionProps[static::XPROPS_REVISION_ON]) ||
                    !isset($this->revisionProps[static::XPROPS_REVISION_NUM])) {
                $invalid[] = 'revisionProps';
            }
        }
        if (!empty($this->xprops(static::XPROPS_NOTIFICATION))) {
            foreach ($this->notificationProps as $val) {
                foreach ($val as $key => $val1) {
                    if (!in_array($key, array(
                            static::XPROPS_NOTIFICATION_ACCOUNT_ID,
                            static::XPROPS_NOTIFICATION_ACCOUNT_TYPE,
                            static::XPROPS_NOTIFICATION_ACTIVE,
                            static::XPROPS_NOTIFICATION_SUMMARY))) {
                        $invalid[] = 'notificationProps';
                        break 2;
                    }
                }
            }
        }

        if (!empty($invalid) && $_throwExceptionOnInvalidData) {
            $e = new Validation('Some fields ' . implode(',', $invalid)
                . ' have invalid content');

            if (Core::isLogLevel(LogLevel::ERR)) Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . " "
                . $e->getMessage()
                . print_r($this->_validationErrors, true));
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Record: ' . print_r($this->toArray(), true));

            throw $e;
        }

        return parent::isValid($_throwExceptionOnInvalidData);
    }

    /**
     * returns true if this record should be replicated
     *
     * @return boolean
     */
    public function isReplicable()
    {
        if ($this->type === FileObject::TYPE_FILE) {
            return true;
        }
        if ($this->type === FileObject::TYPE_FOLDER) {
            if (strlen($this->name) === 3 &&
                    Previews::getInstance()->getBasePathNode()->getId() === $this->parent_id) {
                return false;
            }
            if (strlen($this->name) === 37 && null !== $this->parent_id &&
                    Previews::getInstance()->getBasePathNode()->getId() ===
                    FileSystem::getInstance()->get($this->parent_id)->parent_id) {
                return false;
            }
            return true;
        }
        return false;
    }
}
