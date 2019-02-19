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
 * Lock interface
 *
 * @package     Tinebase
 * @subpackage  Lock
 */
interface LockInterface
{
    /**
     * @param string $lockId
     */
    public function __construct($lockId);

    /**
     * @return bool
     */
    public function tryAcquire();

    /**
     * @return bool
     */
    public function release();

    /**
     * @return bool
     */
    public function isLocked();

    public function keepAlive();
}