<?php
namespace Fgsl\Groupware\Groupbase\Mail;

use Zend\Mail as ZendMail;
use Zend\Mail\Message;
use Zend\Mime\Part;
use Zend\Mime\Mime;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Zend\Mime\Decode;
use Fgsl\Groupware\Mail\Message as MailMessage;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Helper;
use Fgsl\Groupware\Groupbase\Config\Config;
/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * This class extends the ZendMail class 
 *
 * @package     Tinebase
 * @subpackage  Mail
 */
class Mail extends ZendMail
{
    /**
    * email address regexp
    */
    const EMAIL_ADDRESS_REGEXP = '/^([a-z0-9_\+-\.&]+@[a-z0-9-\.]+\.[a-z]{2,63})$/i';

    /**
     * email address regexp (which might be contained in a longer text)
     */
    const EMAIL_ADDRESS_CONTAINED_REGEXP = '/([a-z0-9_\+-\.&]+@[a-z0-9-\.]+\.[a-z]{2,63})/i';

    /**
     * Sender: address
     * @var string
     */
    protected $_sender = null;
    
    /**
     * fallback charset constant
     * 
     * @var string
     */
    const DEFAULT_FALLBACK_CHARSET = 'iso-8859-15';
    
    /**
     * create Mail from Message
     * 
     * @param  Message  $_zmm
     * @param  string             $_replyBody
     * @return Mail
     */
    public static function createFromZMM(Message $_zmm, $_replyBody = null, $_signature = null)
    {
        if (empty($_signature)) {
           $content = $_zmm->getContent();
        }
        else {
           $content = self::_getZMMContentWithSignature($_zmm, $_signature);
        }
        $contentStream = fopen("php://temp", 'r+');
        fputs($contentStream, $content);
        rewind($contentStream);
        
        $mp = new Part($contentStream);
        self::_getMetaDataFromZMM($_zmm, $mp);
        
        // append old body when no multipart/mixed
        if ($_replyBody !== null && $_zmm->headerExists('content-transfer-encoding')) {
            $mp = self::_appendReplyBody($mp, $_replyBody);
        } else {
            $mp->decodeContent();
            if ($_zmm->headerExists('content-transfer-encoding')) {
                switch ($_zmm->getHeader('content-transfer-encoding')) {
                    case Mime::ENCODING_BASE64:
                        // BASE64 encode has a bug that swallows the last char(s)
                        $bodyEncoding = Mime::ENCODING_7BIT;
                        break;
                    default: 
                        $bodyEncoding = $_zmm->getHeader('content-transfer-encoding');
                }
            } else {
                $bodyEncoding = Mime::ENCODING_7BIT;
            }
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Using encoding: ' . $bodyEncoding);
            $mp->encoding = $bodyEncoding;
        }
        
        $result = new Mail('utf-8');
        $result->setBodyText($mp);
        $result->setHeadersFromZMM($_zmm);
        
        return $result;
    }
    
    /**
     * get content from Message with attached mail signature
     * 
     * @param  Message  $zmm
     * @param  string             $signature
     * @return string
     */
    protected static function _getZMMContentWithSignature(Message $zmm, $signature)
    {
        if (stripos($zmm->contentType, 'multipart/') === 0) {
            // Multipart message
            $zmm->rewind();
            $boundary = $zmm->getHeaderField('content-type', 'boundary');
            $rawHeaders = [];
            foreach (Decode::splitMime($zmm->getContent(), $boundary) as $mimePart) {
                $mimePart = str_replace("\r", '', $mimePart);
                array_push($rawHeaders, substr($mimePart, 0, strpos($mimePart, "\n\n")));
            }
            $content = '';
            for ($num = 1; $num <= $zmm->countParts(); $num++) {
                $zmp = $zmm->getPart($num);
                $content .= "\r\n--" . $boundary . "\r\n";
                $content .= $rawHeaders[$num-1] . "\r\n\r\n";
                $content .= self::_getPartContentWithSignature($zmp, $signature);
            }
            $content .= "\r\n--" . $boundary . "--\r\n";
            return $content;
        }
        else {
            $content = self::_getPartContentWithSignature($zmm, $signature);
        }
        return $content;
    }
    
