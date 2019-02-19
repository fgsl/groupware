<?php
namespace Fgsl\Groupware\Groupbase\Lock;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Lock\Redis as LockRedis;
use Zend\Db\Adapter\Platform\Mysql as AdapterMysql;
use Zend\Db\Adapter\Platform\Postgresql as AdapterPgsql;
use Fgsl\Groupware\Groupbase\Config\Config;
use Psr\Log\LogLevel;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Locking utility class
 *
 * @package     Tinebase
 */
class Lock
{
    protected static $locks = [];
    protected static $lastKeepAlive = null;

    /**
     * @var string
     */
    protected static $backend = null;

    /**
     * tries to release all locked locks (catches and logs exceptions silently)
     * removes all lock objects
     */
    public static function clearLocks()
    {
        /** @var Tinebase_Lock_Abstract $lock */
        foreach (static::$locks as $lock) {
            try {
                if ($lock->isLocked()) {
                    $lock->release();
                }
            } catch (\Exception $e) {
                Exception::log($e);
            }
        }

        static::$locks = [];
    }

    public static function keepLocksAlive()
    {
        // only do this once a minute
        if (null !== static::$lastKeepAlive && time() - static::$lastKeepAlive < 60) {
            return;
        }
        static::$lastKeepAlive = time();

        /** @var Tinebase_Lock_Abstract $lock */
        foreach (static::$locks as $lock) {
            $lock->keepAlive();
        }
    }

    /**
     * @param $id
     * @return LockInterface
     */
    public static function getLock($id)
    {
        $id = static::preFixId($id);
        if (!isset(static::$locks[$id])) {
            static::$locks[$id] = static::getBackend($id);
        }
        return static::$locks[$id];
    }
    /**
     * @param string $id
     * @return bool|null bool on success / failure, null if not supported
     */
    public static function tryAcquireLock($id)
    {
        $id = static::preFixId($id);
        if (isset(static::$locks[$id])) {
            return static::$locks[$id]->tryAcquire();
        }
        if (($lock = static::getBackend($id)) === null) {
            return null;
        }
        static::$locks[$id] = $lock;
        return $lock->tryAcquire();
    }

    /**
     * @param string $id
     * @return bool|null bool on success / failure, null if not supported
     */
    public static function releaseLock($id)
    {
        $id = static::preFixId($id);
        if (isset(static::$locks[$id])) {
            return static::$locks[$id]->release();
        }
        if (static::getBackend($id) === null) {
            return null;
        }
        return false;
    }

    public static function preFixId($id)
    {
        return 'tine20_' . $id;
    }
    /**
     * @param string $id
     * @return LockInterface
     */
    protected static function getBackend($id)
    {
        if (null === static::$backend) {
            $db = Core::getDb();
            if ($db instanceof AdapterMysql) {
                Mysql::checkCapabilities();
            }

            $config = Config::getInstance();
            $cachingBackend = null;
            if ($config->caching && $config->caching->backend) {
                $cachingBackend = ucfirst($config->caching->backend);
            }

            if (Mysql::supportsMultipleLocks()) {
                static::$backend = Mysql::class;
            } elseif ($cachingBackend === 'Redis' && extension_loaded('redis')) {
                $host = $config->caching->host ? $config->caching->host :
                    ($config->caching->redis && $config->caching->redis->host ?
                        $config->caching->redis->host : 'localhost');
                $port = $config->caching->port ? $config->caching->port :
                    ($config->caching->redis && $config->caching->redis->port ? $config->caching->redis->port : 6379);
                static::$backend = LockRedis::class;
                LockRedis::connect($host, $port);
            } elseif ($db instanceof AdapterMysql) {
                static::$backend = Mysql::class;
            } elseif($db instanceof AdapterPgsql) {
                static::$backend = Pgsql::class;
            } else {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ .' no lock backend found');
                return null;
            }
            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                .' lock backend is: ' . static::$backend);
        }
        return new static::$backend($id);
    }
}