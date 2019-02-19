<?php
namespace Fgsl\Groupware\Groupbase\Model\Tree\Node;
use Fgsl\Groupware\Groupbase\AreaLock\AreaLock;
use Fgsl\Groupware\Groupbase\Model\AreaLockConfig;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterBool;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterInt;
use Fgsl\Groupware\Groupbase\Model\Filter\GrantsFilterGroup;
use Fgsl\Groupware\Groupbase\Model\Tree\FileObject;
use Fgsl\Groupware\Groupbase\Model\Tree\Node;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * tree node filter class
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
class Filter extends GrantsFilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Groupbase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = Node::class;

    /**
     * @var string acl table name
     */
    protected $_aclTableName = 'tree_node_acl';

    /**
     * @var string acl record column for join with acl table
     */
    protected $_aclIdColumn = 'acl_node';

    /**
     * @var bool
     */
    protected $_ignorePinProtection = false;

    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'query'                 => array(
            'filter' => 'FilterQuery', 
            'options' => array('fields' => array('name', 'content', 'description'))
        ),
        'id'                    => array('filter' => 'FilterId'),
        'path'                  => array('filter' => 'Tinebase_Model_Tree_Node_PathFilter'),
        'parent_id'             => array('filter' => 'FilterText'),
        'name'                  => array(
            'filter' => 'FilterText',
            'options' => array('binary' => true)
        ),
        'object_id'             => array('filter' => 'FilterText'),
        'acl_node'              => array('filter' => 'FilterText'),
    // tree_fileobjects table
        'last_modified_time'    => array(
            'filter' => 'FilterDate',
            'options' => array('tablename' => 'tree_fileobjects')
        ),
        'deleted_time'          => array(
            'filter' => 'FilterDateTime',
            'options' => array('tablename' => 'tree_fileobjects')
        ),
        'creation_time'         => array(
            'filter' => 'FilterDate',
            'options' => array('tablename' => 'tree_fileobjects')
        ),
        'last_modified_by'      => array(
            'filter' => 'FilterUser',
            'options' => array('tablename' => 'tree_fileobjects'
        )),
        'created_by'            => array(
            'filter' => 'FilterUser',
            'options' => array('tablename' => 'tree_fileobjects')
        ),
        'type'                  => array(
            'filter' => 'FilterText', 
            'options' => array('tablename' => 'tree_fileobjects')
        ),
        'contenttype'           => array(
            'filter' => 'FilterText',
            'options' => array('tablename' => 'tree_fileobjects')
        ),
        'description'           => array(
            'filter' => 'FilterFullText',
            'options' => array('tablename' => 'tree_fileobjects')
        ),
    // tree_filerevisions table
        'size'                  => array(
            'filter' => FilterInt::class,
            'options' => array('tablename' => 'tree_filerevisions')
        ),
    // recursive search
        'recursive'             => array(
            'filter' => 'FilterBool'
        ),
        'tag'                   => array('filter' => 'FilterTag', 'options' => array(
            'idProperty' => 'tree_nodes.id',
            'applicationName' => 'Tinebase',
        )),
    // fulltext search
        'content'               => array(
            'filter'                => 'FilterExternalFullText',
            'options'               => array(
                'idProperty'            => 'object_id',
            )
        ),
        'isIndexed'             => array(
            'filter'                => 'Fgsl\Groupware\Groupase\Model\Tree\Node\IsIndexedFilter',
        ),
        'is_deleted'            => array(
            'filter'                => FilterBool::class
        ),
        'quota'                 => array(
            'filter'                => FilterInt::class
        )
    );

    /**
     * set options
     *
     * @param array $_options
     */
    protected function _setOptions(array $_options)
    {
        if (isset($_options['nameCaseInSensitive']) && $_options['nameCaseInSensitive']) {
            $this->_filterModel['name']['options']['caseSensitive'] = false;
        }
        parent::_setOptions($_options);
    }

    /**
     * append grants acl filter
     *
     * @param Select $select
     * @param AbstractSql $backend
     * @param ModelUser $user
     */
    protected function _appendGrantsFilter($select, $backend, $user = null)
    {
        parent::_appendGrantsFilter($select, $backend, $user);

        if (! $this->_ignorePinProtection
            && AreaLock::getInstance()->hasLock(AreaLockConfig::AREA_DATASAFE)
            && AreaLock::getInstance()->isLocked(AreaLockConfig::AREA_DATASAFE)
        ) {
            $db = $backend->getAdapter();
            $uniqueId = uniqid('pinProtected');
            $select->joinLeft(array(
                /* table  */ $uniqueId => SQL_TABLE_PREFIX . $backend->getTableName()),
                /* on     */ "{$db->quoteIdentifier($uniqueId . '.id')} = {$db->quoteIdentifier($backend->getTableName() . '.' . $this->_aclIdColumn)}",
                /* select */ array()
            );
            $select->where("{$db->quoteIdentifier($uniqueId . '.pin_protected_node')} IS NULL");
        }

        // TODO do something when acl_node = NULL?
    }

    public function ignorePinProtection($_value = true)
    {
        $this->_ignorePinProtection = $_value;
    }

    /**
     * return folder + parent_id filter with ignore acl
     *
     * @param $folderId
     * @return Filter
     */
    public static function getFolderParentIdFilterIgnoringAcl($folderId)
    {
        return new Filter(array(
            array(
                'field'     => 'parent_id',
                'operator'  => $folderId === null ? 'isnull' : 'equals',
                'value'     => $folderId
            ), array(
                'field'     => 'type',
                'operator'  => 'equals',
                'value'     => FileObject::TYPE_FOLDER
            )
        ), FilterGroup::CONDITION_AND, array('ignoreAcl' => true));
    }

    /**
     * check if filter is a recursive filter
     *
     * recursive must be set AND a recursive criteria must be given
     *
     * @return bool
     */
    public function isRecursiveFilter($removeIfNot = false)
    {
        if ($this->getFilter('recursive', false, true)) {
            foreach($this->getFilterModel() as $field => $config) {
                if ($filter = $this->getFilter($field, false, true)) {
                    if (in_array($field, ['path', 'type', 'recursive'])) continue;
                    if ($field == 'query' && !$filter->getValue()) continue;

                    return true;
                }
            }

            if ($removeIfNot) {
                $this->removeFilter('recursive', true);
            }
        }

        return false;
    }
}
