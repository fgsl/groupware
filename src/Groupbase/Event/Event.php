<?php
namespace Fgsl\Groupware\Groupbase\Event;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Psr\Log\LogLevel;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class to handle events between the applications
 *
 * @package     Tinebase
 * @subpackage  Event
 */
class Event
{
    /**
     * keeps a list of currently processed events
     * 
     * @var array
     */
    static public $events = array();
    static protected $history = [];
    
    /**
     * calls the handleEvent function in the controller of all enabled applications 
     *
     * @param  AbstractEvent  $_eventObject  the event object
     */
    static public function fireEvent(AbstractEvent $_eventObject)
    {
        self::$events[get_class($_eventObject)][$_eventObject->getId()] = $_eventObject;
        $historyOffset = count(static::$history);
        static::$history[$historyOffset] = ['event' => $_eventObject];
        
        if (self::isDuplicateEvent($_eventObject)) {
            // do nothing
            return;
        }
        
        foreach (Application::getInstance()->getApplicationsByState(Application::ENABLED) as $application) {
            try {
                $controller = Core::getApplicationInstance($application, NULL, TRUE);
            } catch (NotFound $e) {
                // application has no controller or is not useable at all
                continue;
            }
            if ($controller instanceof EventInterface) {
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . ' '
                    . __LINE__ . ' calling eventhandler for event ' . get_class($_eventObject) . ' of application ' . (string) $application);
                static::$history[$historyOffset][$application->getId()] = true;
                try {
                    $controller->handleEvent($_eventObject);
                } catch (\Exception $e) {
                    if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . ' '
                        . __LINE__ . ' ' . (string) $application . ' threw an exception: '
                        . $e->getMessage()
                    );
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . ' '
                        . __LINE__ . ' ' . $e->getTraceAsString());
                }
            }
        }
        
        // try custom user defined listeners
        try {
            if (@class_exists('CustomEventHooks')) {
                $methods = get_class_methods('CustomEventHooks');
                if (in_array('handleEvent', (array)$methods)) {
                    Core::getLogger()->info(__METHOD__ . ' ' . __LINE__ . ' ' . ' about to process user defined event hook for '. get_class($_eventObject));
                    call_user_func(['CustomEventHooks','handleEvent'],$_eventObject);
                }
            }
        } catch (\Exception $e) {
            Core::getLogger()->info(__METHOD__ . ' ' . __LINE__ . ' ' . ' failed to process user defined event hook with message: ' . $e);
        }
        
        unset(self::$events[get_class($_eventObject)][$_eventObject->getId()]);
    }
    
    /**
     * checks if an event is duplicate
     * 
     * @todo   implement logic
     * @param  AbstractEvent  $_eventObject  the event object
     * @return boolean
     */
    static public function isDuplicateEvent(AbstractEvent $_eventObject)
    {
        return false;
    }

    static public function reFireForNewApplications()
    {
        foreach (static::$history as $data) {
            $event = $data['event'];
            foreach (Application::getInstance()->getApplicationsByState(Application::ENABLED) as $application) {
                try {
                    $controller = Core::getApplicationInstance($application, NULL, TRUE);
                } catch (NotFound $e) {
                    // application has no controller or is not useable at all
                    continue;
                }
                if (isset($data[$application->getId()])) {
                    continue;
                }
                if ($controller instanceof EventInterface) {
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . ' '
                        . __LINE__ . ' calling eventhandler for event ' . get_class($event) . ' of application ' . (string) $application);

                    try {
                        $controller->handleEvent($event);
                    } catch (\Exception $e) {
                        if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . ' '
                            . __LINE__ . ' ' . (string) $application . ' threw an exception: '
                            . $e->getMessage()
                        );
                        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . ' '
                            . __LINE__ . ' ' . $e->getTraceAsString());
                    }
                }
            }
        }
    }
}
