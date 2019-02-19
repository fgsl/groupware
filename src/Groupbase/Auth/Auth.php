<?php
namespace Fgsl\Groupware\Groupbase\Auth;
use Zend\Authentication\Result;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Config\Struct;
use Fgsl\Groupware\Groupbase\Session\Session;
use Zend\Authentication\AuthenticationService;
use Zend\Session\Storage\SessionStorage;
use Zend\Authentication\Storage\NonPersistent;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * main authentication class
 * 
 * @package     Groupbase
 * @subpackage  Auth 
 */

class Auth
{
    /**
     * constant for Sql auth
     *
     */
    const SQL = 'Sql';
    
    /**
     * constant for LDAP auth
     *
     */
    const LDAP = 'Ldap';

    /**
     * constant for IMAP auth
     *
     */
    const IMAP = 'Imap';

    /**
     * constant for DigitalCertificate auth / SSL
     *
     */
    const MODSSL = 'ModSsl';

    /**
     * PIN auth
     *
     */
    const PIN = 'Pin';

    /**
     * General Failure
     */
    const FAILURE                       =  Result::FAILURE;

    /**
     * Failure due to identity not being found.
     */
    const FAILURE_IDENTITY_NOT_FOUND    = Result::FAILURE_IDENTITY_NOT_FOUND;

    /**
     * Failure due to identity being ambiguous.
     */
    const FAILURE_IDENTITY_AMBIGUOUS    = Result::FAILURE_IDENTITY_AMBIGUOUS;

    /**
     * Failure due to invalid credential being supplied.
     */
    const FAILURE_CREDENTIAL_INVALID    = Result::FAILURE_CREDENTIAL_INVALID;

    /**
     * Failure due to uncategorized reasons.
     */
    const FAILURE_UNCATEGORIZED         = Result::FAILURE_UNCATEGORIZED;
    
    /**
     * Failure due the account is disabled
     */
    const FAILURE_DISABLED              = -100;

    /**
     * Failure due the account is expired
     */
    const FAILURE_PASSWORD_EXPIRED      = -101;
    
    /**
     * Failure due the account is temporarily blocked
     */
    const FAILURE_BLOCKED               = -102;
        
    /**
     * database connection failure
     */
    const FAILURE_DATABASE_CONNECTION   = -103;
        
    /**
     * Authentication success.
     */
    const SUCCESS                        =  Result::SUCCESS;

    /**
     * the name of the authenticationbackend
     *
     * @var string
     */
    protected static $_backendType;
    
    /**
     * Holds the backend configuration options.
     * Property is lazy loaded from {@see Config} on first access via
     * getter {@see getBackendConfiguration()}
     * 
     * @var array | optional
     */
    private static $_backendConfiguration;
    
    /**
     * Holds the backend configuration options.
     * Property is lazy loaded from {@see Config} on first access via
     * getter {@see getBackendConfiguration()}
     * 
     * @var array | optional
     */
    private static $_backendConfigurationDefaults = array(
        self::SQL => array(
            'tryUsernameSplit' => '1',
            'accountCanonicalForm' => '2',
            'accountDomainName' => '',
            'accountDomainNameShort' => '',
        ),
        self::LDAP => array(
            'host' => '',
            'username' => '',
            'password' => '',
            'bindRequiresDn' => true,
            'useStartTls' => false,
            'useSsl' => false,
            'port' => 0,
            'baseDn' => '',
            'accountFilterFormat' => NULL,
            'accountCanonicalForm' => '2',
            'accountDomainName' => '',
            'accountDomainNameShort' => '',
            'tryUsernameSplit' => '0'
         ),
         self::IMAP => array(
            'host'      => '',
            'port'      => 143,
            'ssl'       => 'tls',
            'domain'    => '',
         ),
         self::MODSSL => array(
             'casfile'           => null,
             'crlspath'          => null,
             'validation'        => null,
             'tryUsernameSplit'  => '1'
         )
     );
    
