<?php
namespace Fgsl\Groupware\Groupbase\AreaLock;

use Fgsl\Groupware\Groupbase\Controller\ControllerInterface;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Model\AreaLockState;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\DateTime;
use Fgsl\Groupware\Groupbase\Exception\AreaUnlockFailed;
use Fgsl\Groupware\Groupbase\Auth\AuthInterface;
use Fgsl\Groupware\Groupbase\Model\AreaLockConfig;
use Fgsl\Groupware\Groupbase\Auth\AuthFactory;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Auth\Auth;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * AreaLock facility
 *
 * - handles locking/unlocking of certain "areas" (could be login, apps, data safe, ...)
 * - areas can be locked with Auth_AreaLock_*
 * - @todo add more doc
 *
 * @package     Groupbase
 * @subpackage  AreaLock
 */
class AreaLock implements ControllerInterface
{
    /**
     * holds the instance of the singleton
     *
     * @var AreaLock
     */
    private static $_instance = NULL;

    protected $_hasLocks = [];

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone()
    {
    }

    /**
     * the singleton pattern
     *
     * @return AreaLock
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new AreaLock();
        }

        return self::$_instance;
    }

    /**
     * destroy instance of this class
     */
    public static function destroyInstance()
    {
        self::$_instance = NULL;
    }

    /**
     * constructor
     */
    private function __construct()
    {
    }

    /**
     * returns area lock status
     */
    public static function getStatus()
    {
        $status = [
            'active' => false,
            'problems' => [],
        ];

        $areaConfigs = Config::getInstance()->get(Config::AREA_LOCKS);
        $status['active'] = $areaConfigs && $areaConfigs->records && count($areaConfigs->records) > 0;

        // @todo check configs + backends

        return $status;
    }

    /**
     * @param $area
     * @return AreaLockState
     */
    public function lock($area)
    {
        if ($this->_hasValidAuth($area)) {
            $this->resetValidAuth($area);
        }

        return new AreaLockState([
            'area' => $area,
            'expires' => new DateTime('1970-01-01')
        ]);
    }

    /**
     * @param string $area
     * @param string $password
     * @param string $identity
     * @return AreaLockState
     * @throws AreaUnlockFailed
     *
     * @todo allow "non-authentication" providers?
     */
    public function unlock($area, $password, $identity = null)
    {
        $areaConfig = $this->getAreaConfig($area);
        if (! $areaConfig) {
            throw new AreaUnlockFailed('Config for area lock not found');
        }
        $authProvider = $this->_getAuthProvider($areaConfig);

        if (! $identity) {
            $user = Core::getUser();
            $identity = $user->accountLoginName;
        }
        $authProvider->setIdentity($identity)
            ->setCredential($password);
        $authResult = $authProvider->authenticate();

        if ($authResult->isValid()) {
            $expires =$this->_saveValidAuth($area, $areaConfig);
        } else {
            $teauf = new AreaUnlockFailed('Invalid authentication: ' . $authResult->getCode());
            $teauf->setArea($area);
            throw $teauf;
        }

        return new AreaLockState([
            'area' => $area,
            'expires' => $expires
        ]);
    }

    /**
     * @param AreaLockConfig $areaConfig
     * @return AuthInterface
     */
    protected function _getAuthProvider(AreaLockConfig $areaConfig)
    {
        switch (strtolower($areaConfig->provider)) {
            case AreaLockConfig::PROVIDER_PIN:
                $authProvider = AuthFactory::factory(Auth::PIN);
                break;
            case AreaLockConfig::PROVIDER_USERPASSWORD:
                $authProvider = Auth::getInstance()->getBackend();
                break;
            case AreaLockConfig::PROVIDER_TOKEN:
                if (! isset($areaConfig->provider_config) || ! isset($areaConfig->provider_config['adapter'])) {
                    throw new UnexpectedValue('"adapter" needs to be set in provider_config');
                }
                $authProvider = AuthFactory::factory($areaConfig->provider_config['adapter'], $areaConfig->provider_config);
                break;
            default:
                throw new UnexpectedValue('no valid area lock provider given');
        }

        return $authProvider;
    }

