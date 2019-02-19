<?php
namespace Fgsl\Groupware\Groupbase\AreaLock;

use Fgsl\Groupware\Groupbase\DateTime;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * backend interface for area locks
 *
 * @package     Groupbase
 * @subpackage  AreaLock
 */
interface AreaLockInterface
{
    /**
     * @param string $area
     * @return DateTime
     */
    public function saveValidAuth($area);

    /**
     * @param string $area
     * @return bool
     * @throws \Exception
     */
    public function hasValidAuth($area);

    /**
     * @param $area
     * @return bool|DateTime
     */
    public function getAuthValidity($area);

    /**
     * @param string $area
     */
    public function resetValidAuth($area);
}