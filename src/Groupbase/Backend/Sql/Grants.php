<?php
namespace Fgsl\Groupware\Groupbase\Backend\Sql;
use Fgsl\Groupware\Groupbase\Backend\Sql;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Exception\Backend\Database;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\TransactionManager;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\Group\Group;
use Fgsl\Groupware\Groupbase\Acl\Roles;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * backend for records with grants
 *
 * @package     Tinebase
 * @subpackage  Backend
 */
class Grants extends Sql
{
    protected $_recordColumn = 'record_id';

    protected $_recordTable = null;

    /**
     * the constructor
     *
     * allowed options:
     *  - modelName
     *  - tableName
     *  - tablePrefix
     *  - modlogActive
     *  - recordColumn
     *
     * @param array $_options (optional)
     * @param AdapterInterface $_dbAdapter (optional) the db adapter
     * @see AbstractSql::__construct()
     * @throws Database
     */
    public function __construct($_options = array(), $_dbAdapter = NULL)
    {
        if (isset($_options['recordColumn']) && !empty($_options['recordColumn'])) {
            $this->_recordColumn = $_options['recordColumn'];
        }
        if (!isset($_options['recordTable']) && empty($_options['recordTable'])) {
            throw new Database('recordTable needs to be configured');
        }
        $this->_recordTable = $_options['recordTable'];

        parent::__construct($_options, $_dbAdapter);
    }

    /**
     * get grants for records
     * 
     * @param RecordSet $records
     * @param string $aclIdProperty
     */
    public function getGrantsForRecords(RecordSet $records, $aclIdProperty = 'id')
    {
        $recordIds = $aclIdProperty === 'id' ? $records->getArrayOfIds() : $records->{$aclIdProperty};
        if (empty($recordIds)) {
            return;
        }
        
        $select = $this->_getAclSelectByRecordIds($recordIds)
            ->group(array($this->_recordColumn, 'account_type', 'account_id'));
        
        AbstractSql::traitGroup($select);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' ' . $select);
        
        $stmt = $this->_db->query($select);

        $grantsData = $stmt->fetchAll(Adapter::FETCH_ASSOC);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' grantsData: ' . print_r($grantsData, true));

        foreach ($grantsData as $grantData) {
            $givenGrants = explode(',', $grantData['account_grants']);
            foreach ($givenGrants as $grant) {
                $grantData[$grant] = TRUE;
            }
            
            $recordGrant = new $this->_modelName($grantData, true);
            unset($recordGrant->account_grant);

            $recordsToUpdate = $aclIdProperty === 'id'
                ? array($records->getById($recordGrant->{$this->_recordColumn}))
                : $records->filter($aclIdProperty, $recordGrant->{$this->_recordColumn});
            foreach ($recordsToUpdate as $record) {
                // NOTICE: this is strange - we have to remove the record and add it
                //   again to make sure that grants are updated ...
                //   maybe we should add a "replace" method?
                $records->removeRecord($record);
                if (!$record->grants instanceof RecordSet) {
                    $record->grants = new RecordSet($this->_modelName);
                }
                $record->grants->addRecord($recordGrant);
                $records->addRecord($record);
            }
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Records with grants: ' . print_r($records->toArray(), true));
    }
    
    /**
     * get select with acl (grants) by record
     * 
     * @param string|array $recordIds
     * @return Select
     */
    protected function _getAclSelectByRecordIds($recordIds)
    {
         $select = $this->_db->select()
            ->from(
                [$this->getTableName() => SQL_TABLE_PREFIX . $this->getTableName()],
                ['*', 'account_grants' => $this->_dbCommand->getAggregate('account_grant')]
            )
            ->where("{$this->_db->quoteIdentifier($this->_recordColumn)} IN (?)", (array)$recordIds);
         return $select;
    }

    /**
     * clear invalid/dangling grants
     *
     * @refactor create functions for the individual deletes (user/group/roles are VERY similar)
     */
    public function cleanGrants()
    {
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Clear grants in table ' . $this->_tableName . ' for record table ' . $this->_recordTable);

        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);

        $deletedRecordIds = [];

        foreach ($this->_getSelect('id')->joinLeft(
            [$this->_recordTable => SQL_TABLE_PREFIX . $this->_recordTable],
            $this->_db->quoteIdentifier($this->_recordTable) . '.' . $this->_db->quoteIdentifier('id') . ' = ' .
            $this->_db->quoteIdentifier($this->_tableName) . '.' . $this->_db->quoteIdentifier($this->_recordColumn),
            [])->where($this->_db->quoteIdentifier($this->_recordTable) . '.' . $this->_db->quoteIdentifier('id') .
            ' IS NULL')->distinct()->query()->fetchAll(Adapter::FETCH_NUM) as $recordIdRow) {
            $deletedRecordIds[] = $recordIdRow[0];
        }

        if (!empty($deletedRecordIds)) {
            $deleted = $this->_db->delete(SQL_TABLE_PREFIX . $this->_tableName, $this->_db->quoteInto(
                $this->_db->quoteIdentifier('id') . ' IN (?)', $deletedRecordIds));
            unset($deletedRecordIds);

            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' Removed ' . $deleted . ' dangling grants');
        }

        TransactionManager::getInstance()->commitTransaction($transactionId);

        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);

        $accountIds = [];
        foreach ($this->_getSelect(['account_type', 'account_id'])->distinct()->query()->fetchAll(Adapter::FETCH_NUM) as
            $accountRow) {
            $accountIds[$accountRow[0]][] = $accountRow[1];
        }

        if (isset($accountIds['user'])) {
            $userController = User::getInstance();
            $existingIds = $userController->getMultiple($accountIds['user'])->getArrayOfIds();
            $deletedIds = array_diff($accountIds['user'], $existingIds);
            if (!empty($deletedIds)) {
                $deleted = $this->_db->delete(SQL_TABLE_PREFIX . $this->_tableName, $this->_db->quoteIdentifier('account_type') .
                    ' = \'user\' AND ' . $this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . ' IN (?)',
                        $deletedIds));
                unset($deletedIds);

                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Removed ' . $deleted . ' dangling grants for deleted users');
            }
            unset($accountIds['user']);
            unset($existingIds);
        }

        if (isset($accountIds['group'])) {
            $groupController = Group::getInstance();
            $existingIds = $groupController->getMultiple($accountIds['group'])->getArrayOfIds();
            $deletedIds = array_diff($accountIds['group'], $existingIds);
            if (!empty($deletedIds)) {
                $this->_db->delete(SQL_TABLE_PREFIX . $this->_tableName, $this->_db->quoteIdentifier('account_type') .
                    ' = \'group\' AND ' . $this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . ' IN (?)',
                        $deletedIds));
                unset($deletedIds);

                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Removed ' . $deleted . ' dangling grants for deleted groups');
            }
            unset($accountIds['group']);
            unset($existingIds);
        }

        if (isset($accountIds['role'])) {
            $roleController = Roles::getInstance();
            $existingIds = $roleController->getMultiple($accountIds['role'])->getArrayOfIds();
            $deletedIds = array_diff($accountIds['role'], $existingIds);
            if (!empty($deletedIds)) {
                $this->_db->delete(SQL_TABLE_PREFIX . $this->_tableName, $this->_db->quoteIdentifier('account_type') .
                    ' = \'role\' AND ' . $this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . ' IN (?)',
                        $deletedIds));
                unset($deletedIds);

                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Removed ' . $deleted . ' dangling grants for deleted roles');
            }
            unset($accountIds['role']);
            unset($existingIds);
        }

        TransactionManager::getInstance()->commitTransaction($transactionId);
    }
}
