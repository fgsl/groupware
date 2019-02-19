<?php
namespace Fgsl\Groupware\Groupbase\User;

use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Model\FullUser;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * syncable user backend interface
 *
 * @package     Groupbase
 * @subpackage  User
 */
interface SyncAbleInterface
{
    /**
     * get user by login name
     *
     * @param   string $_property
     * @param   string $_accountId
     * @param   string $_accountClass
     * @return ModelUser the user object
     */
    public function getUserByPropertyFromSyncBackend($_property, $_accountId, $_accountClass = 'ModelUser');

    /**
     * get list of users
     *
     * @param string $_filter
     * @param string $_sort
     * @param string $_dir
     * @param int $_start
     * @param int $_limit
     * @param string $_accountClass the type of subclass for the RecordSet to return
     * @return RecordSet with record class ModelUser
     */
    public function getUsersFromSyncBackend($_filter = NULL, $_sort = NULL, $_dir = 'ASC', $_start = NULL, $_limit = NULL, $_accountClass = 'ModelUser');
    
    /**
     * update user status (enabled or disabled)
     *
     * @param   mixed   $_accountId
     * @param   string  $_status
     */
    public function setStatusInSyncBackend($_accountId, $_status);

    /**
     * sets/unsets expiry date in ldap backend
     *
     * expiryDate is the number of days since Jan 1, 1970
     *
     * @param   mixed      $_accountId
     * @param   Tinebase_DateTime  $_expiryDate
     */
    public function setExpiryDateInSyncBackend($_accountId, $_expiryDate);
    
    /**
     * add an user
     * 
     * @param   FullUser  $_user
     * @return  FullUser
     */
    public function addUserToSyncBackend(FullUser $_user);

    /**
     * updates an existing user
     *
     * @todo check required objectclasses?
     *
     * @param FullUser $_account
     * @return FullUser
     */
    public function updateUserInSyncBackend(FullUser $_account);
    
    /**
     * delete an user in ldap backend
     *
     * @param ModelUser|string|int $_userId
     */
    public function deleteUserInSyncBackend($_userId);
    
    /**
     * return contact information for user
     *
     * @param  FullUser    $_user
     * @param  Addressbook_Model_Contact  $_contact
     */
    public function updateContactFromSyncBackend(FullUser $_user, Addressbook_Model_Contact $_contact);

    /**
     * update contact data(first name, last name, ...) of user
     * 
     * @param Addressbook_Model_Contact $_contact
     */
    public function updateContactInSyncBackend($_contact);
}
