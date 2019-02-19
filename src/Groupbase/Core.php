<?php
namespace Fgsl\Groupware\Groupbase;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
use Fgsl\Groupware\Groupbase\Acl\AbstractRights;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Application\Controller as ApplicationController;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Exception\Backend\Database;
use Fgsl\Groupware\Groupbase\Locale\Locale;
use Fgsl\Groupware\Groupbase\Log\Formatter;
use Fgsl\Groupware\Groupbase\Log\Log;
use Fgsl\Groupware\Groupbase\Model\Config as ModelConfig;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Server\ServerInterface;
use Fgsl\Groupware\Groupbase\Session\Session;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LogLevel;
use Sabre\DAV\Exception\NotFound;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Zend\Cache\StorageFactory;
use Zend\Config\Config;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Http\Request;
use Zend\Log\Filter\Priority;
use Zend\Log\Writer\AbstractWriter;
use Zend\Log\Writer\Mock;
use Zend\Log\Writer\Syslog;
use Zend\Session\SessionManager;
use Fgsl\Groupware\Groupbase\Exception\Backend\Database as ExceptionDatabase; 
use Zend\Db\Adapter\AdapterServiceFactory;
use Fgsl\Groupware\Groupbase\Controller\AbstractController;
use Fgsl\Groupware\Groupbase\Controller\Record\AbstractRecord as AbstractControllerRecord;

/**
 * dispatcher and initialisation class (functions are static)
 * - dispatchRequest() function
 * - initXYZ() functions
 * - has registry and config
 *
 * @package     Groupware
 * @subpackage  Server
 */
class Core
{
    

    /**
     * ************** registry indexes ************************
     */

    /**
     * constant for config registry index
     */
    const CONFIG = 'configFile';

    /**
     * constant for locale registry index
     */
    const LOCALE = 'locale';

    /**
     * constant for logger registry index
     */
    const LOGGER = 'logger';

    /**
     * constant for loglevel registry index
     */
    const LOGLEVEL = 'loglevel';

    /**
     * constant for cache registry index
     */
    const CACHE = 'cache';

    /**
     * constant for shared cache registry index
     */
    const SHAREDCACHE = 'sharedCache';

    /**
     * constant for session namespace (tinebase) registry index
     */
    const SESSION = 'session';

    /**
     * session id constant
     */
    const SESSIONID = 'sessionId';

    /**
     * constant for application start time in ms registry index
     */
    const STARTTIME = 'starttime';

    const REQUEST = 'request';

    /**
     * constant for current account/user
     */
    const USER = 'currentAccount';

    /**
     * const for current users credentialcache
     */
    const USERCREDENTIALCACHE = 'usercredentialcache';

    /**
     * const for current users access log
     */
    const USERACCESSLOG = 'useraccesslog';

    /**
     * constant for database adapter
     */
    const DB = 'dbAdapter';

    /**
     * constant for database adapter name
     */
    const DBNAME = 'dbAdapterName';

    /**
     * constant for database adapter
     */
    const USERTIMEZONE = 'userTimeZone';

    /**
     * constant for preferences registry
     */
    const PREFERENCES = 'preferences';

    /**
     * constant for preferences registry
     */
    const SCHEDULER = 'scheduler';

    /**
     * constant temp dir registry
     */
    const TMPDIR = 'tmpdir';

    /**
     * constant temp dir registry
     */
    const FILESDIR = 'filesdir';

    /**
     * constant for request method registry
     */
    const METHOD = 'method';

    /**
     * const PDO_MYSQL
     */
    const PDO_MYSQL = 'Pdo_Mysql';

    /**
     * minimal version of MySQL supported
     */
    const MYSQL_MINIMAL_VERSION = '5.0.0';

    /**
     * const PDO_PGSQL
     */
    const PDO_PGSQL = 'Pdo_Pgsql';

    /**
     * minimal version of PostgreSQL supported
     */
    const PGSQL_MINIMAL_VERSION = '8.4.8';

    /**
     * const PDO_OCI
     */
    const PDO_OCI = 'Pdo_Oci';

    /**
     * const ORACLE
     * Zend_Db adapter name for the oci8 driver.
     */
    const ORACLE = 'Oracle';

    /**
     * minimal version of Oracle supported
     */
    const ORACLE_MINIMAL_VERSION = '9.0.0';

    /**
     * Key for storing server plugins into cache
     *
     * @var string
     */
    const TINEBASE_SERVER_PLUGINS = 'Tinebase_Server_Plugins';

    /**
     * name of frontend server class
     *
     * @var string
     */
    const SERVER_CLASS_NAME = 'serverclassname';

    /**
     * constant for containe registry
     */
    const CONTAINER = 'container';

    /**
     * Application Instance Cache
     *
     * @var array
     */
    protected static $appInstanceCache = array();

    /**
     * current cache status, maybe NULL / uninitialized, true / enabled, false / disabled
     *
     * @var boolean
     */
    protected static $cacheStatus = NULL;

    /**
     * variable to cache value of logLevel during request
     *
     * @var int
     */
    protected static $logLevel = null;

    /**
     * Server classes provided by applications
     *
     * @var array
     */
    protected static $_serverPlugins = array();

    /**
     * contains the delegates fetched from configuration
     *
     * @var array
     */
    protected static $_delegatorCache = array();

    /**
     * whether this instance is a replication master or not.
     * if no replication configured, its a master
     *
     * @var bool
     */
    protected static $_isReplicationMaster = null;

    /**
     * whether this instance is a replication slave or not.
     * if no replication configured, its a master
     *
     * @var bool
     */
    protected static $_isReplicationSlave = null;

    /**
     * ***************************** DISPATCH ********************************
     */

    /**
     * dispatch request
     */
    public static function dispatchRequest()
    {
        // NOTE: we put the request in the registry here - should be kept in mind when we implement more
        // middleware pattern / expressive functionality as each handler might create a new request / request is modified
        $request = self::getRequest();

        // check transaction header
        if ($request->getHeaders()->has('X-GROUPWARE-TRANSACTIONID')) {
            $transactionId = $request->getHeaders()
                ->get('X-GROUPWARE-TRANSACTIONID')
                ->getFieldValue();
            if (Core::isLogLevel(LogLevel::DEBUG))
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Client transaction $transactionId");
            Formatter::setPrefix(substr($transactionId, 0, 5));
        }

        $server = self::getDispatchServer($request);

        $server->handle($request);
        $method = get_class($server) . '::' . $server->getRequestMethod();
        self::set(self::METHOD, $method);

        self::finishProfiling();
        self::getDbProfiling();
    }

