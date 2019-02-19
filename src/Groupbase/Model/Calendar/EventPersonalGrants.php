<?php
namespace Fgsl\Groupware\Groupbase\Model\Calendar;

use Fgsl\Groupware\Groupbase\Model\Grants;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */


/**
 * defines Calendar Event grants for personal containers only
 *
 * @package     Groupware
 * @subpackage  Model
 *
 */
class EventPersonalGrants extends Grants
{
    /**
     * grant to _access_ records marked as private (GRANT_X = GRANT_X * GRANT_PRIVATE)
     */
    const GRANT_PRIVATE = 'privateGrant';
    /**
     * grant to see freebusy info in calendar app
     * @todo move to Calendar_Model_Grant once we are able to cope with app specific grant classes
     */
    const GRANT_FREEBUSY = 'freebusyGrant';

    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Calendar';

    /**
     * get all possible grants
     *
     * @return  array   all container grants
     */
    public static function getAllGrants()
    {
        return array_merge(parent::getAllGrants(), [
            self::GRANT_FREEBUSY,
            self::GRANT_PRIVATE,
        ]);
    }
}