    /**
     * get content from ZendMail_Part with attached mail signature
     * 
     * @param  Part            $zmp
     * @param  string          $signature
     * @return string
     */
    public static function _getPartContentWithSignature($zmp, $signature)
    {
        $contentType = $zmp->getHeaderField('content-type', 0);
        if (($contentType != 'text/html') && ($contentType != 'text/plain')) {
            // Modify text parts only
            return $zmp->getContent();
        }
        if (($zmp->headerExists('Content-Disposition')) && (stripos($zmp->contentDisposition, 'attachment;') === 0)) {
            // Do not modify attachment
            return $zmp->getContent();
        }
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Attaching signature to ' . $contentType . ' mime part');
        $content = $zmp->getContent();
        if ($zmp->contentTransferEncoding == Mime::ENCODING_BASE64) {
            $content = base64_decode($content);
        }
        else if ($zmp->contentTransferEncoding == Mime::ENCODING_QUOTEDPRINTABLE) {
            $content = quoted_printable_decode($content);
        }
        if ($contentType == "text/html") {
            $signature = "<br />&minus;&minus;<br />" . $signature;
        }
        else {
            $signature = MailMessage::convertFromHTMLToText($signature, "\n");
            $signature = "\n--\n" . $signature;
        }
        $content .= $signature;
        return Mime::encode($content, $zmp->contentTransferEncoding);
    }

    /**
     * get meta data (like contentype, charset, ...) from zmm and set it in zmp
     * 
     * @param Message $zmm
     * @param Part $zmp
     */
    protected static function _getMetaDataFromZMM(Message $zmm, Part $zmp)
    {
        if ($zmm->headerExists('content-transfer-encoding')) {
            $zmp->encoding = $zmm->getHeader('content-transfer-encoding');
        } else {
            $zmp->encoding = Mime::ENCODING_7BIT;
        }
        
        if ($zmm->headerExists('content-type')) {
            $contentTypeHeader = Decode::splitHeaderField($zmm->getHeader('content-type'));
            
            $zmp->type = $contentTypeHeader[0];
            
            if (isset($contentTypeHeader['boundary'])) {
                $zmp->boundary = $contentTypeHeader['boundary'];
            }
            
            if (isset($contentTypeHeader['charset'])) {
                $zmp->charset = $contentTypeHeader['charset'];
            }
        } else {
            $zmp->type = Mime::TYPE_TEXT;
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Encoding: ' . $zmp->encoding . ' / type: ' . $zmp->type . ' / charset: ' . $zmp->charset);
    }
    
    /**
     * appends old body to mime part
     * 
     * @param Part $mp
     * @param string $replyBody plain/text reply body
     * @return Part
     */
    protected static function _appendReplyBody(Part $mp, $replyBody)
    {
        $decodedContent = Mail::getDecodedContent($mp, NULL, FALSE);
        $type = $mp->type;
        
        if (Core::isLogLevel(LogLevel::TRACE)) {
            Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " mp content: " . $decodedContent);
            Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " reply body: " . $replyBody);
        }
        
