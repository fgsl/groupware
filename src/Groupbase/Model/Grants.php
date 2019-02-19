<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Acl\Rights;
use Fgsl\Groupware\Groupbase\Record\Diff;
use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Zend\Validator\InArray;
use Fgsl\Groupware\Groupbase\Model\Calendar\EventPersonalGrants;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Group\Group;
use Fgsl\Groupware\Groupbase\Acl\Roles;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Record\RecordSetDiff;
use Zend\Db\Sql\Select;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * grants model
 * 
 * @package     Groupbase
 * @subpackage  Record
 * @property string         id
 * @property string         record_id
 * @property string         account_grant
 * @property string         account_id
 * @property string         account_type
 */
class Grants extends AbstractRecord
{
    /**
     * grant to read all records of a container / a single record
     */
    const GRANT_READ     = 'readGrant';
    
    /**
     * grant to add a record to a container
     */
    const GRANT_ADD      = 'addGrant';
    
    /**
     * grant to edit all records of a container / a single record
     */
    const GRANT_EDIT     = 'editGrant';
    
    /**
     * grant to delete  all records of a container / a single record
     */
    const GRANT_DELETE   = 'deleteGrant';


    /**
     * grant to export all records of a container / a single record
     */
    const GRANT_EXPORT = 'exportGrant';
    
    /**
     * grant to sync all records of a container / a single record
     */
    const GRANT_SYNC = 'syncGrant';
    
    /**
     * grant to administrate a container
     */
    const GRANT_ADMIN    = 'adminGrant';


    /**
     * grant to download file nodes
     */
    const GRANT_DOWNLOAD = 'downloadGrant';

    /**
     * grant to publish nodes in Filemanager
     * @todo move to Filemanager_Model_Grant once we are able to cope with app specific grant classes
     */
    const GRANT_PUBLISH = 'publishGrant';

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
     * constructor
     * 
     * @param mixed $_data
     * @param bool $_bypassFilters
     * @param mixed $_convertDates
     */
    public function __construct($_data = null, $_bypassFilters = false, $_convertDates = null)
    {
        $this->_validators = array(
            'id'            => array('Alnum', 'allowEmpty' => true),
            'record_id'     => array('allowEmpty' => true),
            'account_grant' => array('allowEmpty' => true),
            'account_id'    => array('presence' => 'required', 'allowEmpty' => true, 'default' => '0'),
            'account_type'  => array('presence' => 'required', array('InArray', array(
                Rights::ACCOUNT_TYPE_ANYONE,
                Rights::ACCOUNT_TYPE_USER,
                Rights::ACCOUNT_TYPE_GROUP,
                Rights::ACCOUNT_TYPE_ROLE
            ))),
        );
        
        foreach ($this->getAllGrants() as $grant) {
            $this->_validators[$grant] = array(
                new InArray(array(true, false), true), 
                'default' => false,
                'presence' => 'required',
                'allowEmpty' => true
            );
            
            // initialize in case validators are switched off
            $this->{$grant} = false;
        }
        
        parent::__construct($_data, $_bypassFilters, $_convertDates);
    }
    
    /**
     * get all possible grants
     *
     * @return  array   all container grants
     */
    public static function getAllGrants()
    {
        $allGrants = array(
            self::GRANT_READ,
            self::GRANT_ADD,
            self::GRANT_EDIT,
            self::GRANT_DELETE,
            EventPersonalGrants::GRANT_PRIVATE,
            self::GRANT_EXPORT,
            self::GRANT_SYNC,
            self::GRANT_ADMIN,
            EventPersonalGrants::GRANT_FREEBUSY,
            self::GRANT_DOWNLOAD,
            self::GRANT_PUBLISH,
        );
    
        return $allGrants;
    }

    /**
     * checks record grant
     * 
     * @param string $grant
     * @param FullUser $user
     * @return boolean
     */
    public function userHasGrant($grant, FullUser $user = null)
    {
        if ($user === null) {
            $user = Core::getUser();
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Check grant ' . $grant . ' for user ' . $user->getId() . ' in ' . print_r($this->toArray(), true));
        
        if (! is_object($user)) {
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                . ' No user object');
            return false;
        }
        
        if (! in_array($grant, $this->getAllGrants()) || ! isset($this->{$grant}) || ! $this->{$grant}) {
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                . ' Grant not defined or not set');
            return false;
        }
        
