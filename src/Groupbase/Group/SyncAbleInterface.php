<?php
namespace Fgsl\Groupware\Groupbase\Group;

use Fgsl\Groupware\Groupbase\Model\Group as ModelGroup;
use Fgsl\Groupware\Groupbase\Model\User;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * syncable group backend interface
 *
 * @package     Groupbase
 * @subpackage  Group
 */
interface SyncAbleInterface
{
    /**
     * resolve groupid(for example ldap gidnumber) to uuid(for example ldap entryuuid)
     *
     * @param   string  $_groupId
     * 
     * @return  string  the uuid for groupid
     */
    public function resolveSyncAbleGidToUUid($_groupId);
    
    /**
     * get syncable group by id from sync backend
     * 
     * @param  mixed  $_groupId  the groupid
     * 
     * @return ModelGroup
     */
    public function getGroupByIdFromSyncBackend($_groupId);

    /**
     * create a new group in sync backend
     *
     * @param  ModelGroup  $_group
     * 
     * @return ModelGroup
     */
    public function addGroupInSyncBackend(ModelGroup $_group);
     
    /**
     * get groupmemberships of user from sync backend
     * 
     * @param   User  $_user
     * 
     * @return  array  list of group ids
     */
    public function getGroupMembershipsFromSyncBackend($_userId);
        
    /**
     * get list of groups from syncbackend
     *
     * @param  string  $_filter
     * @param  string  $_sort
     * @param  string  $_dir
     * @param  int     $_start
     * @param  int     $_limit
     * 
     * @return RecordSet with record class ModelGroup
     */
    public function getGroupsFromSyncBackend($_filter = NULL, $_sort = 'name', $_dir = 'ASC', $_start = NULL, $_limit = NULL);
    
    /**
     * return whether backend is read only
     */
    public function isReadOnlyBackend();
    
    /**
     * return whether backend is disabled
     */
    public function isDisabledBackend();
    
    /**
     * replace all current groupmembers with the new groupmembers list in sync backend
     *
     * @param  string  $_groupId
     * @param  array   $_groupMembers array of ids
     */
    public function setGroupMembersInSyncBackend($_groupId, $_groupMembers);
     
    /**
     * add a new groupmember to group in sync backend
     *
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId string or user object
     */
    public function addGroupMemberInSyncBackend($_groupId, $_accountId);

    /**
     * remove one member from the group in sync backend
     *
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId
     */
    public function removeGroupMemberInSyncBackend($_groupId, $_accountId);
    
    /**
     * updates an existing group in sync backend
     *
     * @param  ModelGroup  $_group
     * 
     * @return ModelGroup
     */
    public function updateGroupInSyncBackend(ModelGroup $_group);
    
    /**
     * delete one or more groups in sync backend
     *
     * @param  mixed   $_groupId
     */
    public function deleteGroupsInSyncBackend($_groupId);
    
    /**
     * replace all current groupmemberships of user in sync backend
     *
     * @param  mixed  $_userId
     * @param  mixed  $_groupIds
     * 
     * @return array
     */
    public function setGroupMembershipsInSyncBackend($_userId, $_groupIds);
    
    /**
     * merges missing properties from existing sql group into group fetchted from sync backend
     * 
     * @param ModelGroup $syncGroup
     * @param ModelGroup $sqlGroup
     */
    public static function mergeMissingProperties($syncGroup, $sqlGroup);
}
