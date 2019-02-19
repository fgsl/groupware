<?php
namespace Fgsl\Groupware\Groupbase\Container;

use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Fgsl\Groupware\Groupbase\Model\User;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Model\Grants;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 *  interface to handle containers in each application controller or webdav frontends
 *
 * any record in Tine 2.0 is tied to a container. the rights of an account on a record gets
 * calculated by the grants given to this account on the container holding the record (if you know what i mean ;-))
 *
 * @package     Tinebase
 * @subpackage  Container
 *
 * TODO add interface for container models (Tinebase_Model_Container, Tinebase_Model_Tree_Node, ...)
 */
interface ContainerInterface
{
    /**
     * check if the given user user has a certain grant
     *
     * @param   string|User          $_accountId
     * @param   int|RecordInterface        $_containerId
     * @param   array|string                        $_grant
     * @return  boolean
     */
    public function hasGrant($_accountId, $_containerId, $_grant);

    /**
     * return users which made personal containers accessible to given account
     *
     * @param   string|User          $_accountId
     * @param   string|ModelApplication   $recordClass
     * @param   array|string                        $_grant
     * @param   bool                                $_ignoreACL
     * @param   bool                                $_andGrants
     * @return  RecordSet set of User
     */
    public function getOtherUsers($_accountId, $recordClass, $_grant, $_ignoreACL = FALSE, $_andGrants = FALSE);

    /**
     * returns the shared container for a given application accessible by the current user
     *
     * @param   string|User          $_accountId
     * @param   string|ModelApplication   $recordClass
     * @param   array|string                        $_grant
     * @param   bool                                $_ignoreACL
     * @param   bool                                $_andGrants
     * @return  RecordSet set of Tinebase_Model_Container
     * @throws  NotFound
     */
    public function getSharedContainer($_accountId, $recordClass, $_grant, $_ignoreACL = FALSE, $_andGrants = FALSE);

    /**
     * returns the personal container of a given account accessible by a another given account
     *
     * @param   string|User          $_accountId
     * @param   string|RecordInterface    $_recordClass
     * @param   int|User             $_owner
     * @param   array|string                        $_grant
     * @param   bool                                $_ignoreACL
     * @return  RecordSet of subtype Tinebase_Model_Container
     * @throws  NotFound
     */
    public function getPersonalContainer($_accountId, $_recordClass, $_owner, $_grant = Grants::GRANT_READ, $_ignoreACL = false);

    /**
     * return all container, which the user has the requested right for
     *
     * used to get a list of all containers accesssible by the current user
     *
     * @param   string|User          $accountId
     * @param   string|ModelApplication   $recordClass
     * @param   array|string                        $grant
     * @param   bool                                $onlyIds return only ids
     * @param   bool                                $ignoreACL
     * @return  RecordSet|array
     * @throws  NotFound
     */
    public function getContainerByACL($accountId, $recordClass, $grant, $onlyIds = FALSE, $ignoreACL = FALSE);

    /**
     * gets default container of given user for given app
     *  - did and still does return personal first container by using the application name instead of the recordClass name
     *  - allows now to use different models with default container in one application
     *
     * @param   string|RecordInterface $recordClass
     * @param   string|User       $accountId use current user if omitted
     * @param   string                           $defaultContainerPreferenceName
     * @return  RecordInterface
     */
    public function getDefaultContainer($recordClass, $accountId = NULL, $defaultContainerPreferenceName = NULL);
}