    /**
     * the instance of the authenticationbackend
     *
     * @var AuthInterface
     */
    protected $_backend;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() 
    {
        $this->setBackend();
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}

    /**
     * holds the instance of the singleton
     *
     * @var Auth
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return Auth
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Auth;
        }
        
        return self::$_instance;
    }
    
    /**
     * authenticate user
     *
     * @param string $_username
     * @param string $_password
     * @return Result
     */
    public function authenticate($_username, $_password)
    {
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(
            __METHOD__ . '::' . __LINE__ . ' Trying to authenticate '. $_username);
        
        try {
            $this->_backend->setIdentity($_username);
        } catch (\Exception $zaae) {
            return new Result(
                Result::FAILURE_CREDENTIAL_INVALID,
                $_username,
                array($zaae->getMessage())
            );
        }
        
        $this->_backend->setCredential($_password);

        $authenticationService = new AuthenticationService();
        try {
            if (Session::isStarted()) {
                $authenticationService->setStorage(new SessionStorage());
            } else {
                $authenticationService->setStorage(new NonPersistent());
            }
        } catch (\Exception $e) {
            $authenticationService->setStorage(new NonPersistent());
        }
        $result = $authenticationService->authenticate($this->_backend);
        
        return $result;
    }
    
    /**
     * check if password is valid
     *
     * @param string $_username
     * @param string $_password
     * @return boolean
     */
    public function isValidPassword($_username, $_password)
    {
        $this->_backend->setIdentity($_username);
        $this->_backend->setCredential($_password);
        
        $result = $this->_backend->authenticate();

        if ($result->isValid()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * returns the configured rs backend
     * 
     * @return string
     */
    public static function getConfiguredBackend()
    {
        if (!isset(self::$_backendType)) {
            if (Application::getInstance()->isInstalled('Tinebase')) {
                self::setBackendType(Config::getInstance()->get(Config::AUTHENTICATIONBACKENDTYPE, self::SQL));
            } else {
                self::setBackendType(self::SQL);
            }
        }
        
        return self::$_backendType;
    }
    
    /**
     * set the auth backend
     */
    public function setBackend()
    {
        $backendType = self::getConfiguredBackend();
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(
            __METHOD__ . '::' . __LINE__ .' authentication backend: ' . $backendType);
        
        $this->_backend = AuthFactory::factory($backendType);
    }

    /**
     * @return AuthInterface
     */
    public function getBackend()
    {
        return $this->_backend;
    }
    
    /**
     * setter for {@see $_backendType}
     * 
     * @todo persist in db
     * 
     * @param string $_backendType
     * @return void
     */
    public static function setBackendType($_backendType)
    {
        self::$_backendType = ucfirst($_backendType);
    }
    
    /**
     * Setter for {@see $_backendConfiguration}
     * 
     * NOTE:
     * Setting will not be written to Database or Filesystem.
     * To persist the change call {@see saveBackendConfiguration()}
     * 
     * @param mixed $_value
     * @param string $_key
     * @param boolean $_applyDefaults
     * @return void
     */
    public static function setBackendConfiguration($_value, $_key = null, $_applyDefaults = false)
    {
        $defaultValues = self::$_backendConfigurationDefaults[self::getConfiguredBackend()];
        
        if (is_null($_key) && !is_array($_value)) {
            throw new InvalidArgument('To set backend configuration either a key and value parameter are required or the value parameter should be a hash');
        } elseif (is_null($_key) && is_array($_value)) {
            $configToSet = $_applyDefaults ? array_merge($defaultValues, $_value) : $_value;
            foreach ($configToSet as $key => $value) {
                self::setBackendConfiguration($value, $key);
            }
        } else {
            if ( ! (isset($defaultValues[$_key]) || array_key_exists($_key, $defaultValues))) {
                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                    "Cannot set backend configuration option '$_key' for accounts storage " . self::getConfiguredBackend());
                return;
            }
            self::$_backendConfiguration[$_key] = $_value;
        }
    }
    
    /**
     * Delete the given config setting or all config settings if {@param $_key} is not specified
     * 
     * @param string | optional $_key
     * @return void
     */
    public static function deleteBackendConfiguration($_key = null)
    {
        if (is_null($_key)) {
            self::$_backendConfiguration = array();
        } elseif ((isset(self::$_backendConfiguration[$_key]) || array_key_exists($_key, self::$_backendConfiguration))) {
            unset(self::$_backendConfiguration[$_key]);
        } else {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' configuration option does not exist: ' . $_key);
        }
    }
    
