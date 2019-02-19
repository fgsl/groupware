<?php
namespace Fgsl\Groupware\Admin\Event;
use Fgsl\Groupware\Groupbase\Event\AbstractEvent;

/**
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * event class for deleted groups (this is fired before the deletion)
 *
 */
class BeforeDeleteGroup extends AbstractEvent
{
    /**
     * array of groupids
     *
     * @var array
     */
    public $groupIds;
}