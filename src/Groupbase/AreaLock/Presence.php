<?php
namespace Fgsl\Groupware\Groupbase\AreaLock;

use Fgsl\Groupware\Groupbase\Presence as GroupbasePresence;
use Fgsl\Groupware\Groupbase\Model\AreaLockConfig;
use Fgsl\Groupware\Groupbase\DateTime;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * area locks presence backend
 *
 * @package     Groupbase
 * @subpackage  AreaLock
 */
class Presence implements AreaLockInterface
{
    /**
     * @var null|AreaLockConfig
     */
    protected $_config = null;

    /**
     * Groupbase_AreaLock_Presence constructor.
     * @param AreaLockConfig $config
     */
    public function __construct(AreaLockConfig $config)
    {
        if (! $config->lifetime) {
            // set lifetime default
            $config->lifetime = 15;
        }
        $this->_config = $config;
    }

    /**
     * @param string $area
     * @return DateTime
     * @throws InvalidArgument
     */
    public function saveValidAuth($area)
    {
        $lifetimeSeconds = $this->_config->lifetime * 60;
        $validity = DateTime::now()->addSecond($lifetimeSeconds);
        GroupbasePresence::getInstance()->setPresence(__CLASS__ . '#' . $area, $lifetimeSeconds);
        return $validity;
    }

    /**
     * @param $area
     * @return bool
     */
    public function hasValidAuth($area)
    {
        if ($validUntil = $this->getAuthValidity($area)) {
            return DateTime::now()->isEarlier($validUntil);
        } else {
            return false;
        }
    }

    /**
     * @param $area
     * @return bool|DateTime
     */
    public function getAuthValidity($area)
    {
        $lastPresence = GroupbasePresence::getInstance()->getLastPresence(__CLASS__ . '#' . $area);
        $lifetimeSeconds = $this->_config->lifetime * 60;
        return $lastPresence ? $lastPresence->addSecond($lifetimeSeconds) : false;
    }

    /**
     * @param string $area
     */
    public function resetValidAuth($area)
    {
        GroupbasePresence::getInstance()->resetPresence(__CLASS__ . '#' . $area);
    }
}
