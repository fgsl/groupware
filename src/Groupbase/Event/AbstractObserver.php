<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Event\AbstractEvent;
use Fgsl\Groupware\Groupbase\Record\PersistentObserver;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * base class for all observer events
 *
 * @package     Groupbase
 * @subpackage  Event
 */
abstract class AbstractObserver extends AbstractEvent
{
    /**
     * @var PersistentObserver
     */
    public $persistentObserver;

    /**
     * @var RecordInterface
     */
    public $observable;
}