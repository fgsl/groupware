<?php
namespace Fgsl\Groupware\Groupbase;
use Fgsl\Groupware\Groupbase\Controller\ControllerInterface;
use Fgsl\Groupware\Groupbase\Session\Session;
use Psr\Log\LogLevel;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Presence Facility - is updated by Fgsl\Groupware\Groupbase\Frontend\Json::reportPresence
 * - subscribers can register presence
 * - save presence in SESSION
 *
 * @package     Groupbase
 * @subpackage  Presence
 */
class Presence implements ControllerInterface
{
    /**
     * holds the instance of the singleton
     *
     * @var Presence
     */
    private static $_instance = NULL;

    /**
     * session namespace
     */
    const PRESENCE_SESSION_NAMESPACE = 'presence';

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
     * @return Presence
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Presence();
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
     * @param $key
     * @param integer $increment in seconds
     * @param bool $setLastPresence
     */
    public function setPresence($key, $increment, $setLastPresence = true)
    {
        $presenceKeys = Session::getSessionNamespace()->{self::PRESENCE_SESSION_NAMESPACE};
        if (! isset($presenceKeys) || ! is_array($presenceKeys)) {
            $presenceKeys = [];
        }

        $presenceKeys[$key] = [
            'increment' => $increment,
            'lastPresence' => ($setLastPresence) ? DateTime::now()->toString() : null
        ];

        Session::getSessionNamespace()->{self::PRESENCE_SESSION_NAMESPACE} = $presenceKeys;
    }

    /**
     * @param $key
     */
    public function resetPresence($key)
    {
        $presenceKeys = Session::getSessionNamespace()->{self::PRESENCE_SESSION_NAMESPACE};
        if (isset($presenceKeys)) {
            unset($presenceKeys[$key]);
            Session::getSessionNamespace()->{self::PRESENCE_SESSION_NAMESPACE} = $presenceKeys;
        }
    }

    /**
     * @param $key
     * @return null|DateTime
     */
    public function getLastPresence($key)
    {
        if (! Session::isStarted()) {
            return null;
        }

        $presenceKeys = Session::getSessionNamespace()->{self::PRESENCE_SESSION_NAMESPACE};
        if (isset($presenceKeys[$key]['lastPresence'])) {
            return new DateTime($presenceKeys[$key]['lastPresence']);
        } else {
            return null;
        }
    }

    /**
     * updates presence in all keys
     */
    public function reportPresence()
    {
        if (!Session::isStarted()) {
            if (Core::isLogLevel(LogLevel::INFO)) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' No session started');
            }
            return;
        }
        if (!Session::getSessionEnabled()) {
            if (Core::isLogLevel(LogLevel::INFO)) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Session not enabled');
            }
            return;
        }

        $presenceKeys = Session::getSessionNamespace()->{self::PRESENCE_SESSION_NAMESPACE};
        if (isset($presenceKeys) && is_array($presenceKeys)) {
            $now = DateTime::now()->toString();
            array_walk($presenceKeys, function (&$item, $key, $now) {
                $item['lastPresence'] = $now;
            }, $now);

            Session::getSessionNamespace()->{self::PRESENCE_SESSION_NAMESPACE} = $presenceKeys;
        }
    }
}