<?php
namespace Fgsl\Groupware\Groupbase\Controller;
use Fgsl\Groupware\Groupbase\Model\Alarm;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * alarms controller interface
 *  
 * @package     Groupbase
 * @subpackage  Alarm
 */
interface AlarmInterface
{
    /**
     * sendAlarm - send an alarm and update alarm status/sent_time/...
     *
     * @param  Alarm $_alarm
     * @return Alarm
     */
    public function sendAlarm(Alarm $_alarm);
    
}
