<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Auth
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009-2013 Serpro (http://www.serpro.gov.br)
 * @copyright   Copyright (c) 2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Marcelo Teixeira <marcelo.teixeira@serpro.gov.br>
 * @author      Guilherme Striquer Bisotto <guilherme.bisotto@serpro.gov.br>
 */

/**
 * OpenAM authentication backend
 *
 * @package     Tinebase
 * @subpackage  Auth
 */
class Custom_Tinebase_Auth_OpenAM implements Tinebase_Auth_Interface
{
    /**
     * type of backend;
     */
    const TYPE = 'OpenAM';

    /**
     * default cookie name
     */
    const DEFAULT_COOKIE_NAME = 'iPlanetDirectoryPro';

    /**
     * Value to be used as identity
     * @var string
     */
    protected $_identity;

    /**
     * Value to be used as credential
     * @var string
     */
    protected $_credential;

    /**
     * The constructor
     */
    public function __construct() {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) {
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Loading Module Tinebase_Auth_OpenAM');
        }
    }

    /**
     * Plugin initialization
     */
    public static function init()
    {
        Tinebase_Auth::addCustomBackend('OpenAM', 'Custom_Tinebase_Auth_OpenAM');
        Tinebase_Server_Http::setFrontendClass('Custom_Tinebase_Frontend_Http');

        Tinebase_PluginManager::addGlobalPluginConfigItem('global_plugins_sso_active', 'checkbox', 'SSO Active', FALSE);
        Tinebase_PluginManager::addGlobalPluginConfigItem('global_plugins_sso_serverUrl', 'textfield', 'SSO Server Url', '');
        Tinebase_PluginManager::addGlobalPluginConfigItem('global_plugins_sso_cookieName', 'textfield', 'SSO Cookie Name', 'iPlanetDirectoryPro');
        Tinebase_PluginManager::addGlobalPluginConfigItem('global_plugins_sso_sslVerify', 'checkbox', 'SSO SSL Verify Host', FALSE);

        Tinebase_PluginManager::addRegistryDataElement('sso', function() {
            $config = Tinebase_Core::getConfig()->global->plugins->sso ?
                Tinebase_Core::getConfig()->global->plugins->sso :
                (object) array('active' => false, 'serverUrl' => '', 'cookieName' => 'iPlanetDirectoryPro');
            return  $config;
        });

        Tinebase_Server_Http::addPlugin('Custom_Tinebase_Server_Plugin_Actions_OpenAM');
    }

    /**
     * Checks if backend must be forced over the configuration
     * @return boolean
     */
    public static function isForcedBackend()
    {
        $config = Tinebase_Config::getInstance();
        if(isset($config->global) &&
                isset($config->global->plugins) &&
                isset($config->global->plugins->sso) &&
                isset($config->global->plugins->sso->active)) {
            return $config->global->plugins->sso->active;
        }

        return false;
    }

    /**
     * has or not the session cookie token
     * @return boolean
     */
    public static function hasSessionToken()
    {
        $cookieName = self::DEFAULT_COOKIE_NAME;

        $config = Tinebase_Config::getInstance();
        if(isset($config->global) &&
                isset($config->global->plugins) &&
                isset($config->global->plugins->sso) &&
                isset($config->global->plugins->sso->cookieName)) {
            $cookieName = $config->global->plugins->sso->cookieName;
        }

        return isset($_COOKIE[$cookieName]);
    }

    /**
     * setCredential() - set the credential value to be used
     *
     * @param  string $credential
     * @return Zend_Auth_Adapter_Interface Provides a fluent interface
     */
    public function setCredential($credential)
    {
        $this->_credential = $credential;
    }

    /**
     * setIdentity() - set the value to be used as the identity
     *
     * @param  string $value
     * @return Zend_Auth_Adapter_Interface Provides a fluent interface
     */
    public function setIdentity($value)
    {
        $this->_identity = $value;
    }

    /**
     * Returns the type of backend
     * @return string
     */
    public static function getType()
    {
        return self::TYPE;
    }

    /**
     * Returns default configurations of the backend
     * @return array
     */
    public static function getBackendConfigurationDefaults()
    {
        return array();
    }

    /**
     * Returns a connection to user backend
     * @param array $_options
     * @return Tinebase_Ldap
     * @throws Tinebase_Exception_Backend
     */
    public static function getBackendConnection(array $_options = array())
    {
        return null;
    }

    /**
     * Checks if user backend is valid
     * @param mixed $_authBackend
     * @return boolean
     */
    public static function isValid($_authBackend)
    {
        return true;
    }

    /**
     * Force close connection to backend
     */
    public function closeConnection()
    {
    }

    /*
     * (non-PHPdoc)
     * @see Zend_Auth_Adapter_ModSsl::authenticate()
     */
    public function authenticate()
    {
        $config = Tinebase_Config::getInstance();
        if(isset($config->global) &&
                isset($config->global->plugins) &&
                isset($config->global->plugins->sso)) {

            $ssoConfig = $config->global->plugins->sso;

            $openAMUrl = $ssoConfig->serverUrl ? $ssoConfig->serverUrl : NULL;
            $cookieName = $ssoConfig->cookieName ? $ssoConfig->cookieName : self::DEFAULT_COOKIE_NAME;

            if ($openAMUrl == NULL || !$ssoConfig->active) {
                Tinebase_Core::getLogger()->err(__METHOD__ . "::" . __LINE__ . ":: SSO configuration missing!");
                return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, $this->_identity, array("SSO configuration missing!"));
            }
        } else {
            Tinebase_Core::getLogger()->err(__METHOD__ . "::" . __LINE__ . ":: SSO configuration missing!");
            return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, $this->_identity, array('SSO configuration missing'));
        }

        if (!extension_loaded('curl')) {
            Tinebase_Core::getLogger()->err(__METHOD__ . "::" . __LINE__ . ":: OpenAM needs curl extension!");
            return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, $this->_identity, array("OpenAM needs curl extension!"));
        }

        // First retrieve user identity using session cookie
        $ch = curl_init() or die ( curl_error() );
        $url = $openAMUrl . '/json/users?_action=idFromSession';
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, '');

        if(isset($config->global->plugins->sso->sslVerify) &&
                $config->global->plugins->sso->sslVerify == false) {
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
           'iplanetdirectorypro: ' . $_COOKIE[$cookieName],
           'Content-Type: application/json'
        ));
        curl_setopt( $ch, CURLOPT_POST, true);
        $data = curl_exec( $ch );
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $user = NULL;
        if($httpCode != 200) {
            return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, $this->_identity, array("OpenAM communication error!"));
        }
        $user = Zend_Json::decode($data);

        // Next, retrieve user info (mail, etc...)
        $url = $openAMUrl . '/json/users/' . $user['id'] . '?_fields=uid,mail';
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, false);
        $data = curl_exec( $ch );
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $userInfo = NULL;
        if($httpCode != 200) {
            return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, $this->_identity, array("OpenAM communication error!"));
        }
        $userInfo = Zend_Json::decode($data);
        curl_close( $ch );

        if (Tinebase_Config_Manager::isMultidomain()) {
            $this->_identity = $userInfo['mail'][0];
        } else {
            $this->_identity = $userInfo['uid'][0];
        }

        $messages = array();
        if(Tinebase_Config_Manager::getInstance()->isEnvironmentSet($this->_identity) === TRUE) {
            $status = Zend_Auth_Result::SUCCESS;
        } else {
            $status = Zend_Auth_Result::FAILURE;
            $messages = array("Failure setting environment");
        }

        return new Zend_Auth_Result($status, $this->_identity, $messages);
    }
}