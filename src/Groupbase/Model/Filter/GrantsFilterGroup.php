<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;

use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Model\PersistentFilterGrant;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Container;
use Psr\Log\LogLevel;

/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Filter
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Tinebase_Model_Filter_GrantsFilterGroup 
 * @package     Tinebase
 * @subpackage  Filter
 */
class GrantsFilterGroup extends FilterGroup implements AclFilter
{
    /**
     * @var string acl table name
     */
    protected $_aclTableName = null;

    /**
     * @var string the alias for the acl table name
     */
    protected $_joinedTableAlias = null;

    /**
     * @var string acl record column for join with acl table
     */
    protected $_aclIdColumn = 'id';

    /**
     * @var array one of these grants must be met
     */
    protected $_requiredGrants = array(
        PersistentFilterGrant::GRANT_READ
    );

    /**
     * is acl filter resolved?
     *
     * TODO needed here?
     *
     * @var boolean
     */
    protected $_isResolved = FALSE;

    /**
     * sets the grants this filter needs to assure
     *
     * @param array $_grants
     */
    public function setRequiredGrants(array $_grants)
    {
        $this->_requiredGrants = $_grants;
        $this->_isResolved = FALSE;
    }

    /**
     * appends custom filters to a given select object
     *
     * @param  Select                    $select
     * @param  AbstractSql               $backend
     * @return void
     */
    public function appendFilterSql($select, $backend)
    {
        $this->_appendAclSqlFilter($select, $backend);
    }

    /**
     * add account id to filter
     *
     * @param Select $select
     * @param AbstractSql $backend
     */
    protected function _appendAclSqlFilter($select, $backend)
    {
        if (! $this->_isResolved) {
            $this->_appendGrantsFilter($select, $backend);

            $this->_isResolved = TRUE;
        }
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
        if ($this->_ignoreAcl) {
            return;
        }

        if (! $user) {
            $user = isset($this->_options['user']) ? $this->_options['user'] : Core::getUser();
        }

        $this->_joinedTableAlias = uniqid($this->_aclTableName);
        $db = $backend->getAdapter();
        $select->join(array(
            /* table  */ $this->_joinedTableAlias => SQL_TABLE_PREFIX . $this->_aclTableName),
            /* on     */ "{$db->quoteIdentifier($this->_joinedTableAlias . '.record_id')} = {$db->quoteIdentifier($backend->getTableName() . '.' . $this->_aclIdColumn)}",
            /* select */ array()
        );
        
        Container::addGrantsSql($select, $user, $this->_requiredGrants, $this->_joinedTableAlias);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' $select after appending grants sql: ' . $select);
    }
}
