<?php
namespace Fgsl\Groupware\Addressbook\Event;
use Fgsl\Groupware\Groupbase\Event\AbstractEvent;
use Fgsl\Groupware\Addressbook\Model\ModelList;

/**
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * event class for changed list
 *
 * @package     Addressbook
 */
class DeleteList extends AbstractEvent
{
    /**
     * the list object
     *
     * @var ModelList
     */
    public $list;
}