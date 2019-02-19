<?php
namespace Fgsl\Groupware\Groupbase\Lock;

use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\Backend as ExceptionBackend;
use Zend\Db\Adapter\Adapter;


/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Pgsql lock implementation
 * the lock only persists during a connection session
 * no need to use __destruct to release the lock, it goes away automatically
 *
 * @package     Groupbase
 * @subpackage  Lock
 */
class Pgsql extends AbstractLock
{
    public function keepAlive()
    {
        $db = Core::getDb();
        $db->query('SELECT NOW()')->fetchAll();
    }


    /**
     * @return bool
     */
    public function tryAcquire()
    {
        if ($this->_isLocked) {
            throw new ExceptionBackend('trying to acquire a lock on a locked lock');
        }
        $db = Core::getDb();
        if (($stmt = $db->query('SELECT pg_try_advisory_lock(' . $this->_lockId . ')')) &&
                $stmt->setFetchMode(Adapter::FETCH_NUM) &&
                ($row = $stmt->fetch()) &&
                $row[0] == 1) {
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
        $db = Core::getDb();
        if (($stmt = $db->query('SELECT pg_advisory_unlock(' . $this->_lockId . ')')) &&
                $stmt->setFetchMode(Adapter::FETCH_NUM) &&
                ($row = $stmt->fetch()) &&
                $row[0] == 1) {
            $this->_isLocked = false;
            return true;
        }
        return false;
    }

    protected function processLockId()
    {
        $this->_lockId = current(unpack('N', sha1($this->_lockId, true)));
    }
}