        if ($type === Mime::TYPE_HTML && /* checks if $replyBody does not contains tags */ $replyBody === strip_tags($replyBody)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " Converting plain/text reply body to HTML");
            $replyBody = self::convertFromTextToHTML($replyBody);
        }
        
        if ($type === Mime::TYPE_HTML && preg_match('/(<\/body>[\s\r\n]*<\/html>)/i', $decodedContent, $matches)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Appending reply body to html body.');
            
            $decodedContent = str_replace($matches[1], $replyBody . $matches[1], $decodedContent);
        } else {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " Appending reply body to mime text part.");
            
            $decodedContent .= $replyBody;
        }
        
        $mp = new Part($decodedContent);
        $mp->charset = 'utf-8';
        $mp->type = $type;
        
        return $mp;
    }
    
    /**
     * Sets the HTML body for the message
     *
     * @param  string|Part    $html
     * @param  string    $charset
     *  @param  string    $encoding
     * @return ZendMail Provides fluent interface
     */
    public function setBodyHtml($html, $charset = null, $encoding = Mime::ENCODING_QUOTEDPRINTABLE)
    {
        if ($html instanceof Part) {
            $mp = $html;
        } else {
            if ($charset === null) {
                $charset = $this->_charset;
            }
        
            $mp = new Part($html);
            $mp->encoding = $encoding;
            $mp->type = Mime::TYPE_HTML;
            $mp->disposition = Mime::DISPOSITION_INLINE;
            $mp->charset = $charset;
        }
        
        $this->_bodyHtml = $mp;
    
        return $this;
    }
    
    /**
     * Sets the text body for the message.
     *
     * @param  string|Part $txt
     * @param  string $charset
     * @param  string $encoding
     * @return ZendMail Provides fluent interface
    */
    public function setBodyText($txt, $charset = null, $encoding = Mime::ENCODING_QUOTEDPRINTABLE)
    {
        if ($txt instanceof Part) {
            $mp = $txt;
        } else {
            if ($charset === null) {
                $charset = $this->_charset;
            }
    
            $mp = new Part($txt);
            $mp->encoding = $encoding;
            $mp->type = Mime::TYPE_TEXT;
            $mp->disposition = Mime::DISPOSITION_INLINE;
            $mp->charset = $charset;
        }
        
        $this->_bodyText = $mp;

        return $this;
    }

    public function setBodyPGPMime($amored)
    {
        $this->_type = 'multipart/encrypted; protocol="application/pgp-encrypted"';

        // PGP/MIME Versions Identification
        $pgpIdent = new Part('Version: 1');
        $pgpIdent->encoding = '7bit';
        $pgpIdent->type = 'application/pgp-encrypted';
        $pgpIdent->description = 'PGP/MIME Versions Identification';
        $this->_bodyText = $pgpIdent;

        // OpenPGP encrypted message
        $pgpMessage = new Part($amored);
        $pgpMessage->encoding = '7bit';
        $pgpMessage->disposition = 'inline; filename=encrypted.asc';
        $pgpMessage->type = 'application/octet-stream; name=encrypted.asc';
        $pgpMessage->description = 'OpenPGP encrypted message';
        $this->_bodyHtml = $pgpMessage;
    }

    /**
     * set headers
     * 
     * @param Message $_zmm
     * @return ZendMail Provides fluent interface
     */
    public function setHeadersFromZMM(Message $_zmm)
    {
        foreach ($_zmm->getHeaders() as $header => $values) {
            foreach ((array)$values as $value) {
                switch ($header) {
                    case 'content-transfer-encoding':
                    // these are implicitly set by ZendMail_Transport_Abstract::_getHeaders()
                    case 'content-type':
                    case 'mime-version':
                        // do nothing
                        break;
                        
                    case 'bcc':
                        $addresses = self::parseAdresslist($value);
                        foreach ($addresses as $address) {
                            $this->addBcc($address['address'], $address['name']);
                        }
                        break;
                        
                    case 'cc':
                        $addresses = self::parseAdresslist($value);
                        foreach ($addresses as $address) {
                            $this->addCc($address['address'], $address['name']);
                        }
                        break;
                        
                    case 'date':
                        try {
                            $this->setDate($value);
                        } catch (\Exception $zme) {
                            if (Core::isLogLevel(LogLevel::NOTICE))
                            {
                                Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " Could not set date: " . $value);
                                Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " " . $zme);
                            }
                            $this->setDate();
                        }
                        break;
                        
                    case 'from':
                        $addresses = self::parseAdresslist($value);
                        foreach ($addresses as $address) {
                            $this->setFrom($address['address'], $address['name']);
                        }
                        break;
                        
                    case 'message-id':
                        $this->setMessageId(trim($value,"<>"));
                        break;
                        
                    case 'return-path':
                        $this->setReturnPath($value);
                        break;
                        
                    case 'subject':
                        $this->setSubject($value);
                        break;
                        
                    case 'to':
                        $addresses = self::parseAdresslist($value);
                        foreach ($addresses as $address) {
                            $this->addTo($address['address'], $address['name']);
                        }
                        break;

                    case 'reply-to':
                        $this->setReplyTo($value);
                        break;

                    default:
                        $this->addHeader($header, $value);
                        break;
                }
            }
        }
        
        return $this;
    }

    /**
     * Sets Sender-header and sender of the message
     *
     * @param  string    $email
     * @param  string    $name
     * @return ZendMail Provides fluent interface
     * @throws \Exception if called subsequent times
     */
    public function setSender($email, $name = '')
    {
        if ($this->_sender === null) {
            $email = strtr($email,"\r\n\t",'???');
            $this->_from = $email;
            $this->_storeHeader('Sender', $this->_encodeHeader('"'.$name.'"').' <'.$email.'>', true);
        } else {
            throw new \Exception('Sender Header set twice');
        }
        return $this;
    }
    
    /**
     * Formats e-mail address
     * 
     * NOTE: we always add quotes to the name as this caused problems when name is encoded
     * @see ZendMail::_formatAddress
     *
     * @param string $email
     * @param string $name
     * @return string
     */
    protected function _formatAddress($email, $name)
    {
        if ($name === '' || $name === null || $name === $email) {
            return $email;
        } else {
            $encodedName = $this->_encodeHeader($name);
            $format = '"%s" <%s>';
            return sprintf($format, $encodedName, $email);
        }
    }

    /**
     * check if Message is/contains calendar iMIP message
     * 
     * @param Message $zmm
     * @return boolean
     */
    public static function isiMIPMail(Message $zmm)
    {
        foreach ($zmm as $part) {
            if (preg_match('/text\/calendar/', $part->contentType)) {
                return TRUE;
            }
        }
        
        return FALSE;
    }
    
    /**
     * get decoded body content
     * 
     * @param Part $zmp
     * @param array $partStructure
     * @param boolean $appendCharsetFilter
     * @return string
     */
    public static function getDecodedContent(Part $zmp, $_partStructure = NULL, $appendCharsetFilter = TRUE)
    {
        $charset = self::_getCharset($zmp, $_partStructure);
        if ($appendCharsetFilter) {
            $charset = self::_appendCharsetFilter($zmp, $charset);
        }
        $encoding = (is_array($_partStructure) && ! empty($_partStructure['encoding']))
            ? $_partStructure['encoding']
            : $zmp->encoding;
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . " Trying to decode mime part content. Encoding/charset: " . $encoding . ' / ' . $charset);
        
        // need to set error handler because stream_get_contents just throws a E_WARNING
        set_error_handler('Mail::decodingErrorHandler', E_WARNING);
        try {
            $body = $zmp->getDecodedContent();
            restore_error_handler();
            
        } catch (Exception $e) {
            if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                . " Decoding of " . $zmp->encoding . '/' . $encoding . ' encoded message failed: ' . $e->getMessage());
            
            // trying to fix decoding problems
            restore_error_handler();
            $zmp->resetStream();
            if (preg_match('/convert\.quoted-printable-decode/', $e->getMessage())) {
                if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Trying workaround for http://bugs.php.net/50363.');
                $body = quoted_printable_decode(stream_get_contents($zmp->getRawStream()));
                $body = iconv($charset, 'utf-8', $body);
            } else {
                if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Try again with fallback encoding.');
                $zmp->appendDecodeFilter(self::_getDecodeFilter());
                set_error_handler('Fgsl\Groupware\Groupbase\Mail::decodingErrorHandler', E_WARNING);
                try {
                    $body = $zmp->getDecodedContent();
                    restore_error_handler();
                } catch (\Exception $e) {
                    restore_error_handler();
                    if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Fallback encoding failed. Trying base64_decode().');
                    $zmp->resetStream();
                    $decodedBody = base64_decode(stream_get_contents($zmp->getRawStream()));
                    $body = @iconv($charset, 'utf-8', $decodedBody);
                    if (empty($body)) {
                        // if iconv above still fails we do mb_convert and replace all special chars ...
                        $body = Helper::mbConvertTo($decodedBody);
                        $body = Helper::replaceSpecialChars($body, false);
                    }
                }
            }
        }
        
        return $body;
    }
    /**
     * convert charset (and return charset)
     *
     * @param  Part  $_part
     * @param  array           $_structure
     * @return string   
     */
    protected static function _getCharset(Part $_part, $_structure = NULL)
    {
        return ($_structure && isset($_structure['parameters']['charset'])) 
            ? $_structure['parameters']['charset']
            : ($_part->charset ? $_part->charset : self::DEFAULT_FALLBACK_CHARSET);
    }
    
    /**
     * convert charset (and return charset)
     *
     * @param  Part  $_part
     * @param  string          $charset
     * @return string   
     */
    protected static function _appendCharsetFilter(Part $_part, $charset)
    {
        if ('utf8' === $charset) {
            $charset = 'utf-8';
        } elseif ('us-ascii' === $charset) {
            // us-ascii caused problems with iconv encoding to utf-8
            $charset = self::DEFAULT_FALLBACK_CHARSET;
        } elseif (strpos($charset, '.') !== false) {
            // the stream filter does not like charsets with a dot in its name
            // stream_filter_append(): unable to create or locate filter "convert.iconv.ansi_x3.4-1968/utf-8//IGNORE"
            $charset = self::DEFAULT_FALLBACK_CHARSET;
        } elseif (@iconv($charset, 'utf-8', '') === false) {
            // check if charset is supported by iconv
            $charset = self::DEFAULT_FALLBACK_CHARSET;
        }
        
        $_part->appendDecodeFilter(self::_getDecodeFilter($charset));
        
        return $charset;
    }
    
    /**
     * get decode filter for stream_filter_append
     * 
     * @param string $_charset
     * @return string
     */
    protected static function _getDecodeFilter($_charset = self::DEFAULT_FALLBACK_CHARSET)
    {
        if (in_array(strtolower($_charset), array('iso-8859-1', 'windows-1252', 'iso-8859-15')) && extension_loaded('mbstring')) {
            require_once 'StreamFilter/ConvertMbstring.php';
            $filter = 'convert.mbstring';
        } else {
            // //IGNORE works only as of PHP7.2 -> the code expects an error to occur, don't use //IGNORE
            $filter = "convert.iconv.$_charset/utf-8";
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Appending decode filter: ' . $filter);
        
        return $filter;
    }
    
    /**
     * error exception handler for iconv decoding errors / only gets E_WARNINGs
     *
     * NOTE: PHP < 5.3 don't throws exceptions for Catchable fatal errors per default,
     * so we convert them into exceptions manually
     *
     * @param integer $severity
     * @param string $errstr
     * @param string $errfile
     * @param integer $errline
     * @throws Exception
     * 
     * @todo maybe we can remove that because php 5.3+ is required now
     */
    public static function decodingErrorHandler($severity, $errstr, $errfile, $errline)
    {
        Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " $errstr in {$errfile}::{$errline} ($severity)");
        
        throw new Exception($errstr);
    }
    
    /**
     * parse address list
     *
     * @param string $_adressList
     * @return array
     */
    public static function parseAdresslist($_addressList)
    {
        if (strpos($_addressList, ',') !== FALSE && substr_count($_addressList, '@') == 1) {
            // we have a comma in the name -> do not split string!
            $addresses = array($_addressList);
        } else {
            // create stream to be used with fgetcsv
            $stream = fopen("php://temp", 'r+');
            fputs($stream, $_addressList);
            rewind($stream);
            
            // alternative solution to create stream; yet untested
            #$stream = fopen('data://text/plain;base64,' . base64_encode($_addressList), 'r');
            
            // split addresses
            $addresses = fgetcsv($stream);
        }
        
        if (! is_array($addresses)) {
            if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . 
                ' Could not parse addresses: ' . var_export($addresses, TRUE));
            return array();
        }
        
        foreach ($addresses as $key => $address) {
            if (preg_match('/(.*)<(.+@[^@]+)>/', $address, $matches)) {
                $name = trim(trim($matches[1]), '"');
                $address = trim($matches[2]);
                $addresses[$key] = array('name' => substr($name, 0, 250), 'address' => $address);
            } else if (strpos($address, '@') !== false) {
                $address = preg_replace('/[,;]*/i', '', $address);
                $addresses[$key] = array('name' => null, 'address' => trim($address));
            } else {
                // skip this - no email address found
                unset($addresses[$key]);
            }
        }

        return $addresses;
    }

    /**
     * convert text to html
     * - replace quotes ('>  ') with blockquotes 
     * - does htmlspecialchars()
     * - converts linebreaks to <br />
     * 
     * @param string $text
     * @param string $blockquoteClass
     * @return string
     */
    public static function convertFromTextToHTML($text, $blockquoteClass = null)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Input: ' . $text);
        
        $lines = preg_split('/\r\n|\n|\r/', $text);
        $result = array();
        $indention = 0;
        foreach ($lines as $line) {
            // get indention level and remove quotes
            if (preg_match('/^>[> ]*/', $line, $matches)) {
                $indentionLevel = substr_count($matches[0], '>');
                $line = str_replace($matches[0], '', $line);
            } else {
                $indentionLevel = 0;
            }
            
            // convert html special chars
            $line = htmlspecialchars($line, ENT_COMPAT, 'UTF-8');
            
            // set blockquote tags for current indentionLevel
            while ($indention < $indentionLevel) {
                $class = $blockquoteClass ? 'class="' . $blockquoteClass . '"' : '';
                $line = '<blockquote ' . $class . '>' . $line;
                $indention++;
            }
            while ($indention > $indentionLevel) {
                $line = '</blockquote>' . $line;
                $indention--;
            }
            
            $result[] = $line;
            
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Line: ' . $line);
        }
        
        $result = implode('<br />', $result);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Result: ' . $result);
        
        return $result;
    }

    /**
     * get imap/smtp connection options
     *
     * do we verify imap/smtp peers?
     *
     * @param integer $timeout connection timeout
     * @return array
     *
     * TODO use separate configs for imap/smtp/sieve...
     */
    public static function getConnectionOptions($timeout = 30)
    {
        $connectionOptions = array(
            'timeout' => $timeout,
        );
        $tinebaseImapConfig = Config::getInstance()->get(Tinebase_Config::IMAP);
        if (isset($tinebaseImapConfig->verifyPeer) && $tinebaseImapConfig->verifyPeer == false) {
            $connectionOptions['context'] = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ),
            );
        }

        return $connectionOptions;
    }
}
