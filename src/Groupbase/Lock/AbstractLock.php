<?php
namespace Fgsl\Groupware\Groupbase\Lock;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Abstract lock implementation
 *
 * @package     Groupbase
 * @subpackage  Lock
 */
abstract class AbstractLock implements LockInterface
{
    protected $_lockId;

    /**
     * @var bool
     */
    protected $_isLocked = false;

    /**
     * @param string $lockId
     */
    public function __construct($_lockId)
    {
        $this->_lockId = $_lockId;
        $this->processLockId();
    }

    protected function processLockId()
    {
        $this->_lockId = sha1($this->_lockId);
    }

    /**
     * @return bool
     */
    public function isLocked()
    {
        return $this->_isLocked;
    }
}