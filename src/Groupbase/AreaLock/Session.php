<?php
namespace Fgsl\Groupware\Groupbase\AreaLock;
use Fgsl\Groupware\Groupbase\Model\AreaLockConfig;
use Fgsl\Groupware\Groupbase\DateTime;
use Fgsl\Groupware\Groupbase\Session\Session as GroupbaseSession;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * area locks session backend
 *
 * @package     Groupbase
 * @subpackage  AreaLock
 */
class Session implements AreaLockInterface
{
    /**
     * session namespace
     */
    const AREALOCK_VALIDITY_SESSION_NAMESPACE = 'areaLockValidity';

    /**
     * @var null|AreaLockConfig
     */
    protected $_config = null;

    /**
     * Tinebase_AreaLock_Session constructor.
     * @param AreaLockConfig $config
     */
    public function __construct(AreaLockConfig $config)
    {
        $this->_config = $config;
    }

    /**
     * @param string $area
     * @return DateTime
     * @throws InvalidArgument
     */
    public function saveValidAuth($area)
    {
        switch (strtolower($this->_config->validity)) {
            case AreaLockConfig::VALIDITY_SESSION:
                $sessionValidity = new DateTime('2150-01-01');
                break;
            case AreaLockConfig::VALIDITY_LIFETIME:
                $lifetimeMinutes = $this->_config->lifetime;
                $lifetimeSeconds = $lifetimeMinutes ? $lifetimeMinutes * 60  : 15 * 60;
                $sessionValidity = DateTime::now()->addSecond($lifetimeSeconds);
                break;
            default:
                throw new InvalidArgument('validity ' . $this->_config->validity . ' not supported');
        }

        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' saveValidAreaLock until ' . $sessionValidity->toString() . ' (' . $this->_config->validity . ')');
        GroupbaseSession::getSessionNamespace()->{self::AREALOCK_VALIDITY_SESSION_NAMESPACE}[$area] = $sessionValidity->toString();

        return $sessionValidity;
    }

    /**
     * @param $area
     * @return bool
     */
    public function hasValidAuth($area)
    {
        if (!GroupbaseSession::isStarted()) {
            if (Core::isLogLevel(LogLevel::INFO)) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' No session started to check auth in session');
            }
            return true;
        }
        if (!GroupbaseSession::getSessionEnabled()) {
            if (Core::isLogLevel(LogLevel::INFO)) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Session not enabled to check auth in session');
            }
            return true;
        }

        if ($validUntil = $this->getAuthValidity($area)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " valid until: " . $validUntil . ' now: ' . DateTime::now());

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
        $areaLocksInSession = Session::getSessionNamespace()->{self::AREALOCK_VALIDITY_SESSION_NAMESPACE};
        if (!isset($areaLocksInSession[$area])) {
            return false;
        }

        $currentValidUntil = $areaLocksInSession[$area];
        if (is_string($currentValidUntil)) {
            return new DateTime($currentValidUntil);
        }

        return false;
    }

    /**
     * @param string $area
     */
    public function resetValidAuth($area)
    {
        $areaLocksInSession = Session::getSessionNamespace()->{self::AREALOCK_VALIDITY_SESSION_NAMESPACE};
        if (isset($areaLocksInSession[$area])) {
            unset($areaLocksInSession[$area]);
            Session::getSessionNamespace()->{self::AREALOCK_VALIDITY_SESSION_NAMESPACE} = $areaLocksInSession;
        }
    }
}
