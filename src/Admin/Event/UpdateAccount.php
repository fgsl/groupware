<?php
namespace Fgsl\Groupware\Groupbase\Event;
use Fgsl\Groupware\Groupbase\Model\FullUser;

/**
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * event class for updated account
 *
 * @package     Admin
 * @subpackage  Event
 */
class UpdateAccount extends AbstractEvent
{
    /**
     * the updated account
     *
     * @var FullUser
     */
    public $account;

    /**
     * the old account
     *
     * @var FullUser
     */
    public $oldAccount;
}
