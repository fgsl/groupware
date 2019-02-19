<?php
namespace Fgsl\Groupware\Groupbase\Auth;

use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Auth\ModSsl\Certificate\CertificateFactory;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Exception\Duplicate;
use Psr\Log\LogLevel;
use Zend\Authentication\Result;
use Fgsl\Groupware\Groupbase\Auth\ModSsl\UsernameCallback\Standard;
use Fgsl\Groupware\Addressbook\Controller\Certificate as ControllerCertificate;
use Fgsl\Groupware\Addressbook\Model\Certificate as ModelCertificate;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * DigitalCertificate authentication backend
 * 
 * @package     Groupbase
 * @subpackage  Auth
 */
class ModSsl implements AuthInterface
{
    /**
     * Constructor
     *
     * @param array  $options An array of arrays of IMAP options
     * @param string $username
     * @param string $password
     */
    public function __construct(array $options = array(), $username = null, $password = null)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(
            __METHOD__ . '::' . __LINE__ . ' ' . print_r($options, true));

        // TODO does this make sense?
        /** @noinspection PhpUndefinedMethodInspection */
        parent::__construct($options, $username, $password);
    }
    
    /**
     * set loginname
     *
     * TODO function probably doesnt work
     *
     * @param  string  $identity
     * @return ModSsl
     */
    public function setIdentity($identity)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::setUsername($identity);
        return $this;
    }
    
    /**
     * set password
     *
     * TODO function probably doesnt work
     *
     * @param  string  $credential
     * @return ModSsl
     */
    public function setCredential($credential)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::setPassword($credential);
        return $this;
    }
    /**
     * Verify if client was verified by apache mod_ssl
     *
     * @return boolean true if we have all needed mod_ssl server variables
     */
    static function hasModSsl(){
        
        // Get modssl config session
        $config = Config::getInstance()->get('modssl');
        if ($config && (!empty($_SERVER['SSL_CLIENT_CERT']) || !empty($_SERVER['HTTP_SSL_CLIENT_CERT']))
            &&  ((!empty($_SERVER['SSL_CLIENT_VERIFY']) && $_SERVER['SSL_CLIENT_VERIFY'] == 'SUCCESS')
                || (!empty($_SERVER['HTTP_SSL_CLIENT_VERIFY']) && $_SERVER['HTTP_SSL_CLIENT_VERIFY'] == 'SUCCESS'))) {
                    if (Core::isLogLevel(LogLevel::DEBUG)) {
                        Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ModSsl detected');
                    }
                    return true;
                }
                
                return false;
                
    }
    
    public function authenticate()
    {
        if (self::hasModSsl()) {
            
            // Fix to support reverseProxy without SSLProxyEngine
            $clientCert = !empty($_SERVER['SSL_CLIENT_CERT']) ? $_SERVER['SSL_CLIENT_CERT'] : $_SERVER['HTTP_SSL_CLIENT_CERT'];
            
            // get Identity
            $certificate = CertificateFactory::buildCertificate($clientCert);
            $config = Config::getInstance()->get('modssl');
            
            if (class_exists($config->username_callback)) {
                $callback = new $config->username_callback($certificate);
            } else { // fallback to default
                $callback = new Standard($certificate);
            }
            
            $this->setIdentity(call_user_func(array($callback, 'getUsername')));
            $this->setCredential(null);
            
            if ($certificate instanceof \Fgsl\Groupware\Groupbase\Auth\ModSsl\Certificate\X509) {
                if(!$certificate->isValid()) {
                    $lines = '';
                    foreach($certificate->getStatusErrors() as $line) {
                        $lines .= $line . '#';
                    }
                    
                    if (Core::isLogLevel(LogLevel::ERR)) {
                        Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ModSsl authentication for '. $this->_identity . ' failed: ' . $lines);
                    }
                    
                    return new Result(Result::FAILURE_CREDENTIAL_INVALID, $this->_identity, $certificate->getStatusErrors());
                }
                $messages = array('Authentication Successfull');
                
                // If certificate is valid store it in database
                $controller = ControllerCertificate::getInstance();
                try {
                    $controller->create(new ModelCertificate($certificate));
                } catch (Duplicate $e) {
                    // Fail silently if certificate already exists
                }
                return new Result(Result::SUCCESS, $this->_identity, $messages);
            }
        }
        
        return new Result(Result::FAILURE, 'Unknown User', array('Unknown Authentication Error'));
    }
}