        switch ($this->account_type) {
            case Rights::ACCOUNT_TYPE_GROUP:
                if (! in_array($user->getId(), Group::getInstance()->getGroupMembers($this->account_id))) {
                    if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                        . ' Current user not member of group ' . $this->account_id);
                    return false;
                }
                break;
            case Rights::ACCOUNT_TYPE_USER:
                if ($user->getId() !== $this->account_id) {
                    if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                        . ' Grant not available for current user (account_id of grant: ' . $this->account_id . ')');
                    return false;
                }
                break;
            case Rights::ACCOUNT_TYPE_ROLE:
                $userId = $user->getId();
                foreach (Roles::getInstance()->getRoleMembers($this->account_id) as $roleMember) {
                    if (Rights::ACCOUNT_TYPE_USER === $roleMember['account_type'] &&
                            $userId === $roleMember['account_id']) {
                        return true;
                    }
                    if (Rights::ACCOUNT_TYPE_GROUP === $roleMember['account_type'] &&
                        in_array($user->getId(), Group::getInstance()->getGroupMembers($roleMember['account_id']))) {
                        return true;
                    }
                }
                if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                    . ' Current user not member of role ' . $this->account_id);
                return false;
        }
        
        return true;
    }

    /**
     * fills record with all grants and adds account id
     */
    public function sanitizeAccountIdAndFillWithAllGrants()
    {
        if (empty($this->account_id)) {
            if ($this->account_type === Rights::ACCOUNT_TYPE_USER && 
                is_object(Core::getUser())) 
            {
                $this->account_id = Core::getUser()->getId();
            } else if ($this->account_type === Rights::ACCOUNT_TYPE_GROUP || 
                Config::getInstance()->get(Config::ANYONE_ACCOUNT_DISABLED))
            {
                $this->account_type = Rights::ACCOUNT_TYPE_GROUP;
                $this->account_id = Group::getInstance()->getDefaultAdminGroup()->getId();
            } else {
                $this->account_type = Rights::ACCOUNT_TYPE_ANYONE;
                $this->account_id = 0;
            }
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
            . ' Set all available grants for ' . $this->account_type . ' with id ' . $this->account_id);
        
        foreach ($this->getAllGrants() as $grant) {
            $this->$grant = true;
        }
        
        return $this;
    }

    /**
     * return default grants with read for user group, write/admin for current user and write/admin for admin group
     *
     * @param array $_additionalGrants
     * @param array $_additionalAdminGrants
     * @return RecordSet of Grants
     */
    public static function getDefaultGrants($_additionalGrants = array(), $_additionalAdminGrants = array())
    {
        $groupsBackend = Group::getInstance();
        $adminGrants = array_merge(array_merge([
            Grants::GRANT_READ => true,
            Grants::GRANT_ADD => true,
            Grants::GRANT_EDIT => true,
            Grants::GRANT_DELETE => true,
            Grants::GRANT_ADMIN => true,
            Grants::GRANT_EXPORT => true,
            Grants::GRANT_SYNC => true,
        ], $_additionalGrants), $_additionalAdminGrants);
        $grants = [
            array_merge([
                'account_id' => $groupsBackend->getDefaultGroup()->getId(),
                'account_type' => Rights::ACCOUNT_TYPE_GROUP,
                Grants::GRANT_READ => true,
                Grants::GRANT_EXPORT => true,
                Grants::GRANT_SYNC => true,
            ], $_additionalGrants),
            array_merge([
                'account_id' => $groupsBackend->getDefaultAdminGroup()->getId(),
                'account_type' => Rights::ACCOUNT_TYPE_GROUP,
            ], $adminGrants),
        ];

        if (is_object(Core::getUser())) {
            $grants[] = array_merge([
                'account_id' => Core::getUser()->getId(),
                'account_type' => Rights::ACCOUNT_TYPE_USER,
            ], $adminGrants);
        }

        return new RecordSet('Grants', $grants,true);
    }

    /**
     * return personal grants for given account
     *
     * @param string|ModelUser          $_accountId
     * @param array $_additionalGrants
     * @return RecordSet of Grants
     */
    public static function getPersonalGrants($_accountId, $_additionalGrants = array())
    {
        $accountId = ModelUser::convertUserIdToInt($_accountId);
        $grants = array(Grants::GRANT_READ      => true,
            Grants::GRANT_ADD       => true,
            Grants::GRANT_EDIT      => true,
            Grants::GRANT_DELETE    => true,
            Grants::GRANT_EXPORT    => true,
            Grants::GRANT_SYNC      => true,
            Grants::GRANT_ADMIN     => true,
        );
        $grants = array_merge($grants, $_additionalGrants);
        return new RecordSet('Grants', array(array_merge(array(
            'account_id'     => $accountId,
            'account_type'   => Rights::ACCOUNT_TYPE_USER,
        ), $grants)));
    }

    /**
     * @param RecordSet $_recordSet
     * @param RecordSetDiff $_recordSetDiff
     * @return bool
     * @throws InvalidArgument
     */
    public static function applyRecordSetDiff(RecordSet $_recordSet, RecordSetDiff $_recordSetDiff)
    {
        $model = $_recordSetDiff->model;
        if ($_recordSet->getRecordClassName() !== $model) {
            throw new InvalidArgument('try to apply record set diff on a record set of different model!' .
                'record set model: ' . $_recordSet->getRecordClassName() . ', record set diff model: ' . $model);
        }

        /** @var Tinebase_Record_Interface $modelInstance */
        $modelInstance = new $model(array(), true);
        $idProperty = $modelInstance->getIdProperty();

        foreach($_recordSetDiff->removed as $data) {
            $found = false;
            /** @var Grants $record */
            foreach ($_recordSet as $record) {
                if (    $record->record_id      === $data['record_id']      &&
                        $record->account_id     === $data['account_id']     &&
                        $record->account_type   === $data['account_type']       ) {
                    $found = true;
                    break;
                }
            }
            if (true === $found) {
                $_recordSet->removeRecord($record);
            }
        }

        foreach($_recordSetDiff->modified as $data) {
            $diff = new Diff($data);
            $found = false;
            /** @var Grants $record */
            foreach ($_recordSet as $record) {
                if (    $record->record_id      === $diff->diff['record_id']      &&
                        $record->account_id     === $diff->diff['account_id']     &&
                        $record->account_type   === $diff->diff['account_type']       ) {
                    $found = true;
                    break;
                }
            }
            if (true === $found) {
                $record->applyDiff($diff);
            } else {
                Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                    . ' Did not find the record supposed to be modified with id: ' . $data[$idProperty]);
                throw new InvalidArgument('Did not find the record supposed to be modified with id: ' . $data[$idProperty]);
            }
        }

        foreach($_recordSetDiff->added as $data) {
            $found = false;
            /** @var Grants $record */
            foreach ($_recordSet as $record) {
                if (    $record->record_id      === $data['record_id']      &&
                        $record->account_id     === $data['account_id']     &&
                        $record->account_type   === $data['account_type']       ) {
                    $found = true;
                    break;
                }
            }
            if (true === $found) {
                $_recordSet->removeRecord($record);
            }
            $newRecord = new $model($data);
            $_recordSet->addRecord($newRecord);
        }

        return true;
    }

    /**
     * @param RecordSet $_recordSetOne
     * @param RecordSet $_recordSetTwo
     * @return null|RecordSetDiff
     */
    public static function recordSetDiff(RecordSet $_recordSetOne, RecordSet $_recordSetTwo)
    {
        $shallowCopyTwo = new RecordSet(self::class);
        $removed = new RecordSet(self::class);
        $added = new RecordSet(self::class);
        $modified = new RecordSet('Tinebase_Record_Diff');

        foreach ($_recordSetTwo as $grantTwo) {
            $shallowCopyTwo->addRecord($grantTwo);
        }

        /** @var Grants $grantOne */
        foreach ($_recordSetOne as $grantOne) {
            $found = false;
            /** @var Grants $grantTwo */
            foreach ($shallowCopyTwo as $grantTwo) {
                if (    $grantOne->record_id      === $grantTwo->record_id      &&
                        $grantOne->account_id     === $grantTwo->account_id     &&
                        $grantOne->account_type   === $grantTwo->account_type       ) {
                    $found = true;
                    break;
                }
            }

            if (true === $found) {
                $shallowCopyTwo->removeRecord($grantTwo);
                $diff = $grantOne->diff($grantTwo, array('id', 'account_grant'));
                if (!$diff->isEmpty()) {
                    $diff->xprops('diff')['record_id']    = $grantTwo->record_id;
                    $diff->xprops('diff')['account_id']   = $grantTwo->account_id;
                    $diff->xprops('diff')['account_type'] = $grantTwo->account_type;
                    $diff->xprops('oldData')['record_id']    = $grantTwo->record_id;
                    $diff->xprops('oldData')['account_id']   = $grantTwo->account_id;
                    $diff->xprops('oldData')['account_type'] = $grantTwo->account_type;
                    $modified->addRecord($diff);
                }
            } else {
                $removed->addRecord($grantOne);
            }
        }

        /** @var Grants $grantTwo */
        foreach ($shallowCopyTwo as $grantTwo) {
            $added->addRecord($grantTwo);
        }

        $result = new Diff(array(
            'model'    => self::class,
            'added'    => $added,
            'removed'  => $removed,
            'modified' => $modified,
        ));

        return $result;
    }

    /**
     * @return bool
     */
    public static function doSetGrantFailsafeCheck()
    {
        return true;
    }

    /**
     * @param Select $_select
     * @param ModelApplication $_application
     * @param string $_accountId
     * @param string|array $_grant
     */
    public static function addCustomGetSharedContainerSQL(Select $_select,
        ModelApplication $_application, $_accountId, $_grant)
    {
    }
}