    /**
     * Write backend configuration setting {@see $_backendConfigurationSettings} and {@see $_backendType} to
     * db config table.
     * 
     * @return void
     */
    public static function saveBackendConfiguration()
    {
        Config::getInstance()->set(Config::AUTHENTICATIONBACKEND, self::getBackendConfiguration());
        Config::getInstance()->set(Config::AUTHENTICATIONBACKENDTYPE, self::getConfiguredBackend());
    }
    
    /**
     * Getter for {@see $_backendConfiguration}
     * 
     * @param boolean $_getConfiguredBackend
     * @return mixed [If {@param $_key} is set then only the specified option is returned, otherwise the whole options hash]
     */
    public static function getBackendConfiguration($_key = null, $_default = null)
    {
        //lazy loading for $_backendConfiguration
        if (!isset(self::$_backendConfiguration)) {
            if (Application::getInstance()->isInstalled('Tinebase')) {
                $struct = new Struct();
                $rawBackendConfiguration = Config::getInstance()->get(Config::AUTHENTICATIONBACKEND, $struct->toArray());
            } else {
                $rawBackendConfiguration = array();
            }
            self::$_backendConfiguration = is_array($rawBackendConfiguration) ? $rawBackendConfiguration : json_decode($rawBackendConfiguration);
            
            if (!empty(self::$_backendConfiguration['password'])) {
                Core::getLogger()->addReplacement(self::$_backendConfiguration['password']);
            }
        }
        
        if (isset($_key)) {
            return (isset(self::$_backendConfiguration[$_key]) || array_key_exists($_key, self::$_backendConfiguration)) ? self::$_backendConfiguration[$_key] : $_default;
        } else {
            return self::$_backendConfiguration;
        }
    }
    
    /**
     * Returns default configuration for all supported backends 
     * and overrides the defaults with concrete values stored in this configuration 
     * 
     * @param bool $_getConfiguredBackend
     * @return mixed [If {@param $_key} is set then only the specified option is returned, otherwise the whole options hash]
     */
    public static function getBackendConfigurationWithDefaults($_getConfiguredBackend = TRUE)
    {
        $config = array();
        $defaultConfig = self::getBackendConfigurationDefaults();
        foreach ($defaultConfig as $backendType => $backendConfig) {
            $config[$backendType] = ($_getConfiguredBackend && $backendType == self::getConfiguredBackend() ? self::getBackendConfiguration() : array());
            if (is_array($config[$backendType])) {
                foreach ($backendConfig as $key => $value) {
                    // 2010-05-20 cweiss Zend_Ldap changed and does not longer throw exceptions
                    // on unsupported values, we might skip this cleanup here.
                    if (! (isset($config[$backendType][$key]) || array_key_exists($key, $config[$backendType]))) {
                        $config[$backendType][$key] = $value;
                    }
                }
            } else {
                $config[$backendType] = $backendConfig;
            }
        }
        return $config;
    }
    
    /**
     * Getter for {@see $_backendConfigurationDefaults}
     * @param String | optional $_backendType
     * @return array
     * @throws InvalidArgument
     */
    public static function getBackendConfigurationDefaults($_backendType = null) {
        if ($_backendType) {
            if (!(isset(self::$_backendConfigurationDefaults[$_backendType]) || array_key_exists($_backendType, self::$_backendConfigurationDefaults))) {
                throw new InvalidArgument("Unknown backend type '$_backendType'");
            }
            return self::$_backendConfigurationDefaults[$_backendType];
        } else {
            return self::$_backendConfigurationDefaults;
        }
    }
}