<?php
namespace Fgsl\Groupware\Groupbase;

use Zend\Mail\Message;
use Zend\Mail\Transport\TransportInterface;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Config\Struct;
use Fgsl\Groupware\Groupbase\Config\Config;
use Zend\Mail\Transport\Smtp as TransportSmtp;
use Fgsl\Groupware\Groupbase\Mail\Mail;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * class Smtp
 * 
 * send emails using smtp
 * 
 * @package Groupbase
 * @subpackage Smtp
 */
class Smtp
{
    /**
     * holds the instance of the singleton
     *
     * @var Smtp
     */
    private static $_instance = NULL;
    
    /**
     * the default smtp transport
     *
     * @var TransportInterface
     */
    protected static $_defaultTransport = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() 
    {
        self::createDefaultTransport();
    }
    
    /**
     * create default transport
     */
    public static function createDefaultTransport()
    {
        $config = Config::getInstance()->get(Config::SMTP, new Struct(array(
            'hostname' => 'localhost', 
            'port' => 25
        )))->toArray();
        
        // set default transport none is set yet
        if (! self::getDefaultTransport()) {
            if (empty($config['hostname'])) {
                $config['hostname'] = 'localhost';
            }
            
            // don't try to login if no username is given or if auth set to 'none'
            if (! isset($config['auth']) || $config['auth'] == 'none' || empty($config['username'])) {
                unset($config['username']);
                unset($config['password']);
                unset($config['auth']);
            }
            
            if (isset($config['ssl']) && $config['ssl'] == 'none') {
                unset($config['ssl']);
            }

            $config['connectionOptions'] = Mail::getConnectionOptions();

            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Setting default SMTP transport. Hostname: ' . $config['hostname']);

            $transport = new TransportSmtp($config['hostname'], $config);
            self::setDefaultTransport($transport);
        }
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }
    
    /**
     * the singleton pattern
     *
     * @return Smtp
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Smtp();
        }
        
        return self::$_instance;
    }

    /**
     * sets default transport
     * @param  TransportInterface|NULL $_transport
     * @return void
     */
    public static function setDefaultTransport($_transport)
    {
        if ($_transport) {
            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(
                __METHOD__ . '::' . __LINE__ . ' Setting SMTP transport: ' . get_class($_transport));
            self::$_defaultTransport = $_transport;
        } else {
            self::$_defaultTransport = NULL;
            self::createDefaultTransport();
        }
    }
    
    /**
     * returns default transport
     * 
     * @return null|TransportInterface
     */
    public static function getDefaultTransport()
    {
        return self::$_defaultTransport;
    }
    
    /**
     * send message using default transport or an instance of TransportInterface
     *
     * @param Message            $_mail
     * @param TransportInterface $_transport
     * @return void
     */
    public function sendMessage(Message $_mail, $_transport = NULL)
    {
        $transport = $_transport instanceof TransportInterface ? $_transport : self::getDefaultTransport();

        if (Core::isLogLevel(LogLevel::DEBUG) && $transport) {
            Core::getLogger()->debug(
                __METHOD__ . '::' . __LINE__ . ' Send Message using SMTP transport: '
                . get_class($transport));
        }
        
        if (! $_mail->getMessageId()) {
            $_mail->setMessageId();
        }
        $_mail->addHeader('X-MailGenerator', 'Tine 2.0');
        
        $_mail->send($transport);
    }
}
