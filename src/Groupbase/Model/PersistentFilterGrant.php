<?php
namespace Fgsl\Groupware\Groupbase\Model;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class PersistentFilterGrant
 * 
 * @package     Gropbase
 * @subpackage  PersistentFilter
 */
class PersistentFilterGrant extends Grants 
{
    /**
     * get all possible grants
     *
     * @return  array   all container grants
     */
    public static function getAllGrants()
    {
        $allGrants = array(
            self::GRANT_READ,
            self::GRANT_EDIT,
            self::GRANT_DELETE,
        );
    
        return $allGrants;
    }
}
