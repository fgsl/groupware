<?php
/**
 * Tine 2.0
*
* @package     Custom
* @subpackage  Expressomail
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @copyright   Copyright (c) 2008-2014 Metaways Infosystems GmbH (http://www.metaways.de)
* @copyright   Copyright (c) 2014 Serpro (http://www.serpro.gov.br)
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
* @deprecated  This class must be removed after #14130 go to production and ActiveSync proves that is stable
*/
/**
 * Custom strategy for Expressomail ActiveSync frontend class
*
* @package     Custom
* @subpackage  Expressomail
*/
class Custom_Expressomail_Frontend_ActiveSync_FormerStrategy implements Expressomail_Frontend_ActiveSync_Strategy_Interface
{
    /**
     *
     * @var Expressomail_Frontend_ActiveSync
     */
    private static $_frontend = NULL;

    /**
     * @param array $source
     * @param string $inputStream
     * @param bool $saveInSent
     * @param bool $replaceMime
     * @param Expressomail_Frontend_ActiveSync $_frontend
     * @see Syncroton_Data_IDataEmail::forwardEmail()
     */
    public static function forwardEmail($source, $inputStream, $saveInSent, $replaceMime, $frontend)
    {
        self::$_frontend = $_frontend;
        $messageId = self::$_frontend->getMessageIdFromSource($source);

        if (! is_resource($inputStream)) {
            $stream = fopen("php://temp", 'r+');
            fwrite($stream, $inputStream);
            $inputStream = $stream;
            rewind($inputStream);
        }

        if (self::$_frontend->debugEmail() == true) {
            $debugStream = fopen("php://temp", 'r+');
            stream_copy_to_stream($inputStream, $debugStream);
            rewind($debugStream);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                __METHOD__ . '::' . __LINE__ . " email to send:" . stream_get_contents($debugStream));

            // replace original stream with debug stream, as php://input can't be rewinded
            $inputStream = $debugStream;
            rewind($inputStream);
        }

        $incomingMessage = new Zend_Mail_Message(
            array(
                'file' => $inputStream
            )
        );

        if (! $incomingMessage->isMultipart()) {
            self::parseAndSendMessage($messageId, $incomingMessage, Zend_Mail_Storage::FLAG_PASSED);
            return;
        }

        $defaultAccountId = Tinebase_Core::getPreference('Expressomail')->{Expressomail_Preference::DEFAULTACCOUNT};

