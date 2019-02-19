<?php
namespace Fgsl\Groupware\Groupbase\Notification\Backend;
use Fgsl\Groupware\Groupbase\Notification\NotificationInterface;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Model\FullUser;
use Fgsl\Groupware\Addressbook\Model\Contact;
use Fgsl\Groupware\Groupbase\Mail\Mail;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Config\Struct;
use Zend\Mime\Part;
use Zend\Mime\Mime;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Smtp as GroupbaseSmtp;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * notifications smtp backend class
 *
 * @package     Groupbase
 * @subpackage  Notification
 */
class Smtp implements NotificationInterface{
    /**
     * the from address
     *
     * @var string
     */
    protected $_fromAddress;
    
    /**
     * the sender name
     *
     * @var string
     */
    protected $_fromName = 'Groupware notification service';
    
    /**
     * the constructor
     *
     */
    public function __construct()
    {
        $smtpConfig = Config::getInstance()->get(Config::SMTP, new Struct(array()))->toArray();
        $this->_fromAddress = (isset($smtpConfig['from']) && ! empty($smtpConfig['from'])) ? $smtpConfig['from'] : '';
        
        // try to sanitize sender address
        if (empty($this->_fromAddress) && isset($smtpConfig['primarydomain']) && ! empty($smtpConfig['primarydomain'])) {
            $this->_fromAddress = 'noreply@' . $smtpConfig['primarydomain'];
        }
    }
    
    /**
     * send a notification as email
     *
     * @param FullUser                  $_updater
     * @param Contact $_recipient
     * @param string                    $_subject the subject
     * @param string                    $_messagePlain the message as plain text
     * @param string                    $_messageHtml the message as html
     * @param string|array              $_attachments
     */
    public function send($_updater, Contact $_recipient, $_subject, $_messagePlain, $_messageHtml = NULL, $_attachments = NULL)
    {
        // create mail object
        $mail = new Mail('UTF-8');
        // this seems to break some subjects, removing it for the moment 
        // -> see 0004070: sometimes we can't decode message subjects (calendar notifications?)
        //$mail->setHeaderEncoding(Mime::ENCODING_BASE64);
        $mail->setSubject($_subject);
        $mail->setBodyText($_messagePlain);
        
        if($_messageHtml !== NULL) {
            $mail->setBodyHtml($_messageHtml);
        }
        
        // add header to identify mails sent by notification service / don't reply to this mail, dear autoresponder ... :)
        $mail->addHeader('X-Tine20-Type', 'Notification');
        $mail->addHeader('Precedence', 'bulk');
        $mail->addHeader('User-Agent', Core::getTineUserAgent('Notification Service'));
        
        if (empty($this->_fromAddress)) {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' No notification service address set. Could not send notification.');
            return;
        }
        
        if($_updater !== NULL && ! empty($_updater->accountEmailAddress)) {
            $mail->setFrom($_updater->accountEmailAddress, $_updater->accountFullName);
            $mail->setSender($this->_fromAddress, $this->_fromName);
        } else {
            $mail->setFrom($this->_fromAddress, $this->_fromName);
        }
        
        // attachments
        if (is_array($_attachments)) {
            $attachments = &$_attachments;
        } elseif (is_string($_attachments)) {
            $attachments = array(&$_attachments);
        } else {
            $attachments = array();
        }
        foreach ($attachments as $attachment) {
            if ($attachment instanceof Part) {
                $mail->addAttachment($attachment);
            } else if (isset($attachment['filename'])) {
                $mail->createAttachment(
                    $attachment['rawdata'], 
                    Mime::TYPE_OCTETSTREAM,
                    Mime::DISPOSITION_ATTACHMENT,
                    Mime::ENCODING_BASE64,
                    $attachment['filename']
                );
            } else {
                $mail->createAttachment($attachment);
            }
        }
        
        // send
        if(! empty($_recipient->email)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Send notification email to ' . $_recipient->email);
            $mail->addTo($_recipient->email, $_recipient->n_fn);
            GroupbaseSmtp::getInstance()->sendMessage($mail);
        } else {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ 
                . ' Not sending notification email to ' . $_recipient->n_fn . '. No email address available.');
        }
    }
}
