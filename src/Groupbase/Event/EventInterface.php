<?php
namespace Fgsl\Groupware\Groupbase\Event;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * interface for all classes which can handle events
 *
 * @package     Tinebase
 * @subpackage  Event
 */
interface EventInterface
{
    /**
     * this functions handles the events
     *
     * @param AbstractEvent $_eventObject the eventobject
     */
    public function handleEvent(AbstractEvent $_eventObject);
    
    /**
     * suspend processing of event
     */
    public function suspendEvents();

    /**
     * resume processing of events
     */
    public function resumeEvents();
}