    /**
     * dispatch request
     *
     * @param \Zend\Http\Request $request
     * @return null|ServerInterface
     * @throws Exception
     */
    public static function getDispatchServer(\Zend\Http\Request $request)
    {
        // Test server conditions from server plugins
        foreach (self::_getServerPlugins() as $serverPlugin) {
            $server = call_user_func_array(array(
                $serverPlugin,
                'getServer'
            ), array(
                $request
            ));

            if ($server instanceof ServerInterface) {
                Core::set('serverclassname', get_class($server));

                return $server;
            }
        }

        if (Core::isLogLevel(LogLevel::NOTICE)) {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " Request: " . $request->toString());
        }
        throw new Exception('No valid server found for request');
    }

    /**
     * returns TRUE if request is HTTPS
     *
     * @return boolean
     */
    public static function isHttpsRequest()
    {
        return (! empty($_SERVER['HTTPS']) && strtoupper($_SERVER['HTTPS']) != 'OFF');
    }

    /**
     * enable profiling
     * - supports xhprof
     */
    public static function enableProfiling()
    {
        if (! self::getConfig() || ! self::getConfig()->profiler) {
            return;
        }

        $config = self::getConfig()->profiler;

        if ($config && $config->xhprof) {
            $XHPROF_ROOT = $config->path ? $config->path : '/usr/share/php5-xhprof';
            if (file_exists($XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php")) {
                define('XHPROF_LIB_ROOT', $XHPROF_ROOT . '/xhprof_lib');
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Enabling xhprof');
                xhprof_enable(XHPROF_FLAGS_MEMORY, array());
            } else {
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Could not find xhprof lib root');
            }
        }
    }

    /**
     * finish profiling / save profiling data to a file
     * - supports xhprof
     */
    public static function finishProfiling()
    {
        if (! self::getConfig() || ! self::getConfig()->profiler) {
            return;
        }

        $config = self::getConfig()->profiler;
        $method = self::get(self::METHOD);

        if ($config->xhprof) {
            $xhprof_data = xhprof_disable();

            if ($config->method) {
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Filtering xhprof profiling method: ' . $config->method);
                if (! preg_match($config->method, $method)) {
                    Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Method mismatch, do not save profiling info.');
                    return;
                }
            }

            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Saving xhprof profiling run for method ' . $method);

            if (! defined('XHPROF_LIB_ROOT')) {
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . '  ' . print_r($xhprof_data, TRUE));
            } else {
                /**
                 *
                 * @noinspection PhpUndefinedClassInspection
                 */
                $xhprof_runs = new \XHProfRuns_Default();
                /**
                 *
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $runId = $xhprof_runs->save_run($xhprof_data, "tine");
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' xhprof runid: ' . $runId);
            }
        }
    }

    /**
     * ***************************** APPLICATION ***********************************
     */

    /**
     * returns an instance of the controller of an application
     *
     * ATTENTION if ever refactored, this is called via ActionQueue with the single parameter 'Tinebase_FOO_User' to get Tinebase_User (triggered in Tinebase_User_Sql::deleteUserInSqlBackend()
     * Tinebase_FOO_FileSystem
     *
     * @param string $_applicationName
     *            appname / modelname
     * @param string $_modelName
     * @param boolean $_ignoreACL
     * @return AbstractController|AbstractControllerRecord the controller of the application
     * @throws AccessDenied
     * @throws NotFound
     */
    public static function getApplicationInstance($_applicationName, $_modelName = '', $_ignoreACL = FALSE)
    {
        if (Core::isLogLevel(LogLevel::TRACE))
            Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Params: application: ' . $_applicationName . ' / model: ' . $_modelName);

        $cacheKey = $_applicationName . '_' . $_modelName . '_' . ($_ignoreACL ? 1 : 0);
        if (isset(self::$appInstanceCache[$cacheKey])) {
            return self::$appInstanceCache[$cacheKey];
        }

        $extract = Application::extractAppAndModel($_applicationName, $_modelName);
        $appName = $extract['appName'];
        $modelName = $extract['modelName'];

        $controllerName = ucfirst((string) $appName);
        if ($appName !== 'Tinebase' || ($appName === 'Tinebase' && ! $modelName)) {
            // only app controllers are called "App_Controller_Model"
            $controllerName .= '_Controller';
        }

        if (Core::isLogLevel(LogLevel::TRACE))
            Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' controllerName: ' . $controllerName);

        if (! empty($modelName)) {
            if (Core::isLogLevel(LogLevel::TRACE))
                Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Checking for model controller ...');

            $modelName = preg_replace('/^' . $appName . '_' . 'Model_/', '', $modelName);
            $controllerNameModel = $controllerName . '_' . $modelName;
            if (! class_exists($controllerNameModel)) {
                throw new NotFound('No Application Controller found (checked class ' . $controllerNameModel . ')!');
            } else {
                $controllerName = $controllerNameModel;
            }
        } else if (! class_exists($controllerName)) {
            if (Core::isLogLevel(LogLevel::TRACE))
                Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Use generic application controller');

            $controller = new ApplicationController($appName);
        }

        if (! $_ignoreACL && is_object(Core::getUser()) && ! Core::getUser()->hasRight($appName, AbstractRights::RUN)) {
            if (Core::isLogLevel(LogLevel::INFO))
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' User ' . Core::getUser()->accountDisplayName . '/' . Core::getUser()->getId() . ' has no right to access ' . $appName);
            throw new AccessDenied('No right to access application ' . $appName);
        }

        if (! isset($controller)) {
            if (Core::isLogLevel(LogLevel::TRACE))
                Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Getting instance of ' . $controllerName);

            $controller = call_user_func(array(
                $controllerName,
                'getInstance'
            ));
            self::$appInstanceCache[$cacheKey] = $controller;
        }

        return $controller;
    }

    /**
     * ***************************** SETUP ***********************************
     */

    /**
     * init tine framework
     */
    public static function initFramework()
    {
        Core::setupBuildConstants();

        self::setupSentry();

        if (Core::isLogLevel(LogLevel::INFO)) {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' initializing framework (' . 'PID: ' . getmypid() . ')');
        }

        // avoid autostart of sessions
        $config = new Config(['strict' => true]);
        $sessionManager = new SessionManager($config);
        $sessionManager->writeClose();

        Core::setupTempDir();

        Core::setupStreamWrapper();

        // Cache must be setup before User Locale because otherwise Zend_Locale tries to setup
        // its own cache handler which might result in a open_basedir restriction depending on the php.ini settings
        Core::setupCache();

        // setup a temporary user locale. This will be overwritten later but we
        // need to handle exceptions during initialisation process such as session timeout
        // @todo add fallback locale to config file
        Core::set('locale', new Locale('en_US'));

        Core::setupUserLocale();

        Core::enableProfiling();

        if (PHP_SAPI !== 'cli') {
            header('X-API: http://www.tine20.org/apidocs/tine20/');
            if (isset($_SERVER['HTTP_X_TRANSACTIONID'])) {
                header('X-TransactionID: ' . substr($_SERVER['HTTP_X_TRANSACTIONID'], 1, - 1) . ';' . $_SERVER['SERVER_NAME'] . ';16.4.5009.816;' . date('Y-m-d H:i:s') . ' UTC;265.1558 ms');
            }
        }

        Core::setupContainer();
    }

    protected static function setupContainer()
    {
        if (self::isRegistered(self::CONTAINER)) {
            return;
        }

        $cacheFile = self::getCacheDir() . '/cachedGroupwareContainer.php';

        // be aware of race condition between is_file and include_once => somebody may have deleted the file
        // yes it does get deleted! => check result of include_once
        if (TINE20_BUILDTYPE !== 'DEVELOPMENT' && is_file($cacheFile) && true === @include_once ($cacheFile) && class_exists('Tine20Container')) {
            /**
             *
             * @noinspection PhpUndefinedClassInspection
             */
            $preCompiledContainer = 'GroupwareContainer';
            $container = new $preCompiledContainer();
            
        } else {
            $container = self::getPreCompiledContainer();
            $container->compile();

            $dumper = new PhpDumper($container);
            file_put_contents($cacheFile, $dumper->dump(array(
                'class' => '\GroupwareContainer'
            )));
        }

        self::setContainer($container);
    }

    /**
     *
     * @return ContainerBuilder
     */
    public static function getPreCompiledContainer()
    {
        $container = new ContainerBuilder();

        $container->register(RequestInterface::class)->setFactory('\Zend\Diactoros\ServerRequestFactory::fromGlobals');

        try {
            $applications = Application::getInstance()->getApplications();
        } catch (Exception $e) {
            // Tinebase is not yet installed
            return $container;
        }

        /** @var Tinebase_Model_Application $application */
        foreach ($applications->filter('status', Application::ENABLED) as $application) {
            /** @var AbstractController $className */
            $className = $application->name . '_Controller';
            if (class_exists($className)) {
                $className::registerContainer($container);
            }
        }

        return $container;
    }

    /**
     *
     * @param \Psr\Container\ContainerInterface $container
     * @throws InvalidArgument
     */
    public static function setContainer(\Psr\Container\ContainerInterface $container)
    {
        self::set(self::CONTAINER, $container);
    }

    /**
     *
     * @return \Psr\Container\ContainerInterface
     */
    public static function getContainer()
    {
        return self::get(self::CONTAINER);
    }

    /**
     * start core session
     *
     * @throws Exception
     */
    public static function startCoreSession()
    {
        if (! Session::isStarted()) {
            Session::setSessionBackend();
            $tries = 0;
            while (true) {
                try {
                    $sessionManager = new SessionManager();
                    $sessionManager->start();
                    break;
                } catch (\RedisException $re) {
                    if (++ $tries < 5) {
                        // wait 100ms on the redis
                        usleep(100000);
                    } else {
                        throw $re;
                    }
                }
            }
        }

        $coreSession = Session::getSessionNamespace();

        if (isset($coreSession->currentAccount)) {
            self::set(self::USER, $coreSession->currentAccount);
        }

        if (! isset($coreSession->jsonKey)) {
            $coreSession->jsonKey = AbstractRecord::generateUID();
        }
        self::set('jsonKey', $coreSession->jsonKey);
        self::set(self::SESSION, $coreSession);
    }

    /**
     * return current session id
     *
     * @param boolean $generateUid
     * @return mixed|null
     */
    public static function getSessionId($generateUid = true)
    {
        if (! self::isRegistered(self::SESSIONID)) {
            $sessionId = null;
            // TODO allow to access Tinebase/Core methods with Setup session and remove this workaround
            if (Session::isStarted() && ! Session::isSetupSession()) {
                $sessionId = Session::getId();
            }
            if (empty($sessionId)) {
                $sessionId = 'NOSESSION';
                if ($generateUid) {
                    $sessionId .= AbstractRecord::generateUID(31);
                }
            }
            self::set(self::SESSIONID, $sessionId);
        }

        return self::get(self::SESSIONID);
    }

    /**
     * initializes the build constants like buildtype, package information, ...
     */
    public static function setupBuildConstants()
    {
        if (defined('TINE20_BUILDTYPE')) {
            // only define constants once
            return;
        }
        $config = self::getConfig();

        define('TINE20_BUILDTYPE', strtoupper($config->get('buildtype', 'DEVELOPMENT')));
        define('TINE20_CODENAME', Helper::getDevelopmentRevision());
        define('TINE20_PACKAGESTRING', 'none');
        define('TINE20_RELEASETIME', 'none');
    }

    /**
     * tines error exception handler for catchable fatal errors
     *
     * NOTE: PHP < 5.3 don't throws exceptions for Catchable fatal errors per default,
     * so we convert them into exceptions manually
     *
     * @param integer $severity
     * @param string $errstr
     * @param string $errfile
     * @param integer $errline
     * @throws \ErrorException
     */
    public static function errorHandler($severity, $errstr, $errfile, $errline)
    {
        if (error_reporting() == 0) {
            return;
        }

        $logLine = " $errstr in {$errfile}::{$errline} ($severity)";
        $e = new Exception('just to get trace');
        $trace = $e->getTraceAsString();

        switch ($severity) {
            case E_COMPILE_ERROR:
            case E_CORE_ERROR:
            case E_ERROR:
            case E_PARSE:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR:
                throw new \ErrorException($errstr, 0, $severity, $errfile, $errline);
                break;

            case E_COMPILE_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
            case E_WARNING:
                if (Core::isRegistered(Core::LOGGER)) {
                    Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . $logLine);
                    Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                } else {
                    error_log(__METHOD__ . '::' . __LINE__ . $logLine);
                    error_log(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                }
                break;

            case E_NOTICE:
            case E_STRICT:
            case E_USER_NOTICE:
            default:
                if (Core::isRegistered(Core::LOGGER)) {
                    Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . $logLine);
                    Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                } else {
                    error_log(__METHOD__ . '::' . __LINE__ . $logLine);
                    error_log(__METHOD__ . '::' . __LINE__ . ' ' . $trace);
                }
                break;
        }
    }

    /**
     * initializes the config
     */
    public static function setupConfig()
    {
        self::set(self::CONFIG, Config::getInstance());
    }

    /**
     * setup temp dir registry setting retrieved by {@see _getTempDir()}
     *
     * @return void
     */
    public static function setupTempDir()
    {
        self::set(self::TMPDIR, self::guessTempDir());
    }

    /**
     * figure out temp directory:
     * config.inc.php > sys_get_temp_dir > session_save_path > /tmp
     *
     * @param array $config
     * @return String
     */
    public static function guessTempDir($config = null)
    {
        if ($config === null) {
            $config = self::getConfig();
            $tmpdir = $config->tmpdir !== ModelConfig::NOTSET ? $config->tmpdir : null;
        } else {
            $tmpdir = isset($config['tmpdir']) ? $config['tmpdir'] : null;
        }

        // let's make the fall back dir more safe in a multi instance environment
        if (! $tmpdir || ! @is_writable($tmpdir)) {
            $append = '';
            if ($tmpdir) {
                // first lets try to create the configured tmpdir
                if (@mkdir($tmpdir, 0777, true) && null == clearstatcache() && @is_writable($tmpdir)) {
                    // yeaha, saved the day
                    return $tmpdir;
                }
                // we have a configured tmpdir, but it is not writable / doesn't exist => as it is different for each
                // instance in the environment, we use it to create a unique hash for this instance and append it to
                // the default fall back dir (which might be the same for each instance!)
                $append = '/' . md5($tmpdir);
            }
            $tmpdir = sys_get_temp_dir();
            if (empty($tmpdir) || ! @is_writable($tmpdir)) {
                $tmpdir = session_save_path();
                if (empty($tmpdir) || ! @is_writable($tmpdir)) {
                    $tmpdir = '/tmp';
                }
            }

            if ($append) {
                $tmpdir = rtrim($tmpdir, '/') . $append;
                @mkdir($tmpdir, 0777, true);
            }
        }

        return $tmpdir;
    }

    /**
     * initializes the logger
     *
     * @param $_defaultWriter AbstractWriter
     *            default log writer
     */
    public static function setupLogger(AbstractWriter $_defaultWriter = NULL)
    {
        $config = self::getConfig();
        $logger = new Log();

        if (isset($config->logger) && $config->logger->active) {
            try {
                if ($config->logger->tz) {
                    $logger->setTimezone($config->logger->tz);
                }
                $logger->addWriterByConfig($config->logger);
                if ($config->logger->additionalWriters) {
                    foreach ($config->logger->additionalWriters as $writerConfig) {
                        $logger->addWriterByConfig($writerConfig);
                    }
                }
            } catch (Exception $e) {
                error_log("Groupware can't setup the configured logger! The Server responded: $e");
                $writer = ($_defaultWriter === NULL) ? new Mock() : $_defaultWriter;
                $logger->addWriter($writer);
            }

            // For saving log into syslog too, create a key syslog into logger (value does not matter)
            if ((bool) $config->logger->syslog) {
                $writer = new Syslog(array(
                    'application' => 'Tine 2.0'
                ));
                $prio = ($config->logger->priority) ? (int) $config->logger->priority : 3;
                $filter = new Priority($prio);
                $writer->addFilter($filter);
                $logger->addWriter($writer);
            }
        } else {
            $writer = new Syslog(array(
                'application' => 'Tine 2.0'
            ));

            $filter = new Priority(LogLevel::WARN);
            $writer->addFilter($filter);
            $logger->addWriter($writer);
        }

        self::set(self::LOGGER, $logger);

        if (Core::isLogLevel(LogLevel::INFO))
            $logger->info(__METHOD__ . '::' . __LINE__ . ' Logger initialized.' . (isset($writer) ? ' Writer: ' . get_class($writer) : ''));
        if (isset($config->logger) && Core::isLogLevel(LogLevel::TRACE))
            $logger->trace(__METHOD__ . '::' . __LINE__ . ' Logger settings: ' . print_r($config->logger->toArray(), TRUE));
    }

    /**
     *
     * @param null|Config $config
     * @return string
     */
    public static function getCacheDir($config = null)
    {
        if (null === $config) {
            $config = self::getConfig();
        }

        return $config->caching && $config->caching->path ? $config->caching->path : self::getTempDir();
    }

    /**
     * setup the cache and add it to zend registry
     *
     * @param bool $_enabled
     *            disabled cache regardless what's configured in config.inc.php
     *            
     * @todo use the same config keys as Zend_Cache (backend + frontend) to simplify this
     */
    public static function setupCache($_enabled = true)
    {
        if (self::$cacheStatus !== NULL && self::$cacheStatus === $_enabled) {
            return;
        }

        $config = self::getConfig();
        if ($config->caching && $config->caching->active) {
            if (isset($config->caching->shared) && ($config->caching->shared === true)) {
                self::set(self::SHAREDCACHE, true);
            } else {
                self::set(self::SHAREDCACHE, false);
            }
        }

        $backendOptions = array();

        // create zend cache
        if ($_enabled === true && $config->caching && $config->caching->active) {
            $logging = isset($config->caching->logging) ? $config->caching->logging : false;

            if ($logging) {
                if (Core::isLogLevel(LogLevel::DEBUG))
                    Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Cache logging enabled");
                $logger = self::getLogger();
            } else {
                if (Core::isLogLevel(LogLevel::DEBUG))
                    Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Cache logging disabled");
                $logger = null;
            }

            $frontendOptions = array(
                'lifetime' => ($config->caching->lifetime) ? $config->caching->lifetime : 7200,
                'automatic_serialization' => true, // turn that off for more speed
                'caching' => true,
                'automatic_cleaning_factor' => 0, // no garbage collection as this is done by a scheduler task
                'write_control' => false, // don't read cache entry after it got written
                'logging' => $logging,
                'logger' => $logger
            );

            $backendType = ($config->caching->backend) ? ucfirst($config->caching->backend) : 'File';
            $backendOptions = ($config->caching->backendOptions) ? $config->caching->backendOptions->toArray() : array();

            if (empty($backendOptions)) {
                switch ($backendType) {
                    case 'File':
                        $backendOptions = array(
                            'cache_dir' => self::getCacheDir($config),
                            'hashed_directory_level' => ($config->caching->dirlevel) ? $config->caching->dirlevel : 4,
                            'logging' => $logging,
                            'logger' => $logger
                        );
                        break;

                    case 'Memcached':
                        $host = $config->caching->host ? $config->caching->host : (isset($config->caching->memcached->host) ? $config->caching->memcached->host : 'localhost');
                        $port = $config->caching->port ? $config->caching->port : (isset($config->caching->memcached->port) ? $config->caching->memcached->port : 11211);
                        $backendOptions = array(
                            'servers' => array(
                                'host' => $host,
                                'port' => $port,
                                'persistent' => TRUE
                            )
                        );
                        break;

                    case 'Redis':
                        $host = $config->caching->host ? $config->caching->host : ($config->caching->redis && $config->caching->redis->host ? $config->caching->redis->host : 'localhost');
                        $port = $config->caching->port ? $config->caching->port : ($config->caching->redis && $config->caching->redis->port ? $config->caching->redis->port : 6379);
                        if ($config->caching && $config->caching->prefix) {
                            $prefix = $config->caching->prefix;
                        } else if ($config->caching && $config->caching->redis && $config->caching->redis->prefix) {
                            $prefix = $config->caching->redis->prefix;
                        } else {
                            $prefix = ($config->database && $config->database->tableprefix) ? $config->database->tableprefix : 'tine20';
                        }
                        $prefix .= '_CACHE_';
                        $backendOptions = array(
                            'servers' => array(
                                'host' => $host,
                                'port' => $port,
                                'prefix' => $prefix
                            )
                        );
                        break;

                    default:
                        $backendOptions = array();
                        break;
                }
            }

            if (Core::isLogLevel(LogLevel::INFO))
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " cache of backend type '{$backendType}' enabled");

            if (Core::isLogLevel(LogLevel::TRACE)) {
                Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " Caching options: " . print_r($config->caching->toArray(), TRUE));
            }
        } else {
            if (Core::isLogLevel(LogLevel::INFO))
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Cache disabled');
            $backendType = 'Test';
            $frontendOptions = array(
                'caching' => false
            );
        }

        // getting a Zend cache storage object
        try {
            $cache = StorageFactory::factory($backendOptions);
        } catch (Exception $e) {
            Exception::log($e);

            $enabled = false;
            if ('File' === $backendType && isset($backendOptions['cache_dir']) && ! is_dir($backendOptions['cache_dir'])) {
                if (Core::isLogLevel(LogLevel::INFO))
                    Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Create cache directory and re-try');
                if (@mkdir($backendOptions['cache_dir'], 0770, true)) {
                    $enabled = $_enabled;
                }
            }

            self::setupCache($enabled);
            return;
        }

        // some important caches
        Locale::setCache($cache);

        self::set(self::CACHE, $cache);
        self::$cacheStatus = $_enabled;
    }

    /**
     * setup stream wrapper for tine20:// prefix
     */
    public static function setupStreamWrapper()
    {
        if (empty(Core::getConfig()->filesdir)) {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " Filesdir config value not set. tine20:// streamwrapper not registered, virtual filesystem not available.");
            return;
        }
        if (! in_array('tine20', stream_get_wrappers())) {
            stream_wrapper_register('tine20', 'Tinebase_FileSystem_StreamWrapper');
        }
    }

    /**
     * initializes the database connection
     */
    public static function setupDatabaseConnection()
    {
        // check if database connection is setup already
        if (self::get(self::DB) instanceof AdapterInterface) {
            return self::get(self::DB);
        }

        // make sure cache is setup or we get in trouble in config_abstract. after db is available, cache needs to be present too!
        self::getCache();

        $config = self::getConfig();

        if (! isset($config->database)) {
            die("Database section not found in central configuration file.\n");
        }

        $dbConfig = $config->database;

        if (! empty($dbConfig->password)) {
            self::getLogger()->addReplacement($dbConfig->password);
        }

        if (! defined('SQL_TABLE_PREFIX')) {
            define('SQL_TABLE_PREFIX', $dbConfig->get('tableprefix') ? $dbConfig->get('tableprefix') : 'tine20_');
        }

        $db = Registry::get(SERVICE_MANAGER)->get(DB_ADAPTER);

        // place table prefix into the concrete adapter
        $db->table_prefix = SQL_TABLE_PREFIX;

        self::set(self::DB, $db);

        return $db;
    }

    /**
     * get db profiling
     *
     * Enable db profiling like this (in config.inc.php):
     *
     * 'database' =>
     * array(
     * [...] // db connection params
     * 'profiler' => TRUE
     * ),
     * 'profiler' =>
     * array(
     * 'queryProfiles' => TRUE,
     * 'queryProfilesDetails' => TRUE,
     * 'user' => 'loginname', // only profile this user
     * 'profilerFilterElapsedSecs' => 1, // only show queries whose elapsed time is equal or greater than this
     * )
     * ),
     */
    public static function getDbProfiling()
    {
        if (! self::getConfig() || ! self::getConfig()->database || ! (bool) self::getConfig()->database->profiler) {
            return;
        }

        $config = self::getConfig()->profiler;

        if ($config->user && is_object(self::getUser()) && $config->user !== self::getUser()->accountLoginName) {
            return;
        }

        $profiler = Registry::getService(Registry::DB_ADAPTER)->getProfiler();

        if (! empty($config->profilerFilterElapsedSecs)) {
            $profiler->setFilterElapsedSecs($config->profilerFilterElapsedSecs);
        }

        $data = array(
            'totalNumQueries' => $profiler->getTotalNumQueries(),
            'totalElapsedSec' => $profiler->getTotalElapsedSecs(),
            'longestTime' => 0,
            'longestQuery' => ''
        );

        if ($config && (bool) $config->queryProfiles) {
            $queryProfiles = $profiler->getQueryProfiles(null, true);
            if (is_array($queryProfiles)) {
                $data['queryProfiles'] = array();
                /** @var Zend_Db_Profiler_Query $profile */
                foreach ($queryProfiles as $profile) {
                    if ((bool) $config->queryProfilesDetails) {
                        $data['queryProfiles'][] = array(
                            'query' => $profile->getQuery(),
                            'elapsedSecs' => $profile->getElapsedSecs()
                        );
                    }

                    if ($profile->getElapsedSecs() > $data['longestTime']) {
                        $data['longestTime'] = $profile->getElapsedSecs();
                        $data['longestQuery'] = $profile->getQuery();
                    }
                }
                $profiler->clear();
            }
        }

        self::getLogger()->debug(__METHOD__ . ' (' . __LINE__ . ') value: ' . print_r($data, true));
    }

    /**
     * sets the user locale
     *
     * @param string $localeString
     */
    public static function setupUserLocale($localeString = 'auto')
    {
        try {
            $session = Session::getSessionNamespace();
        } catch (\Exception $zse) {
            $session = null;
        }

        if (self::isLogLevel(LogLevel::DEBUG))
            self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " given localeString '$localeString'");

        // get locale object from session or ...
        if ($session !== NULL && isset($session->userLocale) && is_object($session->userLocale) && ($session->userLocale->toString() === $localeString || $localeString === 'auto')) {
            $locale = $session->userLocale;

            if (self::isLogLevel(LogLevel::DEBUG))
                self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Got locale from session : " . (string) $locale);

            // ... create new locale object
        } else {
            if ($localeString === 'auto') {
                // check if cookie or pref with language is available
                if (isset($_COOKIE['TINE20LOCALE'])) {
                    $localeString = $_COOKIE['TINE20LOCALE'];
                    if (self::isLogLevel(LogLevel::DEBUG))
                        self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Got locale from cookie: '$localeString'");
                } elseif (isset($session->currentAccount)) {
                    $localeString = self::getPreference()->getValue(Tinebase_Preference::LOCALE, 'auto');
                    if (self::isLogLevel(LogLevel::DEBUG))
                        self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Got locale from preference: '$localeString'");
                } else {
                    if (self::isLogLevel(LogLevel::DEBUG))
                        self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Try to detect the locale of the user (browser, environment, default)");
                }
            }

            $locale = Tinebase_Translation::getLocale($localeString);

            // save in session
            if ($session !== NULL) {
                $session->userLocale = $locale;
            }

            // check if the detected locale should be saved in preferences
            if ($localeString === 'auto' && is_object(Core::getUser()) && (string) $locale !== 'en') {
                if (Core::isLogLevel(LogLevel::DEBUG))
                    self::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Saving locale: " . (string) $locale);
                self::getPreference()->{Tinebase_Preference::LOCALE} = (string) $locale;
            }
        }

        // save in registry
        self::set('locale', $locale);

        $localeString = (string) $locale;
        if (Core::isLogLevel(LogLevel::INFO))
            self::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Setting user locale: " . $localeString);

        // set correct ctype locale, to make sure that the filesystem functions like basename() are working correctly with utf8 chars
        $ctypeLocale = setlocale(LC_CTYPE, 0);
        if (! preg_match('/utf-?8/i', $ctypeLocale)) {
            // use en_US as fallback locale if region string is missing
            $newCTypeLocale = ((strpos($localeString, '_') !== FALSE) ? $localeString : 'en_US') . '.UTF8';
            if (Core::isLogLevel(LogLevel::DEBUG))
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Setting CTYPE locale from "' . $ctypeLocale . '" to "' . $newCTypeLocale . '".');
            setlocale(LC_CTYPE, $newCTypeLocale);
        }
    }

    /**
     * intializes the timezone handling
     *
     * @param string $_timezone
     * @param bool $_saveaspreference
     * @return string
     */
    public static function setupUserTimezone($_timezone = null, $_saveaspreference = FALSE)
    {
        try {
            $session = Session::getSessionNamespace();
        } catch (Zend_Session_Exception $zse) {
            $session = null;
        }

        // get timezone from session, parameter or preference
        if ($_timezone === null && $session instanceof Zend_Session_Namespace && isset($session->timezone)) {
            $timezone = $session->timezone;
        } else {
            $timezone = $_timezone;
        }
        if ($timezone === null) {
            if (Core::isLogLevel(LogLevel::DEBUG))
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' get timezone from preferences');
            $timezone = self::getPreference()->getValue(Tinebase_Preference::TIMEZONE);
        }

        // set timezone in registry, session and preference
        self::set(self::USERTIMEZONE, $timezone);
        if ($session instanceof Zend_Session_Namespace && Session::isWritable()) {
            $session->timezone = $timezone;
        }
        if ($_saveaspreference) {
            self::getPreference()->setValue(Tinebase_Preference::TIMEZONE, $timezone);
        }

        if (Core::isLogLevel(LogLevel::DEBUG))
            Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' User timezone: ' . $timezone);

        return $timezone;
    }

    /**
     * set php execution life (max) time
     *
     * @param int $_seconds
     * @return int old max execution time in seconds
     */
    public static function setExecutionLifeTime($_seconds)
    {
        $oldMaxExcecutionTime = ini_get('max_execution_time');

        if ($oldMaxExcecutionTime > 0) {
            if (Core::isRegistered(self::LOGGER) && Core::isLogLevel(LogLevel::DEBUG)) {
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' setting execution life time to: ' . $_seconds);
            }
            set_time_limit($_seconds);
        }

        return $oldMaxExcecutionTime;
    }

    /**
     * set php memory (max) limit
     *
     * @param string $_limit
     * @return string old max memory limit
     */
    public static function setMemoryLimit($_limit)
    {
        $oldMaxMemoryLimit = ini_get('memory_limit');

        if (! empty($oldMaxMemoryLimit)) {
            if (Core::isRegistered(self::LOGGER) && Core::isLogLevel(LogLevel::INFO)) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' setting memory limit to: ' . $_limit);
            }
            ini_set('memory_limit', $_limit);
        }

        return $oldMaxMemoryLimit;
    }

    /**
     * log memory usage
     */
    public static function logMemoryUsage()
    {
        if (function_exists('memory_get_peak_usage')) {
            $memory = memory_get_peak_usage(true);
        } else {
            $memory = memory_get_usage(true);
        }

        return ' Memory usage: ' . ($memory / 1024 / 1024) . ' MB';
    }

    public static function logCacheSize()
    {
        if (function_exists('realpath_cache_size')) {
            $realPathCacheSize = realpath_cache_size();
        } else {
            $realPathCacheSize = 'unknown';
        }

        return ' Real patch cache size: ' . $realPathCacheSize;
    }

    /**
     * ***************************** REGISTRY ***********************************
     */

    /**
     * get a value from the registry
     *
     * @param
     *            $index
     * @return mixed|null
     */
    public static function get($index)
    {
        try {
            return Registry::get($index);
        } catch (Zend_Exception $ze) {
            return null;
        }
    }

    /**
     * set a registry value
     *
     * @param string $index
     * @param mixed $value
     * @throws InvalidArgument
     */
    public static function set($index, $value)
    {
        if ($index === self::USER) {
            if ($value === null) {
                throw new InvalidArgument('Invalid user object!');
            }
            if (Core::isLogLevel(LogLevel::INFO)) {
                if ($value instanceof Tinebase_Model_FullUser) {
                    $userString = $value->accountLoginName;
                } else if ($value instanceof ModelUser) {
                    $userString = $value->accountDisplayName;
                } else {
                    $userString = var_export($value, true);
                }

                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Setting user ' . $userString);
                Formatter::resetUsername();
            }
        }

        Registry::set($index, $value);
    }

    /**
     * checks a registry value
     *
     * @param string $index
     * @return boolean
     */
    public static function isRegistered($index)
    {
        return Registry::isRegistered($index);
    }

    /**
     * Returns the auth typ from config or default value
     *
     * @return String
     */
    public static function getAuthType()
    {
        if (isset(Core::getConfig()->authentication)) {
            $authType = Core::getConfig()->authentication->get('backend', Tinebase_Auth::SQL);
        } else {
            $authType = Tinebase_Auth::SQL;
        }

        return ucfirst($authType);
    }

    /**
     * get config from the registry
     *
     * @return Config
     */
    public static function getConfig()
    {
        if (! self::get(self::CONFIG)) {
            self::setupConfig();
        }
        return self::get(self::CONFIG);
    }

    /**
     * get max configured loglevel
     *
     * @return integer
     */
    public static function getLogLevel()
    {
        if (! ($logLevel = self::get(self::LOGLEVEL))) {
            $config = self::getConfig();
            $logLevel = Tinebase_Log::getMaxLogLevel(isset($config->logger) ? $config->logger : NULL);
            self::set(self::LOGLEVEL, $logLevel);
        }

        return $logLevel;
    }

    /**
     * check if given loglevel should be logged
     *
     * @param integer $_prio
     * @return boolean
     */
    public static function isLogLevel($_prio)
    {
        if (! isset(self::$logLevel) || self::$logLevel === 0) {
            self::$logLevel = self::getLogLevel();
        }

        return self::$logLevel >= $_prio;
    }

    /**
     * get config from the registry
     *
     * @return Tinebase_Log the logger
     */
    public static function getLogger()
    {
        if (! self::get(self::LOGGER) instanceof Tinebase_Log) {
            Core::setupLogger();
        }

        return self::get(self::LOGGER);
    }

    /**
     * unset the logger
     */
    public static function unsetLogger()
    {
        self::$logLevel = 0;
        self::set(self::LOGLEVEL, 0);
        self::set(self::LOGGER, null);
    }

    /**
     * get cache from the registry
     *
     * @return Zend_Cache_Core the cache
     */
    public static function getCache()
    {
        if (! self::get(self::CACHE) instanceof Zend_Cache_Core) {
            self::$cacheStatus = null;
            Core::setupCache();
        }
        return self::get(self::CACHE);
    }

    /**
     * get credentials cache from the registry or initialize it
     *
     * @return Tinebase_Model_CredentialCache
     */
    public static function getUserCredentialCache()
    {
        if (! self::get(self::USERCREDENTIALCACHE) instanceof Tinebase_Model_CredentialCache && self::getUser()) {
            try {
                $cache = Tinebase_Auth_CredentialCache::getInstance()->getCacheAdapter()->getCache();
            } catch (Zend_Db_Statement_Exception $zdse) {
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Could not get credential cache adapter, perhaps Tine 2.0 is not installed yet");
                $cache = NULL;
            }

            if ($cache !== NULL) {
                self::set(self::USERCREDENTIALCACHE, $cache);
            }
        }

        return self::get(self::USERCREDENTIALCACHE);
    }

    /**
     * get locale from the registry
     *
     * @return Zend_Locale
     */
    public static function getLocale()
    {
        return self::get(self::LOCALE);
    }

    /**
     * get current user account
     *
     * @return Tinebase_Model_FullUser the user account record
     */
    public static function getUser()
    {
        return self::get(self::USER);
    }

    /**
     * unset user in registry
     */
    public static function unsetUser()
    {
        Registry::set(self::USER, null);
        Formatter::resetUsername();
    }

    /**
     * get the http request
     *
     * @return Request
     */
    public static function getRequest()
    {
        if (! self::isRegistered(self::REQUEST)) {
            $request = new Request();
            self::set(self::REQUEST, $request);
        }

        return self::get(self::REQUEST);
    }

    /**
     * get current users timezone
     *
     * @return string the current users timezone string
     */
    public static function getUserTimezone()
    {
        if (! self::isRegistered(self::USERTIMEZONE) || ($return = self::get(self::USERTIMEZONE)) === NULL) {
            return Core::setupUserTimezone();
        }

        return $return;
    }

    /**
     * get preferences instance by application name (create+save it to registry if it doesn't exist)
     *
     * @param string $_application
     * @param boolean $_throwException
     *            throws exception if class does not exist
     * @return Tinebase_Preference_Abstract
     * @throws NotFound
     */
    public static function getPreference($_application = 'Tinebase', $_throwException = FALSE)
    {
        $result = NULL;

        if (! self::isRegistered(self::PREFERENCES)) {
            self::set(self::PREFERENCES, array());
        }

        $prefs = self::get(self::PREFERENCES);

        if (isset($prefs[$_application])) {
            return $prefs[$_application];
        }

        $prefClassName = $_application . '_Preference';

        if (@class_exists($prefClassName)) {
            $result = new $prefClassName();
            $prefs[$_application] = $result;
            self::set(self::PREFERENCES, $prefs);
        } else if ($_throwException) {
            throw new NotFound('No preference class found for app ' . $_application);
        }

        return $result;
    }

    /**
     * get db adapter
     *
     * @return AdapterInterface
     */
    public static function getDb()
    {
        if (! self::get(self::DB) instanceof AdapterInterface) {
            Core::setupDatabaseConnection();
        }

        return self::get(self::DB);
    }

    /**
     * checks if the DB is registered already
     *
     * @return bool
     */
    public static function hasDb()
    {
        return self::isRegistered(self::DB);
    }

    /**
     * get db name
     *
     * @return string
     * @throws Exception
     */
    public static function getDbName()
    {
        if (! self::get(self::DBNAME)) {
            $db = self::getDb();
            $adapterName = get_class($db);

            if (empty($adapterName) || strpos($adapterName, '_') === FALSE) {
                throw new Exception('Could not get DB adapter name.');
            }

            $adapterNameParts = explode('_', $adapterName);
            $type = array_pop($adapterNameParts);

            // special handling for Oracle
            $type = str_replace('Oci', self::ORACLE, $type);
            self::set(self::DBNAME, $type);
        }

        return self::get(self::DBNAME);
    }

    /**
     * get temp dir string (without PATH_SEP at the end)
     *
     * @return string
     */
    public static function getTempDir()
    {
        return self::get(self::TMPDIR);
    }

    /**
     * returns protocol + hostname
     *
     * @return string
     */
    public static function getHostname()
    {
        $hostname = self::get('HOSTNAME');
        if (! $hostname) {
            if (empty($_SERVER['SERVER_NAME']) && empty($_SERVER['HTTP_HOST'])) {
                if (empty($url = Config::getInstance()->get(Config::TINE20_URL))) {
                    if (Core::isLogLevel(LogLevel::WARN))
                        Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' neither SERVER_NAME nor HTTP_HOST are set and tine20URL config is not set too!');
                    $hostname = 'http://'; // backward compatibility. This is what used to happen if you ask zend
                } else {
                    $hostname = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
                }
            } else {
                $request = new Zend_Controller_Request_Http();
                $hostname = $request->getScheme() . '://' . $request->getHttpHost();
            }

            self::set('HOSTNAME', $hostname);
        }

        return $hostname;
    }

    /**
     * returns requested url part
     *
     * @param string $part
     * @return string
     */
    public static function getUrl($part = 'full')
    {
        if (empty($url = Config::getInstance()->get(Config::TINE20_URL))) {
            if (empty($_SERVER['SERVER_NAME']) && empty($_SERVER['HTTP_HOST'])) {
                if (Core::isLogLevel(LogLevel::WARN))
                    Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' neither SERVER_NAME nor HTTP_HOST are set and tine20URL config is not set too!');
                $protocol = 'http://'; // backward compatibility. This is what used to happen if you ask zend
                $hostname = '';
                $pathname = '';
            } else {
                $request = new Zend_Controller_Request_Http();
                $pathname = $request->getBasePath();
                $hostname = $request->getHttpHost();
                $protocol = $request->getScheme();
            }
        } else {
            $protocol = parse_url($url, PHP_URL_SCHEME);
            $hostname = parse_url($url, PHP_URL_HOST);
            $pathname = rtrim(str_replace($protocol . '://' . $hostname, '', $url), '/');
        }

        switch ($part) {
            case 'path':
                $url = $pathname;
                break;
            case 'host':
                $url = $hostname;
                break;
            case 'protocol':
                $url = $protocol;
                break;
            case 'full':
            default:
                $url = $protocol . '://' . $hostname . $pathname;
                break;
        }

        return $url;
    }

    /**
     * Singleton instance
     *
     * @return Tinebase_Scheduler
     */
    public static function getScheduler()
    {
        if (! ($scheduler = self::get(self::SCHEDULER)) instanceof Tinebase_Scheduler) {
            $scheduler = Tinebase_Scheduler::getInstance();
            self::set(self::SCHEDULER, $scheduler);
        }
        return $scheduler;
    }

    /**
     * filter input string for database as some databases (looking at you, MySQL) can't cope with some chars
     *
     * @param string $string
     * @return string
     *
     * @see 0008644: error when sending mail with note (wrong charset)
     * @see http://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string/8215387#8215387
     * @see http://stackoverflow.com/questions/8491431/remove-4-byte-characters-from-a-utf-8-string
     */
    public static function filterInputForDatabase($string)
    {
        $string = Helper::mbConvertTo($string);

        if (($db = self::getDb()) instanceof Zend_Db_Adapter_Pdo_Mysql && ! Tinebase_Backend_Sql_Adapter_Pdo_Mysql::supportsUTF8MB4($db)) {
            // remove 4 byte utf8
            $string = preg_replace('/[\xF0-\xF7].../s', '?', $string);
        }

        return $string;
    }

    /**
     * checks if a system command exists.
     * Works on POSIX systems.
     *
     * @param string $name
     * @return bool
     */
    public static function systemCommandExists($name)
    {
        $ret = shell_exec('which ' . $name);
        return ! empty($ret);
    }

    /**
     * calls a system command with escapeshellcmd
     *
     * @param string $cmd
     * @return bool
     */
    public static function callSystemCommand($cmd)
    {
        return shell_exec(escapeshellcmd($cmd));
    }

    /**
     * Search server plugins from applications configuration
     *
     * @return array
     */
    protected static function _searchServerPlugins()
    {
        $cache = Core::getCache();

        if ($cache && $plugins = $cache->load(self::TINEBASE_SERVER_PLUGINS)) {
            return $plugins;
        }

        if (! Setup_Controller::getInstance()->isInstalled('Tinebase')) {
            // get apps by scanning directory
            $applications = array();
            $d = dir(dirname(__DIR__));
            while (false !== ($entry = $d->read())) {
                if ($entry[0] == '.') {
                    continue;
                }

                if (ctype_upper($entry[0]) && is_dir($d->path . DIRECTORY_SEPARATOR . $entry)) {
                    $applications[] = $entry;
                }
            }
            $d->close();
        } else {
            // get list of available applications
            $applications = Application::getInstance()->getApplicationsByState(Application::ENABLED)->name;
        }

        if (! in_array('Tinebase', $applications)) {
            // prevent the case of no servers ... (maybe all apps are disabled?)
            array_push($applications, 'Tinebase');
        }

        // get list of plugins
        $plugins = array();
        foreach ($applications as $application) {
            $config = $application . '_Config';

            try {
                if (@class_exists($config)) {
                    $reflectedClass = new ReflectionClass($config);

                    if ($reflectedClass->isSubclassOf('Config_Abstract')) {
                        $plugins = array_merge($plugins, call_user_func(array(
                            $config,
                            'getServerPlugins'
                        )));
                    }
                }
            } catch (Exception $e) {
                Exception::log($e);
            }
        }

        // sort plugins by priority
        asort($plugins);

        $plugins = array_keys($plugins);

        if ($cache) {
            $cache->save($plugins, self::TINEBASE_SERVER_PLUGINS);
        }

        return $plugins;
    }

    /**
     * Return server plugins ensuring that they were found
     *
     * @return array
     */
    protected static function _getServerPlugins()
    {
        if (empty(self::$_serverPlugins)) {
            self::$_serverPlugins = self::_searchServerPlugins();
        }

        return self::$_serverPlugins;
    }

    /**
     * returns TRUE if filesystem is available
     *
     * - value is stored in session and registry for caching
     *
     * @return boolean
     */
    public static function isFilesystemAvailable()
    {
        $isFileSystemAvailable = self::get('FILESYSTEM');
        if ($isFileSystemAvailable === null) {
            try {
                $session = Session::getSessionNamespace();

                if (isset($session->filesystemAvailable)) {
                    $isFileSystemAvailable = $session->filesystemAvailable;
                    if ($isFileSystemAvailable) {
                        self::set('FILESYSTEM', $isFileSystemAvailable);
                        return $isFileSystemAvailable;
                    }
                }
            } catch (Zend_Session_Exception $zse) {
                $session = null;
            }

            $isFileSystemAvailable = false;
            if (! empty(Core::getConfig()->filesdir)) {
                if (! is_writeable(Core::getConfig()->filesdir)) {
                    // try to create it
                    $isFileSystemAvailable = @mkdir(Core::getConfig()->filesdir);
                } else {
                    $isFileSystemAvailable = true;
                }
            }

            if ($session instanceof Zend_Session_Namespace) {
                if (Fgsl\Groupware\Groupbase\Session::isWritable()) {
                    $session->filesystemAvailable = $isFileSystemAvailable;
                }
            }

            if (Core::isLogLevel(LogLevel::INFO))
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Filesystem available: ' . ($isFileSystemAvailable ? 'yes' : 'no'));

            self::set('FILESYSTEM', $isFileSystemAvailable);
        }

        return $isFileSystemAvailable;
    }

    /**
     * returns true if installation is in maintenance mode
     *
     * @return bool
     */
    public static function inMaintenanceMode()
    {
        $config = self::getConfig();
        return ! empty($config->{Config::MAINTENANCE_MODE});
    }

    /**
     * returns true if installation is in maintenance mode "ALL"
     *
     * @return bool
     */
    public static function inMaintenanceModeAll()
    {
        $config = self::getConfig();
        return $config->{Config::MAINTENANCE_MODE} === Config::MAINTENANCE_MODE_ALL;
    }

    /**
     * returns Tine 2.0 user agent string
     *
     * @param string $submodule
     * @return string
     */
    public static function getTineUserAgent($submodule = '')
    {
        return 'Tine 2.0 ' . $submodule . '(version ' . TINE20_CODENAME . ' - ' . TINE20_PACKAGESTRING . ')';
    }

    /**
     * get http client
     *
     * @param null $uri
     * @param null $config
     * @return Zend_Http_Client
     */
    public static function getHttpClient($uri = null, $config = null)
    {
        $proxyConfig = Config::getInstance()->get(Config::INTERNET_PROXY);
        if (! empty($proxyConfig)) {
            $proxyConfig['adapter'] = 'Zend_Http_Client_Adapter_Proxy';
            if (is_array($config)) {
                $config = array_merge($config, $proxyConfig);
            } else {
                $config = $proxyConfig;
            }
        }
        $httpClient = new Zend_Http_Client($uri, $config);

        return $httpClient;
    }

    /**
     * get Preview Services client
     *
     * @return Tinebase_FileSystem_Preview_ServiceInterface
     */
    public static function getPreviewService()
    {
        return Tinebase_FileSystem_Preview_ServiceFactory::getPreviewService();
    }

    /**
     * returns the tinebase id
     *
     * @return string
     */
    public static function getGroupbaseId()
    {
        if (! isset(self::$appInstanceCache['GroupbaseId'])) {
            self::$appInstanceCache['GroupbaseId'] = Application::getInstance()->getApplicationByName('Tinebase')->getId();
        }

        return self::$appInstanceCache['GroupbaseId'];
    }

    public static function unsetTinebaseId()
    {
        if (isset(self::$appInstanceCache['GroupbaseId'])) {
            unset(self::$appInstanceCache['GroupbaseId']);
        }
    }

    /**
     * clear application instance cache
     */
    public static function clearAppInstanceCache()
    {
        self::$appInstanceCache = array();
    }

    public static function getDelegate($applicationName, $configKey, $interfaceName)
    {
        if (! isset(static::$_delegatorCache[$configKey])) {
            $config = Config_Abstract::factory($applicationName);
            if (null === $config || null === ($delegate = $config->get($configKey)) || ! class_exists($delegate) || ! in_array($interfaceName, class_implements($delegate))) {
                $delegate = false;
            } else {
                $delegate = new $delegate();
            }
            static::$_delegatorCache[$configKey] = $delegate;
        }

        return static::$_delegatorCache[$configKey];
    }

    public static function clearDelegatorCache()
    {
        static::$_delegatorCache = array();
    }

    /**
     * acquire a lock to prevent parallel execution in a multi server environment
     *
     * @param string $id
     * @return bool
     */
    public static function acquireMultiServerLock($id)
    {
        $result = Tinebase_Lock::tryAcquireLock($id . '::' . static::getTinebaseId());
        if (true === $result || null === $result) {
            return true;
        }
        return false;
    }

    /**
     * release a lock to prevent parallel execution in a multi server environment
     *
     * @param string $id
     * @return bool
     */
    public static function releaseMultiServerLock($id)
    {
        $result = Tinebase_Lock::releaseLock($id . '::' . static::getTinebaseId());
        if (true === $result || null === $result) {
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $id
     * @return Tinebase_Lock_Interface
     */
    public static function getMultiServerLock($id)
    {
        return Tinebase_Lock::getLock($id . '::' . static::getTinebaseId());
    }

    /**
     *
     * @return bool
     */
    public static function isReplicationSlave()
    {
        if (null !== static::$_isReplicationSlave) {
            return static::$_isReplicationSlave;
        }
        $slaveConfiguration = Config::getInstance()->{Config::REPLICATION_SLAVE};
        $tine20Url = $slaveConfiguration->{Config::MASTER_URL};
        $tine20LoginName = $slaveConfiguration->{Config::MASTER_USERNAME};
        $tine20Password = $slaveConfiguration->{Config::MASTER_PASSWORD};

        // check if we are a replication slave
        if (empty($tine20Url) || empty($tine20LoginName) || empty($tine20Password)) {
            static::$_isReplicationMaster = true;
            return (static::$_isReplicationSlave = false);
        }

        static::$_isReplicationMaster = false;
        return (static::$_isReplicationSlave = true);
    }

    /**
     *
     * @return bool
     */
    public static function isReplicationMaster()
    {
        if (null !== static::$_isReplicationMaster) {
            return static::$_isReplicationMaster;
        }

        return ! static::isReplicationSlave();
    }

    /**
     * setup sentry Raven_Client
     */
    public static function setupSentry()
    {
        $sentryServerUri = Config::getInstance()->get(Config::SENTRY_URI);
        if (! $sentryServerUri) {
            return;
        }

        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Registering Sentry Error Handler');

        if (! defined('TINE20_CODENAME')) {
            self::setupBuildConstants();
        }

        $config = [
            'release' => TINE20_CODENAME . ' ' . TINE20_PACKAGESTRING,
            'tags' => array(
                'php_version' => phpversion()
            )
        ];
        $client = new Tinebase_Sentry_Raven_Client($sentryServerUri, $config);
        $serializer = new Tinebase_Sentry_Raven_Serializer();
        $client->setSerializer($serializer);
        $error_handler = new Raven_ErrorHandler($client, false, Config::getInstance()->{Config::SENTRY_LOGLEVL});
        $error_handler->registerExceptionHandler();
        $error_handler->registerErrorHandler();
        $error_handler->registerShutdownFunction();

        self::set('SENTRY', $client);
    }

    /**
     *
     * @return Raven_client|null
     */
    public static function getSentry()
    {
        $sentryClient = Core::isRegistered('SENTRY') ? Core::get('SENTRY') : null;
        return $sentryClient;
    }

    /**
     * Returns path to install logo, if no install logo is set use branding logo
     *
     * @return null|string
     * @throws FileNotFoundException
     */
    public static function getInstallLogo()
    {
        $logo = Config::getInstance()->{Config::INSTALL_LOGO};

        if (! $logo) {
            $logo = Config::getInstance()->{Config::BRANDING_LOGO};
        }

        return Helper::getFilename($logo, false);
    }
}