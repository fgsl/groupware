<?php
namespace Fgsl\Groupware\Groupbase\Notification;
use Fgsl\Groupware\Groupbase\Model\FullUser;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Addressbook\Backend\BackendFactory;
use Fgsl\Groupware\Addressbook\Model\Contact;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * primary class to handle notifications
 *
 * @package     Groupbase
 * @subpackage  Notification
 */
class Notification
{
    protected $_smtpBackend;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() {
        $this->_smtpBackend = NotificationFactory::getBackend(NotificationFactory::SMTP);
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}

    /**
     * holds the instance of the singleton
     *
     * @var Notification
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return Notification
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Notification();
        }
        
        return self::$_instance;
    }

    public static function destroyInstance()
    {
        self::$_instance = null;
    }
    
    /**
     * send notifications to a list a recipients
     *
     * @param FullUser                  $_updater
     * @param array                     $_recipients array of int|Contact
     * @param string                    $_subject
     * @param string                    $_messagePlain
     * @param string                    $_messageHtml
     * @param string|array              $_attachments
     * @throws Exception
     * 
     * @todo improve exception handling: collect all messages / exceptions / failed email addresses / ...
     */
    public function send($_updater, $_recipients, $_subject, $_messagePlain, $_messageHtml = NULL, $_attachments = NULL)
    {
        $contactsBackend = BackendFactory::factory(BackendFactory::SQL);
        
        $exception = NULL;
        $sentContactIds = array();
        foreach ($_recipients as $recipient) {
            try {
                if (! $recipient instanceof Contact) {
                    $recipient = $contactsBackend->get($recipient);
                }
                if (! in_array($recipient->getId(), $sentContactIds)) {
                    $this->_smtpBackend->send($_updater, $recipient, $_subject, $_messagePlain, $_messageHtml, $_attachments);
                    $sentContactIds[] = $recipient->getId();
                }
            } catch (Exception $e) {
                $exception = $e;
                if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                    . ' Failed to send notification message (recipient: '
                    . ($recipient instanceof Contact ? $recipient->email : $recipient) . '. Exception: ' . $e);
            }
        }
        
        if ($exception !== NULL) {
            // throw exception in the end when all recipients have been processed
            throw $exception;
        }
    }
}
