<?php
namespace Fgsl\Groupware\Admin\Event;
use Fgsl\Groupware\Groupbase\Event\AbstractEvent;
use Fgsl\Groupware\Groupbase\Model\Group;

/**
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * event class for updated group
 *
 */
class UpdateGroup extends AbstractEvent
{
    /**
     * the group object
     *
     * @var Group
     */
    public $group;

}