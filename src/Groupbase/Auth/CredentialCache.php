<?php
namespace Fgsl\Groupware\Groupbase\Auth;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Controller\ControllerInterface;
use Fgsl\Groupware\Groupbase\Auth\CredentialCache\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Auth\CredentialCache\Adapter\Config as AdapterConfig;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Model\CredentialCache as ModelCredentialCache;;
use Fgsl\Groupware\Groupbase\Exception\SystemGeneric;
use Psr\Log\LogLevel;
use Zend\Db\Adapter\AdapterInterface as DbAdapterInterface;
use Fgsl\Groupware\Groupbase\DateTime;
use Fgsl\Groupware\Groupbase\Session\Session;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Helper;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Application\Application;
use Zend\Db\Adapter\Adapter;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class for caching credentials
 *  
 * @package     Groupbase
 * @subpackage  Auth
 */
class CredentialCache extends AbstractSql implements ControllerInterface
{
    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'credential_cache';
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'CredentialCache';
    
    /**
     * holds credential cache id/key pair for current request
     *
     * @var array
     */
    protected static $_credentialcacheid = NULL;
    
    /**
     * credential cache adapter
     * 
     * @var AdapterInterface
     */
    protected $_cacheAdapter = NULL;
    
    /**
     * holds the instance of the singleton
     *
     * @var CredentialCache
     */
    private static $_instance = NULL;
    
