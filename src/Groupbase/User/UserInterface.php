<?php
namespace Fgsl\Groupware\Groupbase\User;

use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Model\FullUser;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * abstract class for all user backends
 *
 * @package     Groupbase
 * @subpackage  User
 */
 
interface UserInterface
{
    /**
     * get plugins
     * 
     * return array
     */
    public function getPlugins();
    
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
    public function getUsers($_filter = NULL, $_sort = NULL, $_dir = 'ASC', $_start = NULL, $_limit = NULL, $_accountClass = 'ModelUser');
    
    /**
     * get user by property
     *
     * @param   string  $_property
     * @param   string  $_value
     * @param   string  $_accountClass  type of model to return
     * @return  ModelUser user
     */
    public function getUserByProperty($_property, $_value, $_accountClass = 'ModelUser');
    
    /**
     * register plugins
     * 
     * @param PluginInterface $_plugin
     */
    public function registerPlugin(PluginInterface $_plugin);
    
    /**
     * increase bad password counter and store last login failure timestamp if user exists
     * 
     * @param string $_loginName
     * @return  FullUser user
     */
    public function setLastLoginFailure($_loginName);

    /**
     * count user accounts (non-system)
     *
     * @return integer
     */
    public function countNonSystemUsers();

    /**
     * get user by property from backend
     *
     * @param   string  $_property      the key to filter
     * @param   string  $_value         the value to search for
     * @param   string  $_accountClass  type of model to return
     *
     * @return  ModelUser the user object
     */
    public function getUserByPropertyFromBackend($_property, $_value, $_accountClass = 'ModelUser');
}