        try {
            $account = Expressomail_Controller_Account::getInstance()->get($defaultAccountId);
        } catch (Tinebase_Exception_NotFound $ten) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " no email account configured");
            throw new Syncroton_Exception('no email account configured');
        }

        if(empty(Tinebase_Core::getUser()->accountEmailAddress)) {
            throw new Syncroton_Exception('no email address set for current user');
        }

        $fmailMessage = Expressomail_Controller_Message::getInstance()->get($messageId);
        $fmailMessage->flags = Zend_Mail_Storage::FLAG_PASSED;

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
            __METHOD__ . '::' . __LINE__ . " source: " . $messageId . "saveInSent: " . $saveInSent);

        if ($replaceMime === FALSE) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                __METHOD__ . '::' . __LINE__ . " Adding RFC822 attachment and appending body to forward message.");

            $rfc822 = Expressomail_Controller_Message::getInstance()->getMessagePart($fmailMessage);
            $rfc822->type = Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822;
            $rfc822->filename = 'forwarded_email.eml';
            $rfc822->encoding = Zend_Mime::ENCODING_7BIT;
            $replyBody = Expressomail_Controller_Message::getInstance()->getMessageBody($fmailMessage, NULL, 'text/plain');
        } else {
            $rfc822 = NULL;
            $replyBody = NULL;
        }

        $mail = Tinebase_Mail::createFromZMM($incomingMessage, $replyBody);
        if ($rfc822) {
            $mail->addAttachment($rfc822);
        }

        Expressomail_Controller_Message_Send::getInstance()->sendZendMail($account, $mail, $saveInSent, $fmailMessage);
    }

    /**
     * Reply/Forward message with special treatment
     *
     * @param string $messageId
     * @param Zend_Mail_Message $incomingMessage
     * @param string $flag
     * @throws Zend_Mail_Protocol_Exception
     */
    public static function parseAndSendMessage($messageId, $incomingMessage, $flag = NULL)
    {
        $originalMessage = Expressomail_Controller_Message::getInstance()->getCompleteMessage($messageId, null, false);

        $user = Tinebase_Core::getUser();

        $headers = $incomingMessage->getHeaders();

        $body = ($headers['content-transfer-encoding'] == 'base64')
        ? base64_decode($incomingMessage->getContent())
        : $incomingMessage->getContent();
        $isTextPlain = strpos($headers['content-type'],'text/plain');
        $bodyLines = preg_split('/\r\n|\r|\n/', $body);
        $body = '';
        if ($isTextPlain !== false) {
            foreach ($bodyLines as &$line) {
                $body .= htmlentities($line) . '<br />';
            }
        } else {
            foreach ($bodyLines as &$line) {
                $body .= $line . '<br />';
            }
        }
        $body = '<div>' . $body . '</div>';

        $bodyOrigin = $originalMessage['body'];
        preg_match("/<body[^>]*>(.*?)<\/body>/is", $bodyOrigin, $matches);
        $bodyOrigin = (count($matches)>1) ? $matches[1] : $bodyOrigin;
        $body .= '<div>' . $bodyOrigin . '</div>';

        $attachments = array();
        foreach ($originalMessage['attachments'] as &$att) {
            try {
                $att['name'] = $att['filename'];
                $att['type'] = $att['content-type'];
            } catch (Exception $e) {
                Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' Could not get some attachment attributes for message: ' . $messageId);
            }
            array_push($attachments, $att);
        }

        $recordData = array();
        $recordData['note'] = '';
        $recordData['content_type'] = 'text/html';
        $recordData['account_id'] = $originalMessage->account_id;
        $recordData['to'] = is_array($headers['to']) ? $headers['to'] : array($headers['to']);
        $recordData['cc'] = array();
        $recordData['bcc'] = array();
        $recordData['subject'] = $headers['subject'];
        $recordData['body'] = $body;
        //$recordData['flags'] = array_merge($incomingMessage->getFlags(), $originalMessage['flags']);
        $recordData['flags'] = ($flag != NULL) ? $flag : '';
        $recordData['original_id'] = $messageId;
        $recordData['embedded_images'] = array();
        $recordData['attachments'] = $attachments;
        $recordData['from_email'] = $user->accountEmailAddress;
        $recordData['from_name'] = $user->accountFullName;
        $recordData['customfields'] = array();

        $message = new Expressomail_Model_Message();
        $message->setFromJsonInUsersTimezone($recordData);

        try {
            Expressomail_Controller_Message_Send::getInstance()->sendMessage($message);
        } catch (Zend_Mail_Protocol_Exception $zmpe) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Could not send message: ' . $zmpe->getMessage());
            throw $zmpe;
        }
    }

    /**
     * @param array $source
     * @param string $inputStream
     * @param bool $saveInSent
     * @param bool $replaceMime
     * @param Expressomail_Frontend_ActiveSync $_frontend
     * @see Syncroton_Data_IDataEmail::replyEmail()
     */
    public static function replyEmail($source, $inputStream, $saveInSent, $replaceMime, $frontend)
    {
        self::$_frontend = $frontend;
        $messageId = self::$_frontend->getMessageIdFromSource($source);

        if (! is_resource($inputStream)) {
            $stream = fopen("php://temp", 'r+');
            fwrite($stream, $inputStream);
            $inputStream = $stream;
            rewind($inputStream);
        }

        if (self::$_frontend->debugEmail() == true) {
            $debugStream = fopen("php://temp", 'r+');
            stream_copy_to_stream($inputStream, $debugStream);
            rewind($debugStream);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                __METHOD__ . '::' . __LINE__ . " email to send:" . stream_get_contents($debugStream));

            //replace original stream wirh debug stream, as php://input can't be rewinded
            $inputStream = $debugStream;
            rewind($inputStream);
        }

        $incomingMessage = new Zend_Mail_Message(
            array(
                'file' => $inputStream
            )
        );

        if (! $incomingMessage->isMultipart()) {
            self::parseAndSendMessage($messageId, $incomingMessage, Zend_Mail_Storage::FLAG_ANSWERED);
            return;
        }

        $defaultAccountId = Tinebase_Core::getPreference('Expressomail')->{Expressomail_Preference::DEFAULTACCOUNT};

        try {
            $account = Expressomail_Controller_Account::getInstance()->get($defaultAccountId);
        } catch (Tinebase_Exception_NotFound $ten) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " no email account configured");
            throw new Syncroton_Exception('no email account configured');
        }

        if (empty(Tinebase_Core::getUser()->accountEmailAddress)) {
            throw new Syncroton_Exception('no email address set for current user');
        }

        $fmailMessage = Expressomail_Controller_Message::getInstance()->get($messageId);
        $fmailMessage->flags = Zend_Mail_Storage::FLAG_ANSWERED;

        if ($replaceMime === false) {
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Adding RFC822 attachment and appending body to forward message.");

            $rfc822 = Expressomail_Controller_Message::getInstance()->getMessagePart($fmailMessage);
            $rfc822->type = Expressomail_Model_Message::CONTENT_TYPE_MESSAGE_RFC822;
            $rfc822->filename = 'replied_email.eml';
            $rfc822->encoding = Zend_Mime::ENCODING_7BIT;
            $replyBody = Expressomail_Controller_Message::getInstance()->getMessageBody($fmailMessage, null, 'text/plain');
        } else {
            $rfc822 = null;
            $replyBody = null;
        }

        $mail = Tinebase_Mail::createFromZMM($incomingMessage, $replyBody);
        if ($rfc822) {
            $mail->addAttachment($rfc822);
        }

        Expressomail_Controller_Message_Send::getInstance()->sendZendMail($account, $mail, (bool)$saveInSent, $fmailMessage);
    }
}