    const SESSION_NAMESPACE = 'credentialCache';
    const CIPHER_ALGORITHM = 'aes-256-ctr';
    const HASH_ALGORITHM = 'sha256';
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}

    /**
     * the constructor
     *
     * @param DbAdapterInterface $_dbAdapter (optional)
     * @param array $_options (optional)
     * @throws SystemGeneric
     */
    public function __construct($_dbAdapter = NULL, $_options = array()) 
    {
        if (! extension_loaded('openssl')) {
            throw new SystemGeneric('openssl extension required');
        }
        if (!in_array(self::CIPHER_ALGORITHM, openssl_get_cipher_methods(true)))
        {
            throw new SystemGeneric('cipher algorithm: ' . self::CIPHER_ALGORITHM . ' not supported');
        }
        if (!in_array(self::HASH_ALGORITHM, openssl_get_md_methods(true)))
        {
            throw new SystemGeneric('hash algorithm: ' . self::HASH_ALGORITHM . ' not supported');
        }

        parent::__construct($_dbAdapter, $_options);
        
        // set default adapter
        $config = Core::getConfig();
        $adapter = ($config->{AdapterConfig::CONFIG_KEY}) ? 'Config' : 'Cookie';
        $this->setCacheAdapter($adapter);
    }
    
    /**
     * the singleton pattern
     *
     * @return CredentialCache
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new CredentialCache();
        }
        
        return self::$_instance;
    }
    
    /**
     * set cache adapter
     * 
     * @param string $_adapter
     */
    public function setCacheAdapter($_adapter = 'Cookie')
    {
        $adapterClass = 'Fgsl\Groupware\Groupbase\CredentialCache\Adapter\\' . $_adapter;
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Using credential cache adapter: ' . $adapterClass);
        $this->_cacheAdapter = new $adapterClass();
    }
    
    /**
     * get cache adapter
     * 
     * @return AdapterInterface
     */
    public function getCacheAdapter()
    {
        return $this->_cacheAdapter;
    }
    
    /**
     * caches given credentials
     *
     * @param  string $username
     * @param  string $password
     * @param  string $key [optional]
     * @param  boolean $persist
     * @param  DateTime $validUntil
     * @return ModelCredentialCache
     */
    public function cacheCredentials($username, $password, $key = NULL, $persist = FALSE, $validUntil = null)
    {
        $key = ($key !== NULL) ? $key : $this->_cacheAdapter->getDefaultKey();
        
        $cache = new ModelCredentialCache(array(
            'id'            => $this->_cacheAdapter->getDefaultId(),
            'key'           => substr($key, 0, 24),
            'username'      => $username,
            'password'      => $password,
            'creation_time' => DateTime::now(),
            'valid_until'   => $validUntil ?: DateTime::now()->addMonth(1)
        ), true, false);
        $cache->setConvertDates(true);
        
        $this->_encrypt($cache);
        $this->_saveInSession($cache);
        if ($persist) {
            $this->_persistCache($cache);
        }
        
        return $cache;
    }
    
    /**
     * save cache record in session
     * 
     * @param ModelCredentialCache $cache
     */
    protected function _saveInSession(ModelCredentialCache $cache)
    {
        try {
            $session = Session::getSessionNamespace();
            
            $session->{self::SESSION_NAMESPACE}[$cache->getId()] = $cache->toArray();
        } catch (\Exception $zse) {
            // nothing to do
        }
    }
    
    /**
     * persist cache record (in db)
     * -> needs to check if entry exists (some adapters can have static ids)
     * 
     * @param ModelCredentialCache $cache
     */
    protected function _persistCache(ModelCredentialCache $cache)
    {
        try {
            $this->create($cache);
        } catch (\Exception $zdse) {
            $this->update($cache);
        }
    }
    
    /**
     * returns cached credentials
     *
     * @param ModelCredentialCache $_cache
     * @throws InvalidArgument
     * @throws NotFound
     */
    public function getCachedCredentials(ModelCredentialCache $_cache)
    {
        if (! $_cache || $_cache === NULL || ! $_cache instanceof CredentialCache) {
            throw new InvalidArgument('No valid CredentialCache given!');
        }
        
        if (! ($_cache->username && $_cache->password)) {
            $savedCache = $this->_getCache($_cache->getId())->toArray();
            $_cache->setFromArray($savedCache);
            $this->_decrypt($_cache);
        }
    }
    
    /**
     * get cache record (try to find in session first, then DB)
     * 
     * @param string $id
     * @return ModelCredentialCache
     */
    protected function _getCache($id)
    {
        try {
            $session = Session::getSessionNamespace();
            
            $credentialSessionCache = $session->{self::SESSION_NAMESPACE};
            
            if (isset($credentialSessionCache) && isset($credentialSessionCache[$id])) {
                return new CredentialCache($credentialSessionCache[$id]);
            }
            
        } catch (\Exception $zse) {
            // nothing to do
        }

        /** @var CredentialCache $result */
        $result = $this->get($id);
        $this->_saveInSession($result);
        
        return $result;
    }

    /**
     * @param string $_data
     * @param string $_key
     * @return string
     * @throws \Exception
     * @throws Exception:
     */
    public static function encryptData($_data, $_key)
    {
        // there was a bug with openssl_random_pseudo_bytes but its fixed in all major PHP versions, so we use it. People have to update their PHP versions
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER_ALGORITHM), $secure);
        if (!$secure) {
            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(
                __METHOD__ . '::' . __LINE__ . ' openssl_random_pseudo_bytes returned weak random bytes!');
            if (function_exists('random_bytes')) {
                $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER_ALGORITHM));
            } elseif (function_exists('mcrypt_create_iv')) {
                $iv = mcrypt_create_iv(openssl_cipher_iv_length(self::CIPHER_ALGORITHM), MCRYPT_DEV_URANDOM);
            } else {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(
                    __METHOD__ . '::' . __LINE__ . ' openssl_random_pseudo_bytes returned weak random bytes and we could not find a method better suited!');
            }
        }

        $hash = openssl_digest($_key, self::HASH_ALGORITHM, true);

        if (false === ($encrypted = openssl_encrypt($_data, self::CIPHER_ALGORITHM, $hash, OPENSSL_RAW_DATA, $iv))) {
            throw new Exception('encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * @param $_data
     * @param $_key
     * @return bool|string
     */
    public static function decryptData($_data, $_key)
    {
        if (false === ($encryptedData = base64_decode($_data))) {
            return false;
        }
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGORITHM);

        if (strlen($encryptedData) < $ivLength)
        {
            return false;
        }

        $iv = substr($encryptedData, 0, $ivLength);
        $encryptedData = substr($encryptedData, $ivLength);
        $hash = openssl_digest($_key, self::HASH_ALGORITHM, true);

        return openssl_decrypt($encryptedData, self::CIPHER_ALGORITHM, $hash, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * encrypts username and password into cache
     *
     * reference implementation: https://github.com/ioncube/php-openssl-cryptor
     *
     * @param  ModelCredentialCache $_cache
     * @throws Exception
     */
    protected function _encrypt(ModelCredentialCache $_cache)
    {
        $data = json_encode(array_merge($_cache->toArray(), array(
            'username' => $_cache->username,
            'password' => $_cache->password,
        )));

        $_cache->cache = static::encryptData($data, $_cache->key);
    }

    /**
     * decrypts username and password
     *
     * @param  ModelCredentialCache $_cache
     * @throws NotFound
     */
    protected function _decrypt(ModelCredentialCache $_cache)
    {
        $persistAgain = false;

        if (false === ($jsonEncodedData = static::decryptData($_cache->cache, $_cache->key))
            || ! Helper::is_json(trim($jsonEncodedData)))
        {
            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(
                __METHOD__ . '::' . __LINE__ . ' lets try to decode it with the old algorithm, if successful, persist again if this is a persistent cache');

            if (false !== ($jsonEncodedData = openssl_decrypt(
                    base64_decode($_cache->cache), 'AES-128-CBC',
                    $_cache->key,
                    OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING,
                    substr($_cache->getId(), 0, 16)
                ))) {
                $persistAgain = true;
            } else {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(
                    __METHOD__ . '::' . __LINE__ . ' decryption failed');
                throw new NotFound('decryption failed: ' . openssl_error_string());
            }
        }

        try {
            $cacheData = Helper::jsonDecode(trim($jsonEncodedData));
        } catch(Exception $e) {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(
                __METHOD__ . '::' . __LINE__ . ' persisted cache data is no valid json');
            throw new NotFound('persisted cache data is no valid json');
        }

        if (! isset($cacheData['username']) && ! isset($cacheData['password'])) {
            throw new NotFound('could not find valid credential cache');
        }

        $_cache->username = $cacheData['username'];
        $_cache->password = $cacheData['password'];

        if (true === $persistAgain) {
            try {
                $this->get($_cache->getId());
                $this->_encrypt($_cache);
                $this->_persistCache($_cache);
            } catch(NotFound $tenf) {
                // shouldn't happen anyway, just to be save.
                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(
                    __METHOD__ . '::' . __LINE__ . ' cache is not in DB, so we don\'t persist it again, just continue gracefully');
            }
        }
    }
    
    /**
     * remove all credential cache records before $_date
     * 
     * @param DateTime|string $_date
     * @return bool
     */
    public function clearCacheTable($_date = NULL)
    {
        $dateString = ($_date instanceof DateTime) ? $_date->format(AbstractRecord::ISO8601LONG) : $_date;
        $dateWhere = ($dateString === NULL) 
            ? $this->_db->quoteInto($this->_db->quoteIdentifier('valid_until') . ' < ?', DateTime::now()->format(AbstractRecord::ISO8601LONG)) 
            : $this->_db->quoteInto($this->_db->quoteIdentifier('creation_time') . ' < ?', $dateString);
        $where = array($dateWhere);

        // TODO should be handled with looong "valid_until" until time
        if (Application::getInstance()->isInstalled('Mail')) {
            // delete only records that are not related to email accounts
            $fmailIds = $this->_getFelamimailCredentialIds();
            if (! empty($fmailIds)) {
                $where[] = $this->_db->quoteInto($this->_db->quoteIdentifier('id') .' NOT IN (?)', $fmailIds);
            }
        }
        
        $tableName = $this->getTablePrefix() . $this->getTableName();
        $this->_db->delete($tableName, $where);

        return true;
    }
    
    /**
     * returns all credential ids that are used in felamimail
     * 
     * @return array
     */
    protected function _getFelamimailCredentialIds()
    {
        $select = $this->_db->select()
            ->from(SQL_TABLE_PREFIX . 'felamimail_account', array('credentials_id', 'smtp_credentials_id'));
        $stmt = $this->_db->query($select);
        $result = $stmt->fetchAll(Adapter::FETCH_NUM);
        $fmailIds = array();
        foreach ($result as $credentialIds) {
            $fmailIds = array_merge($fmailIds, $credentialIds);
        }
        $fmailIds = array_unique($fmailIds);
        
        return $fmailIds;
    }
}
