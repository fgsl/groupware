<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * acl filter interface
 * 
 * A ACL filter constrict the results of a filter group based on the required
 * grants needed by the current user.
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
interface AclFilter
{
    /**
     * sets the grants this filter needs to assure
     *
     * @param array $_grants
     */
    public function setRequiredGrants(array $_grants);

    /**
     * add a callback that should be called once the required grants are set
     *
     * @param $_callback
     *
    public function addSetRequiredGrantsCallback($_callback);*/
}