    /**
     * @param string $area
     * @return AreaLockConfig
     * @throws NotFound
     */
    public function getAreaConfig($area)
    {
        $areaConfigs = Config::getInstance()->get(Config::AREA_LOCKS);
        $areaConfig = $areaConfigs && $areaConfigs->records
            ? $areaConfigs->records->filter('area', $area)->getFirstRecord()
            : null;

        return $areaConfig;
    }

    /**
     * @param string $area
     * @return bool
     */
    public function hasLock($area)
    {
        if (in_array($area, $this->_hasLocks)) {
            return true;
        }

        if ($this->getAreaConfig($area)) {
            $this->_hasLocks[] = $area;
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $area
     * @return bool
     */
    public function isLocked($area)
    {
        return !$this->_hasValidAuth($area);
    }

    /**
     * @param $area
     * @return AreaLockState
     */
    public function getState($area)
    {
        $expires = $this->_getAuthValidity($area);

        return new AreaLockState([
            'area' => $area,
            'expires' => $expires ? $expires : new DateTime('1970-01-01')
        ]);
    }

    /**
     * @return RecordSet of AreaLockState
     */
    public function getAllStates()
    {
        $states = new RecordSet(AreaLockState::class);
        $areaConfigs = Config::getInstance()->get(Config::AREA_LOCKS);
        if ($areaConfigs->records) {
            foreach ($areaConfigs->records as $areaConfig) {
                $states->addRecord($this->getState($areaConfig->area));
            }
        }
        return $states;
    }

    /**
     * @param string $area
     * @param AreaLockConfig $config
     * @return DateTime
     * @throws InvalidArgument
     */
    protected function _saveValidAuth($area, AreaLockConfig $config)
    {
        $alBackend = $this->_getBackend($config);
        $sessionValidity = $alBackend ? $alBackend->saveValidAuth($area) : DateTime::now();

        return $sessionValidity;
    }

    /**
     * @param $config
     * @return null|AreaLockInterface
     * @throws InvalidArgument
     */
    protected function _getBackend($config)
    {
        switch (strtolower($config->validity)) {
            case AreaLockConfig::VALIDITY_SESSION:
            case AreaLockConfig::VALIDITY_LIFETIME:
                $backend = new Session($config);
                break;
            case AreaLockConfig::VALIDITY_PRESENCE:
                $backend = new Presence($config);
                break;
            case AreaLockConfig::VALIDITY_DEFINEDBYPROVIDER:
                // @todo add support
                throw new InvalidArgument('validity ' . $config->validity . ' not supported yet');
                break;
            default:
                // no persistent backend
                $backend = null;
        }

        return $backend;
    }

    /**
     * @param string $area
     * @return bool
     * @throws \Exception
     */
    protected function _hasValidAuth($area)
    {
        $config = $this->getAreaConfig($area);
        if (! $config) {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                . __LINE__ . ' Config not found for area ' . $area);
            return false;
        }
        $alBackend = $this->_getBackend($config);
        return $alBackend ? $alBackend->hasValidAuth($area) : false;
    }

    /**
     * @param $area
     * @return bool|DateTime
     */
    protected function _getAuthValidity($area)
    {
        $config = $this->getAreaConfig($area);
        if (! $config) {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                . __LINE__ . ' Config not found for area ' . $area);
            return false;
        }
        $alBackend = $this->_getBackend($config);
        return $alBackend ? $alBackend->getAuthValidity($area) : false;
    }

    /**
     * @param string $area
     */
    public function resetValidAuth($area)
    {
        // invalidate class cache
        $this->_hasLocks = [];

        $config = $this->getAreaConfig($area);
        if ($config) {
            $alBackend = $this->_getBackend($config);
            if ($alBackend) {
                $alBackend->resetValidAuth($area);
            }
        }
    }
}
