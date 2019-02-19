<?php
namespace Fgsl\Groupware\Groupbase\Notification;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Notification\Backend\Smtp as BackendSmtp;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Notification factory class
 * 
 * this class is responsible for returning the right notification backend
 *
 * @package     Groupbase
 * @subpackage  Notification
 */
class NotificationFactory
{
    /**
     * smtp backend type
     *
     * @staticvar string
     */
    const SMTP = 'Smtp';
    
    /**
     * return a instance of the current accounts backend
     *
     * @return  NotificationInterface
     * @throws  InvalidArgument
     */
    public static function getBackend($_backendType) 
    {
        switch($_backendType) {
            case self::SMTP:
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Get SMTP notification backend.');
                $result = new BackendSmtp();
                break;
                
            default:
                throw new InvalidArgument("Notification backend type $_backendType not implemented");
        }
        
        return $result;
    }
}
