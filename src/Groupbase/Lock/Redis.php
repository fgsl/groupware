<?php
namespace Fgsl\Groupware\Groupbase\Lock;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\Backend as ExceptionBackend;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\RedisProxy;

/**
 * Redis lock implementation
 *
 * @package     Tinebase
 * @subpackage  Lock
 */
class Redis extends AbstractLock
{
    /**
     * @var Redis
     */
    protected static $_redis = null;

    protected $_lockUUID = null;

    public function keepAlive()
    {
        // use the method! not the property! the method does the keep a live for us.
        if (!$this->isLocked()) {
            throw new ExceptionBackend('trying to keep an unlocked lock alive');
        }
    }

    /**
     * @return bool
     */
    public function tryAcquire()
    {
        if ($this->_isLocked) {
            throw new ExceptionBackend('trying to acquire a lock on a locked lock');
        }
        $this->_lockUUID = AbstractRecord::generateUID();

        // set a TTL of 10 minutes
        if (true === static::$_redis->rawCommand('SET',  $this->_lockId, $this->_lockUUID, 'NX', 'PX', '600000')) {
            $this->_isLocked = true;
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function release()
    {
        if (!$this->_isLocked) {
            throw new ExceptionBackend('trying to release an unlocked lock');
        }

        // this Redis "Lock" is a lease! not a lock. So maybe we lost our lease to a time out here
        // first clear the error, execute eval, if that returns 0, but no errors occurred, it's a time out
        static::$_redis->clearLastError();
        if (static::$_redis->eval('if redis.call("get",KEYS[1]) == KEYS[2]
                then
                    return redis.call("del",KEYS[1])
                else
                    return 0
                end', [$this->_lockId, $this->_lockUUID], 2)) {
            $this->_isLocked = false;
            return true;
        }
        if (null === static::$_redis->getLastError()) {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                . __LINE__ .' releasing an expired lock');
            $this->_isLocked = false;
        } else {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                . __LINE__ .' lock release failed: ' . static::$_redis->getLastError());
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isLocked()
    {
        if ($this->_isLocked) {
            $getResult = null;
            if (!($expireResult = static::$_redis->expire($this->_lockId, 600)) ||
                    ($getResult = static::$_redis->get($this->_lockId)) !== $this->_lockUUID) {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ .' lock was locked, expireResult: ' . var_export($expireResult, true)
                    . ' getResult: ' . var_export($getResult, true));
                $this->_isLocked = false;
            }
        }
        return $this->_isLocked;
    }

    public function __destruct()
    {
        if ($this->_isLocked) {
            $this->release();
        }
    }

    public static function connect($host, $port)
    {
        static::$_redis = new RedisProxy();
        if (!static::$_redis->connect($host, $port, 3)) {
            throw new ExceptionBackend('could not connect to redis');
        }
    }
}