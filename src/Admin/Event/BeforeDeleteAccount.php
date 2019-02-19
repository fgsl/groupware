<?php
namespace Fgsl\Groupware\Admin\Event;
use Fgsl\Groupware\Groupbase\Event\AbstractEvent;
use Fgsl\Groupware\Groupbase\Model\FullUser;

/**
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * event class for deleted accounts (this is fired before the deletion)
 *
 * @package     Admin
 */
class BeforeDeleteAccount extends AbstractEvent
{
    /**
     * the account to be deleted
     *
     * @var FullUser
     */
    public $account;
}