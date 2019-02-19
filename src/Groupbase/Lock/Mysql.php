<?php
namespace Fgsl\Groupware\Groupbase\Lock;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\Backend as ExceptionBackend;
use Fgsl\Groupware\Setup\Exception\Exception as SetupException;
use Fgsl\Groupware\Setup\Backend\BackendFactory;
use Psr\Log\LogLevel;
use Zend\Db\Adapter\Adapter;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Mysql lock implementation
 * the lock only persists during a connection session
 * no need to use __destruct to release the lock, it goes away automatically
 *
 * @package     Groupbase
 * @subpackage  Lock
 */
class Mysql extends AbstractLock
{
    protected static $mysqlLockId = null;
    protected static $supportsMultipleLocks = false;

    public function keepAlive()
    {
        $db = Core::getDb();
        $db->query('SELECT now()')->fetchAll();
    }

    /**
     * @return bool
     */
    public function tryAcquire()
    {
        if ($this->_isLocked) {
            throw new ExceptionBackend('trying to acquire a lock on a locked lock');
        }
        if (!static::$supportsMultipleLocks && static::$mysqlLockId !== null) {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                .' your mysql version does not support multiple locks per session, configure a better lock backend');
            $this->_isLocked = true;
            return null;
        }
        $db = Core::getDb();
        if (($stmt = $db->query('SELECT GET_LOCK("' . $this->_lockId . '", 0)')) &&
                $stmt->setFetchMode(Adapter::FETCH_NUM) &&
                ($row = $stmt->fetch()) &&
                $row[0] == 1) {
            static::$mysqlLockId = $this->_lockId;
            $this->_isLocked = true;
            return true;
        }
        return false;
    }

    /**
     * @param string $lockId
     * @return bool
     */
    public function release()
    {
        if (!$this->_isLocked) {
            throw new ExceptionBackend('trying to release an unlocked lock');
        }
        if (!static::$supportsMultipleLocks && static::$mysqlLockId !== $this->_lockId) {
            $this->_isLocked = false;
            return null;
        }

        $db = Core::getDb();
        if (($stmt = $db->query('SELECT RELEASE_LOCK("' . $this->_lockId . '")')) &&
                $stmt->setFetchMode(Adapter::FETCH_NUM) &&
                ($row = $stmt->fetch()) &&
                $row[0] == 1) {
            static::$mysqlLockId = null;
            $this->_isLocked = false;
            return true;
        }
        return false;
    }

    /**
     * @throws SetupException
     * @throws InvalidArgument
     */
    public static function checkCapabilities()
    {
        if (BackendFactory::factory()->supports('mysql >= 5.7.5 | mariadb >= 10.0.2')) {
            static::$supportsMultipleLocks = true;
        }
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            .' mysql support for multiple locks: ' . var_export(static::$supportsMultipleLocks, true));
    }

    /**
     * @return bool
     */
    public static function supportsMultipleLocks()
    {
        return static::$supportsMultipleLocks;
    }
}