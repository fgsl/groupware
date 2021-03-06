<?php
namespace Fgsl\Groupware\Setup\Controller;
use Fgsl\Groupware\Setup\Backend\BackendInterface;
use Fgsl\Groupware\Setup\Core as SetupCore;
use Fgsl\Groupware\Setup\Backend\BackendFactory;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Config\AbstractConfig;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Setup\ExtCheck;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Session\Session;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Record\RecordSet;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class to handle setup of Tine 2.0
 *
 * @package     Setup
 * @subpackage  Controller
 */
class Controller
{
    /**
     * holds the instance of the singleton
     *
     * @var Controller
     */
    private static $_instance = NULL;
    
    /**
     * setup backend
     *
     * @var BackendInterface
     */
    protected $_backend = NULL;
    
    /**
     * the directory where applications are located
     *
     * @var string
     */
    protected $_baseDir;
    
    /**
     * the email configs to get/set
     *
     * @var array
     */
    protected $_emailConfigKeys = array();
    
    /**
     * number of updated apps
     * 
     * @var integer
     */
    protected $_updatedApplications = 0;

    const MAX_DB_PREFIX_LENGTH = 10;
    const INSTALL_NO_IMPORT_EXPORT_DEFINITIONS = 'noImportExportDefinitions';

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}
    
    /**
     * url to Tine 2.0 wiki
     *
     * @var string
     */
    protected $_helperLink = ' <a href="http://wiki.tine20.org/Admins/Install_Howto" target="_blank">Check the Tine 2.0 wiki for support.</a>';

    /**
     * the temporary super user role
     * @var string
     */
    protected $_superUserRoleName = null;

    /**
     * the singleton pattern
     *
     * @return Controller
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new Controller;
        }
        
        return self::$_instance;
    }

    public static function destroyInstance()
    {
        self::$_instance = null;
    }

    /**
     * the constructor
     *
     */
    protected function __construct()
    {
        // setup actions could take quite a while we try to set max execution time to unlimited
        SetupCore::setExecutionLifeTime(0);
        
        if (!defined('MAXLOOPCOUNT')) {
            define('MAXLOOPCOUNT', 50);
        }
        
        $this->_baseDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
        
        if (SetupCore::get(SetupCore::CHECKDB)) {
            $this->_db = SetupCore::getDb();
            $this->_backend = BackendFactory::factory();
        } else {
            $this->_db = NULL;
        }
        
        $this->_emailConfigKeys = array(
            'imap'  => Config::IMAP,
            'smtp'  => Config::SMTP,
            'sieve' => Config::SIEVE,
        );

        // initialize real config if Tinebase is installed
        if ($this->isInstalled('Tinebase') && ! Core::getConfig() instanceof AbstractConfig) {
            // we only have a Zend_Config - check if we can switch to Config
            Core::setupConfig();
        }
    }

    /**
     * check system/php requirements (env + ext check)
     *
     * @return array
     *
     * @todo add message to results array
     */
    public function checkRequirements()
    {
        $envCheck = $this->environmentCheck();
        
        $databaseCheck = $this->checkDatabase();
        
        $extCheck = new ExtCheck(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'essentials.xml');
        $extResult = $extCheck->getData();

        $result = array(
            'success' => ($envCheck['success'] && $databaseCheck['success'] && $extResult['success']),
            'results' => array_merge($envCheck['result'], $databaseCheck['result'], $extResult['result']),
        );

        $result['totalcount'] = count($result['results']);
        
        return $result;
    }
    
    /**
     * check which database extensions are available
     *
     * @return array
     */
    public function checkDatabase()
    {
        $result = array(
            'result'  => array(),
            'success' => false
        );
        
        $loadedExtensions = get_loaded_extensions();
        
        if (! in_array('PDO', $loadedExtensions)) {
            $result['result'][] = array(
                'key'       => 'Database',
                'value'     => FALSE,
                'message'   => "PDO extension not found."  . $this->_helperLink
            );
            
            return $result;
        }
        
        // check mysql requirements
        $missingMysqlExtensions = array_diff(array('pdo_mysql'), $loadedExtensions);
        
        // check pgsql requirements
        $missingPgsqlExtensions = array_diff(array('pgsql', 'pdo_pgsql'), $loadedExtensions);
        
        // check oracle requirements
        $missingOracleExtensions = array_diff(array('oci8'), $loadedExtensions);

        if (! empty($missingMysqlExtensions) && ! empty($missingPgsqlExtensions) && ! empty($missingOracleExtensions)) {
            $result['result'][] = array(
                'key'       => 'Database',
                'value'     => FALSE,
                'message'   => 'Database extensions missing. For MySQL install: ' . implode(', ', $missingMysqlExtensions) . 
                               ' For Oracle install: ' . implode(', ', $missingOracleExtensions) . 
                               ' For PostgreSQL install: ' . implode(', ', $missingPgsqlExtensions) .
                               $this->_helperLink
            );
            
            return $result;
        }
        
        $result['result'][] = array(
            'key'       => 'Database',
            'value'     => TRUE,
            'message'   => 'Support for following databases enabled: ' . 
                           (empty($missingMysqlExtensions) ? 'MySQL' : '') . ' ' .
                           (empty($missingOracleExtensions) ? 'Oracle' : '') . ' ' .
                           (empty($missingPgsqlExtensions) ? 'PostgreSQL' : '') . ' '
        );
        $result['success'] = TRUE;
        
        return $result;
    }
    
    /**
     * Check if tableprefix is longer than 6 charcters
     *
     * @return boolean
     */
    public function checkDatabasePrefix()
    {
        $config = SetupCore::get(SetupCore::CONFIG);
        if (isset($config->database->tableprefix) && strlen($config->database->tableprefix) > self::MAX_DB_PREFIX_LENGTH) {
            if (SetupCore::isLogLevel(LogLevel::ERR)) SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__
                . ' Tableprefix: "' . $config->database->tableprefix . '" is longer than ' . self::MAX_DB_PREFIX_LENGTH
                . '  characters! Please check your configuration.');
            return false;
        }
        return true;
    }
    
    /**
     * Check if logger is properly configured (or not configured at all)
     *
     * @return boolean
     */
    public function checkConfigLogger()
    {
        $config = SetupCore::get(SetupCore::CONFIG);
        if (!isset($config->logger) || !$config->logger->active) {
            return true;
        } else {
            return (
                isset($config->logger->filename)
                && (
                    file_exists($config->logger->filename) && is_writable($config->logger->filename)
                    || is_writable(dirname($config->logger->filename))
                )
            );
        }
    }
    
    /**
     * Check if caching is properly configured (or not configured at all)
     *
     * @return boolean
     */
    public function checkConfigCaching()
    {
        $result = false;
        
        $config = SetupCore::get(SetupCore::CONFIG);
        
        if (! isset($config->caching) || !$config->caching->active) {
            $result = true;
            
        } else if (! isset($config->caching->backend) || ucfirst($config->caching->backend) === 'File') {
            $result = $this->checkDir('path', 'caching', false);
            
        } else if (ucfirst($config->caching->backend) === 'Redis') {
            try {
                $result = $this->_checkRedisConnect(isset($config->caching->redis) ? $config->caching->redis->toArray() : array());
            } catch (\RedisException $re) {
                Exception::log($re);
                $result = false;
            }
            
        } else if (ucfirst($config->caching->backend) === 'Memcached') {
            $result = $this->_checkMemcacheConnect(isset($config->caching->memcached) ? $config->caching->memcached->toArray() : array());
            
        }
        
        return $result;
    }
    
    /**
     * checks redis extension and connection
     * 
     * @param array $config
     * @return boolean
     */
    protected function _checkRedisConnect($config)
    {
        if (! extension_loaded('redis')) {
            SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' redis extension not loaded');
            return FALSE;
        }
        $redis = '\Redis';
        $redis = new $redis();
        $host = isset($config['host']) ? $config['host'] : 'localhost';
        $port = isset($config['port']) ? $config['port'] : 6379;
        
        $result = $redis->connect($host, $port);
        if ($result) {
            $redis->close();
        } else {
            SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Could not connect to redis server at ' . $host . ':' . $port);
        }
        
        return $result;
    }
    
    /**
     * checks memcached extension and connection
     * 
     * @param array $config
     * @return boolean
     */
    protected function _checkMemcacheConnect($config)
    {
        if (! extension_loaded('memcache')) {
            SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' memcache extension not loaded');
            return FALSE;
        }
        $memcache = '\Memcache';
        $memcache = new $memcache();
        $host = isset($config['host']) ? $config['host'] : 'localhost';
        $port = isset($config['port']) ? $config['port'] : 11211;
        $result = $memcache->connect($host, $port);
        
        return $result;
    }
    
    /**
     * Check if queue is properly configured (or not configured at all)
     *
     * @return boolean
     */
    public function checkConfigQueue()
    {
        $config = SetupCore::get(SetupCore::CONFIG);
        if (! isset($config->actionqueue) || ! $config->actionqueue->active) {
            $result = TRUE;
        } else {
            $result = $this->_checkRedisConnect($config->actionqueue->toArray());
        }
        
        return $result;
    }
    
    /**
     * check config session
     * 
     * @return boolean
     */
    public function checkConfigSession()
    {
        $result = FALSE;
        $config = SetupCore::get(SetupCore::CONFIG);
        if (! isset($config->session) || !$config->session->active) {
            return TRUE;
        } else if (ucfirst($config->session->backend) === 'File') {
            return $this->checkDir('path', 'session', FALSE);
        } else if (ucfirst($config->session->backend) === 'Redis') {
            $result = $this->_checkRedisConnect($config->session->toArray());
        }
        
        return $result;
    }
    
    /**
     * checks if path in config is writable
     *
     * @param string $_name
     * @param string $_group
     * @return boolean
     */
    public function checkDir($_name, $_group = NULL, $allowEmptyPath = TRUE)
    {
        $config = $this->getConfigData();
        if ($_group !== NULL && (isset($config[$_group]) || array_key_exists($_group, $config))) {
            $config = $config[$_group];
        }
        
        $path = (isset($config[$_name]) || array_key_exists($_name, $config)) ? $config[$_name] : false;
        if (empty($path)) {
            return $allowEmptyPath;
        } else {
            return @is_writable($path);
        }
    }
    
    /**
     * get list of applications as found in the filesystem
     *
     * @param boolean $getInstalled applications, too
     * @return array appName => setupXML
     */
    public function getInstallableApplications($getInstalled = false)
    {
        // create Tinebase tables first
        $applications = $getInstalled || ! $this->isInstalled('Tinebase')
            ? array('Tinebase' => $this->getSetupXml('Tinebase'))
            : array();
        
        try {
            $dirIterator = new \DirectoryIterator($this->_baseDir);
        } catch (Exception $e) {
            SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Could not open base dir: ' . $this->_baseDir);
            throw new Tinebase_Exception_AccessDenied('Could not open Tine 2.0 root directory.');
        }
        
        foreach ($dirIterator as $item) {
            $appName = $item->getFileName();
            if ($appName{0} != '.' && $appName != 'Tinebase' && $item->isDir()) {
                $fileName = $this->_baseDir . $appName . '/Setup/setup.xml' ;
                if (file_exists($fileName) && ($getInstalled || ! $this->isInstalled($appName))) {
                    $applications[$appName] = $this->getSetupXml($appName);
                }
            }
        }
        
        return $applications;
    }

    protected function _getUpdatesByPrio(&$applicationCount)
    {
        $applicationController = Application::getInstance();

        $updatesByPrio = [];

        /** @var Tinebase_Model_Application $application */
        foreach ($applicationController->getApplications() as $application) {
            if ($application->status !== Application::ENABLED) {
                continue;
            }

            $stateUpdates = json_decode($applicationController->getApplicationState($application,
                Application::STATE_UPDATES, true), true);

            $appMajorV = (int)$application->getMajorVersion();
            for ($majorV = 0; $majorV <= $appMajorV; ++$majorV) {
                /** @var Setup_Update_Abstract $class */
                $class = $application->name . '_Setup_Update_' . $majorV;
                if (class_exists($class)) {
                    $updates = $class::getAllUpdates();
                    $allUpdates = [];
                    foreach ($updates as $prio => $byPrio) {
                        foreach ($byPrio as &$update) {
                            $update['prio'] = $prio;
                        }
                        unset($update);
                        $allUpdates += $byPrio;
                    }

                    if (is_array($stateUpdates) && count($stateUpdates) > 0) {
                        $allUpdates = array_diff_key($allUpdates, $stateUpdates);
                    }
                    if (!empty($allUpdates)) {
                        ++$applicationCount;
                    }
                    foreach ($allUpdates as $update) {
                        if (!isset($updatesByPrio[$update['prio']])) {
                            $updatesByPrio[$update['prio']] = [];
                        }
                        $updatesByPrio[$update['prio']][] = $update;
                    }
                }
            }
        }

        return $updatesByPrio;
    }
    
    /**
     * updates installed applications. does nothing if no applications are installed
     *
     * applications is legacy, we always update all installed applications
     *
     * @param RecordSet $_applications
     * @return  array   messages
     * @throws Tinebase_Exception
     */
    public function updateApplications(RecordSet $_applications = null)
    {
        $this->clearCache();

        if (null === ($user = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly())) {
            throw new Tinebase_Exception('could not create setup user');
        }
        Core::set(Core::USER, $user);

        if ($_applications === null) {
            $_applications = Application::getInstance()->getApplications();

            /** @var Tinebase_Model_Application $tinebase */
            $tinebase = $_applications->find('name', 'Tinebase');
            $setupXml = $this->getSetupXml($tinebase->name);
            list($majV,) = explode('.', $setupXml->version, 2);
            if (abs((int)$majV - (int)$tinebase->getMajorVersion()) > 1) {
                throw new Setup_Exception_Dependency('Tinebase version ' . $tinebase->version .
                    ' can not be updated to ' . $setupXml->version);
            }
        }

        // TODO remove this in Version 13
        //return array(
        //            'messages' => $messages,
        //            'updated'  => $this->_updatedApplications,
        //        );
        $result = $this->_legacyUpdateApplications($_applications);
        $iterationCount = 0;

        do {
            $updatesByPrio = $this->_getUpdatesByPrio($result['updated']);

            if (empty($updatesByPrio)) {
                return $result;
            }

            ksort($updatesByPrio);
            $db = SetupCore::getDb();
            $classes = [];

            try {
                $this->_prepareUpdate(Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly());

                foreach ($updatesByPrio as $prio => $updates) {
                    foreach ($updates as $update) {
                        $className = $update[Setup_Update_Abstract::CLASS_CONST];
                        $functionName = $update[Setup_Update_Abstract::FUNCTION_CONST];
                        if (!isset($classes[$className])) {
                            $classes[$className] = new $className($this->_backend);
                        }
                        $class = $classes[$className];

                        try {
                            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);

                            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__
                                . ' Updating ' . $className . '::' . $functionName
                            );

                            $class->$functionName();

                            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);

                        } catch (Exception $e) {
                            Tinebase_TransactionManager::getInstance()->rollBack();
                            SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getMessage());
                            SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getTraceAsString());
                            throw $e;
                        }
                    }
                }

            } finally {
                $this->_cleanUpUpdate();
            }
        } while (++$iterationCount < 5);

        throw new Tinebase_Exception('endless update loop');
    }

    protected function _legacyUpdateApplications(RecordSet $_applications = null)
    {
        // we need to clone here because we would taint the app cache otherwise
        $applications = clone($_applications);

        $this->_updatedApplications = 0;
        $smallestMajorVersion = NULL;
        $biggestMajorVersion = NULL;
        
        //find smallest major version
        foreach ($applications as $application) {
            if (! $this->updateNeeded($application)) {
                $applications->removeRecord($application);
                continue;
            }
            
            if ($smallestMajorVersion === NULL || $application->getMajorVersion() < $smallestMajorVersion) {
                $smallestMajorVersion = $application->getMajorVersion();
            }
            if ($biggestMajorVersion === NULL || $application->getMajorVersion() > $biggestMajorVersion) {
                $biggestMajorVersion = $application->getMajorVersion();
            }
        }
        
        $messages = array();

        // turn off create previews and index content temporarily
        $fsConfig = Config::getInstance()->get(Config::FILESYSTEM);
        if ($fsConfig && ($fsConfig->{Config::FILESYSTEM_CREATE_PREVIEWS} ||
                $fsConfig->{Config::FILESYSTEM_INDEX_CONTENT})) {
            $fsConfig->unsetParent();
            $fsConfig->{Config::FILESYSTEM_CREATE_PREVIEWS} = false;
            $fsConfig->{Config::FILESYSTEM_INDEX_CONTENT} = false;
            Config::getInstance()->setInMemory(Config::FILESYSTEM, $fsConfig);
        }

        $this->_enforceCollation();

        $release11 = new Tinebase_Setup_Update_Release11($this->_backend);
        $release11->fsAVupdates();

        // we need to clone here because we would taint the app cache otherwise
        // update tinebase first (to biggest major version)
        $tinebase = clone (Application::getInstance()->getApplicationByName('Tinebase'));
        if ($idx = $applications->getIndexById($tinebase->getId())) {
            unset($applications[$idx]);
        }

        list($major, $minor) = explode('.', $this->getSetupXml('Tinebase')->version[0]);
        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Updating Tinebase to version ' . $major . '.' . $minor);

        // TODO remove this in release 13
        $release11 = new Tinebase_Setup_Update_Release11(Setup_Backend_Factory::factory());
        $release11->addIsSystemToCustomFieldConfig();
        $adbRelease11 = new Addressbook_Setup_Update_Release11(Setup_Backend_Factory::factory());
        $adbRelease11->fixContactData();

        try {
            Setup_SchemaTool::updateAllSchema();
        } catch (Exception $e) {
            Tinebase_Exception::log($e);
            SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Schema update failed - retrying once ...');
            $this->clearCache();
            sleep(5);
            Setup_SchemaTool::updateAllSchema();
        }

        for ($majorVersion = $tinebase->getMajorVersion(); $majorVersion <= $major; $majorVersion++) {
            $messages = array_merge($messages, $this->updateApplication($tinebase, $majorVersion));
        }

        // update the rest
        for ($majorVersion = $smallestMajorVersion; $majorVersion <= $biggestMajorVersion; $majorVersion++) {
            foreach ($applications as $application) {
                if ($application->getMajorVersion() <= $majorVersion) {
                    $messages = array_merge($messages, $this->updateApplication($application, $majorVersion));
                }
            }
        }

        $this->clearCache();
        
        return array(
            'messages' => $messages,
            'updated'  => $this->_updatedApplications,
        );
    }

    /**
     * TODO remove this function in Release 13
     * TODO or better refactor it once the new update system is available
     */
    protected function _enforceCollation()
    {
        $db = SetupCore::getDb();
        $dbConfig = $db->getConfig();
        if ($dbConfig['charset'] === 'utf8') {
            $check = $db->query('SELECT count(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE "' .
                    SQL_TABLE_PREFIX . '%" AND TABLE_COLLATION LIKE "utf8mb4%"' .
                    ' AND TABLE_SCHEMA = "' . $dbConfig['dbname'] . '"')->fetchColumn();
            if (0 !== (int)$check) {
                throw new Tinebase_Exception_Backend(
                    'you already have some utf8mb4 tables, but your db config says utf8, this is bad!');
            }

            $check = $db->query('SELECT count(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE "' .
                SQL_TABLE_PREFIX . '%" AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME LIKE "utf8mb4%"' .
                ' AND TABLE_SCHEMA = "' . $dbConfig['dbname'] . '"')->fetchColumn();
            if (0 !== (int)$check) {
                throw new Tinebase_Exception_Backend(
                    'you already have some utf8mb4 columns, but your db config says utf8, this is bad!');
            }

            $charset = 'utf8';
            $collation = 'utf8_unicode_ci';
        } else {
            $check = $db->query('SELECT count(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE "' .
                SQL_TABLE_PREFIX . '%" AND TABLE_COLLATION LIKE "utf8\\_%"' .
                ' AND TABLE_SCHEMA = "' . $dbConfig['dbname'] . '"')->fetchColumn();
            if (0 !== (int)$check) {
                throw new Tinebase_Exception_Backend(
                    'you still have some utf8 tables, but your db config says utf8mb4, this is bad!');
            }

            $check = $db->query('SELECT count(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE "' .
                SQL_TABLE_PREFIX . '%" AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME LIKE "utf8\\_%"' .
                ' AND TABLE_SCHEMA = "' . $dbConfig['dbname'] . '"')->fetchColumn();
            if (0 !== (int)$check) {
                throw new Tinebase_Exception_Backend(
                    'you still have some utf8 columns, but your db config says utf8mb4, this is bad!');
            }

            $charset = 'utf8mb4';
            $collation = 'utf8mb4_unicode_ci';
        }

        $query = 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE "' .
            SQL_TABLE_PREFIX . '%" AND TABLE_COLLATION <> "' . $collation . '"' .
            ' AND TABLE_SCHEMA = "' . $dbConfig['dbname'] . '"';
        $tables = $db->query($query)->fetchAll(Zend_DB::FETCH_COLUMN, 0);

        if (count($tables) > 0) {
            $db->query('SET foreign_key_checks = 0');
            $db->query('SET unique_checks = 0');

            foreach ($tables as $table) {
                $db->query('ALTER TABLE `' . $table . '` convert to character set ' . $charset . ' collate ' .
                    $collation);
                if (SQL_TABLE_PREFIX . 'tree_nodes' === $table) {
                    $setupBackend = new Setup_Backend_Mysql();
                    $setupBackend->alterCol('tree_nodes', new Setup_Backend_Schema_Field_Xml('<field>
                        <name>name</name>
                        <type>text</type>
                        <length>255</length>
                        <notnull>true</notnull>
                        <collation>utf8mb4_bin</collation>
                    </field>'));
                }
            }

            $db->query('SET foreign_key_checks = 1');
            $db->query('SET unique_checks = 1');

            foreach ($tables as $table) {
                $db->query('REPAIR TABLE ' . $db->quoteIdentifier($table));
                $db->query('OPTIMIZE TABLE ' . $db->quoteIdentifier($table));
            }

            $db->closeConnection();

            // give mysql time to catch up?
            sleep(10);
        }

        $columns = $db->query('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE "'
            . SQL_TABLE_PREFIX . '%" AND COLLATION_NAME <> "' . $collation . '" AND COLLATION_NAME IS NOT NULL'
            . ' AND TABLE_SCHEMA = "' . $dbConfig['dbname'] . '"'
            . ' AND (TABLE_NAME <> "' . SQL_TABLE_PREFIX . 'tree_nodes" OR COLUMN_NAME <> "name")')
            ->fetchAll();

        if (count($columns) > 0) {
            throw new Tinebase_Exception_Backend_Database('some columns do not have the proper collation: ' .
                print_r($columns, true));
        }
    }

    /**
     * load the setup.xml file and returns a simplexml object
     *
     * @param string $_applicationName name of the application
     * @param boolean $_disableAppIfNotFound
     * @return SimpleXMLElement|null
     * @throws Setup_Exception_NotFound
     */
    public function getSetupXml($_applicationName, $_disableAppIfNotFound = false)
    {
        $setupXML = $this->_baseDir . ucfirst($_applicationName) . '/Setup/setup.xml';

        if (! file_exists($setupXML)) {
            if ($_disableAppIfNotFound) {
                Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . $setupXML
                    . ' not found - disabling application "' . $_applicationName . '".');
                $application = Application::getInstance()->getApplicationByName($_applicationName);
                Application::getInstance()->setApplicationStatus(
                    array($application->getId()),
                    Application::DISABLED);
                return null;
            } else {
                throw new Setup_Exception_NotFound($setupXML . ' not found. If application got renamed or deleted, re-run setup.php.');
            }
        }
        
        $xml = simplexml_load_file($setupXML);

        return $xml;
    }
    
    /**
     * check update
     *
     * @param   Tinebase_Model_Application $_application
     * @throws  Setup_Exception
     */
    public function checkUpdate(Tinebase_Model_Application $_application)
    {
        $xmlTables = $this->getSetupXml($_application->name, true);
        if ($xmlTables && isset($xmlTables->tables)) {
            foreach ($xmlTables->tables[0] as $tableXML) {
                $table = Setup_Backend_Schema_Table_Factory::factory('Xml', $tableXML);
                if (true == $this->_backend->tableExists($table->name)) {
                    try {
                        $this->_backend->checkTable($table);
                    } catch (Setup_Exception $e) {
                        SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . " Checking table failed with message '{$e->getMessage()}'");
                    }
                } else {
                    throw new Setup_Exception('Table ' . $table->name . ' for application' . $_application->name . " does not exist. \n<strong>Update broken</strong>");
                }
            }
        }
    }
    
    /**
     * update installed application
     *
     * @param   Tinebase_Model_Application    $_application
     * @param   string    $_majorVersion
     * @return  array   messages
     * @throws  Setup_Exception if current app version is too high
     * @throws  Exception
     */
    public function updateApplication(Tinebase_Model_Application $_application, $_majorVersion)
    {
        $setupXml = $this->getSetupXml($_application->name);
        $messages = array();
        
        switch (version_compare($_application->version, $setupXml->version)) {
            case -1:
                $message = "Executing updates for " . $_application->name . " (starting at " . $_application->version . ")";
                
                $messages[] = $message;
                SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' ' . $message);

                $this->_assertApplicationStatesTable();

                $version = $_application->getMajorAndMinorVersion();
                $minor = $version['minor'];
                
                $className = ucfirst($_application->name) . '_Setup_Update_Release' . $_majorVersion;
                if(class_exists($className)) {
                    try {
                        $this->_prepareUpdate(Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly());
                        $update = new $className($this->_backend);

                        $classMethods = get_class_methods($update);

                        while (array_search('update_' . $minor, $classMethods) !== false) {
                            $functionName = 'update_' . $minor;

                            try {
                                $db = SetupCore::getDb();
                                $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);

                                SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__
                                    . ' Updating ' . $_application->name . ' - ' . $functionName
                                );

                                $update->$functionName();

                                Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);

                            } catch (Exception $e) {
                                Tinebase_TransactionManager::getInstance()->rollBack();
                                SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getMessage());
                                SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getTraceAsString());
                                throw $e;
                            }

                            $minor++;
                        }
                    } finally {
                        $this->_cleanUpUpdate();
                    }
                }

                $updatedApp = Application::getInstance()->getApplicationById($_application->getId());
                if ($_application->version !== $updatedApp->version) {

                    $messages[] = "<strong> Updated " . $_application->name . " successfully to " . $_majorVersion . '.' . $minor . "</strong>";

                    // update app version
                    $_application->version = $updatedApp->version;
                    SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Updated ' . $_application->name . " successfully to " . $_application->version);
                    $this->_updatedApplications++;
                }
                
                break;
                
            case 0:
                SetupCore::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No update needed for ' . $_application->name);
                break;
                
            case 1:
                throw new Setup_Exception('Current application version is higher than version from setup.xml: '
                    . $_application->version . ' > ' . $setupXml->version
                );
                break;
        }
        
        $this->clearCache();

        return $messages;
    }

    // TODO remove in Release 13
    /**
     * make sure that application_states table exists before the update
     *
     * @see https://github.com/tine20/Tine-2.0-Open-Source-Groupware-and-CRM/issues/77
     *
     * TODO should be removed at some point
     */
    protected function _assertApplicationStatesTable()
    {
        if ($this->_backend->tableExists('application_states')) {
            return;
        }

        $updater = new Tinebase_Setup_Update_Release11($this->_backend);
        $oldVersion = Application::getInstance()->getApplicationByName('Tinebase')->version;
        $updater->update_23();
        $updater->setApplicationVersion('Tinebase', $oldVersion);
    }

    // TODO remove in Release 13
    /**
     * TODO should be removed at some point
     */
    protected function _fixTinebase10_33()
    {
        // check and execute \Tinebase_Setup_Update_Release10::update_32 if not done yet :-/
        $updater = new Tinebase_Setup_Update_Release10($this->_backend);
        if (version_compare(($oldVersion = Setup_Update_Abstract::getAppVersion('Tinebase')), '10.33') > -1) {
            return;
        }

        $tables = array(
            'roles' => '',
            'role_rights' => '',
            'role_accounts' => '',
        );

        foreach ($tables as $table => &$oldTblVersion) {
            $oldTblVersion = $updater->getTableVersion($table);
        }

        $updater->update_26();
        $updater->update_32();

        foreach ($tables as $table => $oldTblVersion) {
            $updater->setTableVersion($table, $oldTblVersion);
        }

        $updater->setApplicationVersion('Tinebase', $oldVersion);
    }

    /**
     * prepare update
     *
     * - check minimal required version is installed
     * - checks/disables action queue
     * - creates superuser role for setupuser
     *
     * @see 0013414: update scripts should work without dedicated setupuser
     * @param Tinebase_Model_User $_user
     * @throws Tinebase_Exception
     */
    protected function _prepareUpdate(Tinebase_Model_User $_user)
    {
        $setupXml = $this->getSetupXml('Tinebase');
        if(!empty($setupXml->minimumRequiredVersion) &&
            version_compare(Setup_Update_Abstract::getAppVersion('Tinebase'), $setupXml->minimumRequiredVersion) < 0 ) {
            throw new Tinebase_Exception('Major version jumps are not allowed. Upgrade your current major Version ' .
                'to the most recent minor Version, then upgrade to the most recent next major version. Repeat until ' .
                'you reached the desired major version you want to upgrade to');
        }

        // check action queue is empty and wait for it to finish
        $timeStart = time();
        while (Tinebase_ActionQueue::getInstance()->getQueueSize() > 0 && time() - $timeStart < 300) {
            usleep(1000);
        }
        if (time() - $timeStart >= 300) {
            throw new Tinebase_Exception('waited for Action Queue to become empty for more than 300 sec');
        }
        // set action to direct
        Tinebase_ActionQueue::getInstance('Direct');

        // TODO remove in Release 12
        $this->_fixTinebase10_33();

        $roleController = Tinebase_Acl_Roles::getInstance();
        $applicationController = Application::getInstance();
        $oldModLog = $roleController->modlogActive(false);
        Tinebase_Model_User::forceSuperUser();

        try {
            Tinebase_Model_Role::setIsReplicable(false);
            $this->_superUserRoleName = 'superUser' . Tinebase_Record_Abstract::generateUID();
            $superUserRole = new Tinebase_Model_Role(array(
                'name' => $this->_superUserRoleName
            ));
            $rights = array();

            /** @var Tinebase_Model_Application $application */
            foreach ($applicationController->getApplications() as $application) {
                $appId = $application->getId();
                foreach ($applicationController->getAllRights($appId) as $right) {
                    $rights[] = array(
                        'application_id' => $appId,
                        'right' => $right,
                    );
                }
            }
            $superUserRole->rights = $rights;
            $superUserRole->members = array(
                array(
                    'account_type' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER,
                    'account_id' => $_user->getId()
                )
            );

            $roleController->create($superUserRole);
        } finally {
            Tinebase_Model_Role::setIsReplicable(true);
            $roleController->modlogActive($oldModLog);
        }
    }

    /**
     * cleanup after update
     *
     * - removes setupuser superuser role
     * - re-enables action queue
     */
    protected function _cleanUpUpdate()
    {
        $roleController = Tinebase_Acl_Roles::getInstance();
        $oldModLog = $roleController->modlogActive(false);
        try {
            Tinebase_Model_Role::setIsReplicable(false);
            if (null !== $this->_superUserRoleName) {
                // TODO: check: will the role membership be deleted? How? DB constraint?
                $roleController->delete($roleController->getRoleByName($this->_superUserRoleName));
            }
        } finally {
            Tinebase_Model_Role::setIsReplicable(true);
            $roleController->modlogActive($oldModLog);
            Tinebase_Model_User::forceSuperUser(false);
            $this->_superUserRoleName = null;
        }
    }

    /**
     * checks if update is required
     *
     * TODO remove $_application parameter and legacy code
     *
     * @param Tinebase_Model_Application $_application
     * @return boolean
     */
    public function updateNeeded($_application = null)
    {
        if (null === $_application) {
            $count = 0;
            $this->_getUpdatesByPrio($count);
            return $count > 0;
        }

        // TODO remove legacy code below
        $setupXml = $this->getSetupXml($_application->name, true);
        if (! $setupXml) {
            return false;
        }

        $updateNeeded = version_compare($_application->version, $setupXml->version);
        
        if($updateNeeded === -1) {
            return true;
        }
        
        return false;
    }

    /**
     * search for installed and installable applications
     *
     * @return array
     */
    public function searchApplications()
    {
        // get installable apps
        $installable = $this->getInstallableApplications(/* $getInstalled */ true);
        $applications = array();
        // get installed apps
        if (SetupCore::get(SetupCore::CHECKDB)) {
            try {
                $installed = Application::getInstance()->getApplications(NULL, 'id')->toArray();
                
                // merge to create result array
                foreach ($installed as $application) {
                    
                    if (! (isset($installable[$application['name']]) || array_key_exists($application['name'], $installable))) {
                        Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' App ' . $application['name'] . ' does not exist any more.');
                        continue;
                    }
                    
                    $depends = (array) $installable[$application['name']]->depends;
                    if (isset($depends['application'])) {
                        $depends = implode(', ', (array) $depends['application']);
                    }
                    
                    $application['current_version'] = (string) $installable[$application['name']]->version;
                    $application['install_status'] = (version_compare($application['version'], $application['current_version']) === -1) ? 'updateable' : 'uptodate';
                    $application['depends'] = $depends;
                    $applications[] = $application;
                    unset($installable[$application['name']]);
                }
            } catch (Zend_Db_Statement_Exception $zse) {
                // no tables exist
            }
        }
        
        foreach ($installable as $name => $setupXML) {
            $depends = (array) $setupXML->depends;
            if (isset($depends['application'])) {
                $depends = implode(', ', (array) $depends['application']);
            }
            
            $applications[] = array(
                'name'              => $name,
                'current_version'   => (string) $setupXML->version,
                'install_status'    => 'uninstalled',
                'depends'           => $depends,
            );
        }
        
        return array(
            'results'       => $applications,
            'totalcount'    => count($applications)
        );
    }

    /**
     * checks if setup is required
     *
     * @return boolean
     */
    public function setupRequired()
    {
        $result = FALSE;
        
        // check if applications table exists / only if db available
        if (SetupCore::isRegistered(SetupCore::DB)) {
            try {
                $applicationTable = SetupCore::getDb()->describeTable(SQL_TABLE_PREFIX . 'applications');
                if (empty($applicationTable)) {
                    SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Applications table empty');
                    $result = TRUE;
                }
            } catch (Zend_Db_Statement_Exception $zdse) {
                SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $zdse->getMessage());
                $result = TRUE;
            } catch (Zend_Db_Adapter_Exception $zdae) {
                SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $zdae->getMessage());
                $result = TRUE;
            }
        }
        
        return $result;
    }
    
    /**
     * do php.ini environment check
     *
     * @return array
     */
    public function environmentCheck()
    {
        $result = array();
        $success = TRUE;



        // check php environment
        $requiredIniSettings = array(
            'magic_quotes_sybase'  => 0,
            'magic_quotes_gpc'     => 0,
            'magic_quotes_runtime' => 0,
            'mbstring.func_overload' => 0,
            'eaccelerator.enable' => 0,
            'memory_limit' => '48M'
        );
        
        foreach ($requiredIniSettings as $variable => $newValue) {
            $oldValue = ini_get($variable);
            
            if ($variable == 'memory_limit') {
                $required = Tinebase_Helper::convertToBytes($newValue);
                $set = Tinebase_Helper::convertToBytes($oldValue);
                
                if ($set > -1 && $set < $required) {
                    $result[] = array(
                        'key'       => $variable,
                        'value'     => FALSE,
                        'message'   => "You need to set $variable equal or greater than $required (now: $set)." . $this->_helperLink
                    );
                    $success = FALSE;
                }

            } elseif ($oldValue != $newValue) {
                if (ini_set($variable, $newValue) === false) {
                    $result[] = array(
                        'key'       => $variable,
                        'value'     => FALSE,
                        'message'   => "You need to set $variable from $oldValue to $newValue."  . $this->_helperLink
                    );
                    $success = FALSE;
                }
            } else {
                $result[] = array(
                    'key'       => $variable,
                    'value'     => TRUE,
                    'message'   => ''
                );
            }
        }
        
        return array(
            'result'        => $result,
            'success'       => $success,
        );
    }
    
    /**
     * get config file default values
     *
     * @return array
     */
    public function getConfigDefaults()
    {
        $defaultPath = SetupCore::guessTempDir();
        
        $result = array(
            'database' => array(
                'host'  => 'localhost',
                'dbname' => 'tine20',
                'username' => 'tine20',
                'password' => '',
                'adapter' => 'pdo_mysql',
                'tableprefix' => 'tine20_',
                'port'          => 3306
            ),
            'logger' => array(
                'filename' => $defaultPath . DIRECTORY_SEPARATOR . 'tine20.log',
                'priority' => '5'
            ),
            'caching' => array(
               'active' => 1,
               'lifetime' => 3600,
               'backend' => 'File',
               'path' => $defaultPath,
            ),
            'tmpdir' => $defaultPath,
            'session' => array(
                'path'      => Session::getSessionDir(),
                'liftime'   => 86400,
            ),
        );
        
        return $result;
    }

    /**
     * get config file values
     *
     * @return array
     */
    public function getConfigData()
    {
        $config = SetupCore::get(SetupCore::CONFIG);
        if ($config instanceof Config_Abstract) {
            $configArray = $config->getConfigFileData();
        } else {
            $configArray = $config->toArray();
        }
        
        #####################################
        # LEGACY/COMPATIBILITY:
        # (1) had to rename session.save_path key to sessiondir because otherwise the
        # generic save config method would interpret the "_" as array key/value seperator
        # (2) moved session config to subgroup 'session'
        if (empty($configArray['session']) || empty($configArray['session']['path'])) {
            foreach (array('session.save_path', 'sessiondir') as $deprecatedSessionDir) {
                $sessionDir = (isset($configArray[$deprecatedSessionDir]) || array_key_exists($deprecatedSessionDir, $configArray)) ? $configArray[$deprecatedSessionDir] : '';
                if (! empty($sessionDir)) {
                    if (empty($configArray['session'])) {
                        $configArray['session'] = array();
                    }
                    $configArray['session']['path'] = $sessionDir;
                    Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " config.inc.php key '{$deprecatedSessionDir}' should be renamed to 'path' and moved to 'session' group.");
                }
            }
        }
        #####################################
        
        return $configArray;
    }
    
    /**
     * save data to config file
     *
     * @param array   $_data
     * @param boolean $_merge
     */
    public function saveConfigData($_data, $_merge = TRUE)
    {
        if (!empty($_data['setupuser']['password']) && !Setup_Auth::isMd5($_data['setupuser']['password'])) {
            $password = $_data['setupuser']['password'];
            $_data['setupuser']['password'] = md5($_data['setupuser']['password']);
        }
        if (SetupCore::configFileExists() && !SetupCore::configFileWritable()) {
            throw new Setup_Exception('Config File is not writeable.');
        }
        
        if (SetupCore::configFileExists()) {
            $doLogin = FALSE;
            $filename = SetupCore::getConfigFilePath();
        } else {
            $doLogin = TRUE;
            $filename = dirname(__FILE__) . '/../config.inc.php';
        }
        
        $config = $this->writeConfigToFile($_data, $_merge, $filename);
        
        SetupCore::set(SetupCore::CONFIG, $config);
        
        SetupCore::setupLogger();
        
        if ($doLogin && isset($password)) {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Create session for setup user ' . $_data['setupuser']['username']);
            $this->login($_data['setupuser']['username'], $password);
        }
    }
    
    /**
     * write config to a file
     *
     * @param array $_data
     * @param boolean $_merge
     * @param string $_filename
     * @return Zend_Config
     */
    public function writeConfigToFile($_data, $_merge, $_filename)
    {
        // merge config data and active config
        if ($_merge) {
            $activeConfig = SetupCore::get(SetupCore::CONFIG);
            $configArray = $activeConfig instanceof Config_Abstract
                ? $activeConfig->getConfigFileData()
                : $activeConfig->toArray();
            $config = new Zend_Config($configArray, true);
            $config->merge(new Zend_Config($_data));
        } else {
            $config = new Zend_Config($_data);
        }
        
        // write to file
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Updating config.inc.php');
        $writer = new Zend_Config_Writer_Array(array(
            'config'   => $config,
            'filename' => $_filename,
        ));
        $writer->write();
        
        return $config;
    }
    
    /**
     * load authentication data
     *
     * @return array
     */
    public function loadAuthenticationData()
    {
        return array(
            'authentication'    => $this->_getAuthProviderData(),
            'accounts'          => $this->_getAccountsStorageData(),
            'redirectSettings'  => $this->_getRedirectSettings(),
            'password'          => $this->_getPasswordSettings(),
            'saveusername'      => $this->_getReuseUsernameSettings()
        );
    }
    
    /**
     * Update authentication data
     *
     * Needs Tinebase tables to store the data, therefore
     * installs Tinebase if it is not already installed
     *
     * @param array $_authenticationData
     */
    public function saveAuthentication($_authenticationData)
    {
        if ($this->isInstalled('Tinebase')) {
            // NOTE: Tinebase_Setup_Initialize calls this function again so
            //       we come to this point on initial installation _and_ update
            $this->_updateAuthentication($_authenticationData);
        } else {
            $installationOptions = array('authenticationData' => $_authenticationData);
            $this->installApplications(array('Tinebase'), $installationOptions);
        }
    }

    /**
     * Save {@param $_authenticationData} to config file
     *
     * @param array $_authenticationData [hash containing settings for authentication and accountsStorage]
     * @return void
     */
    protected function _updateAuthentication($_authenticationData)
    {
        // this is a dangerous TRACE as there might be passwords in here!
        //if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_authenticationData, TRUE));

        $this->_enableCaching();
        
        if (isset($_authenticationData['authentication'])) {
            $this->_updateAuthenticationProvider($_authenticationData['authentication']);
        }
        
        if (isset($_authenticationData['accounts'])) {
            $this->_updateAccountsStorage($_authenticationData['accounts']);
        }
        
        if (isset($_authenticationData['redirectSettings'])) {
            $this->_updateRedirectSettings($_authenticationData['redirectSettings']);
        }
        
        if (isset($_authenticationData['password'])) {
            $this->_updatePasswordSettings($_authenticationData['password']);
        }
        
        if (isset($_authenticationData['saveusername'])) {
            $this->_updateReuseUsername($_authenticationData['saveusername']);
        }
        
        if (isset($_authenticationData['acceptedTermsVersion'])) {
            $this->saveAcceptedTerms($_authenticationData['acceptedTermsVersion']);
        }
    }
    
    /**
     * enable caching to make sure cache gets cleaned if config options change
     */
    protected function _enableCaching()
    {
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(
            __METHOD__ . '::' . __LINE__ . ' Activate caching backend if available ...');
        
        Core::setupCache();
    }
    
    /**
     * Update authentication provider
     *
     * @param array $_data
     * @return void
     */
    protected function _updateAuthenticationProvider($_data)
    {
        Tinebase_Auth::setBackendType($_data['backend']);
        $config = (isset($_data[$_data['backend']])) ? $_data[$_data['backend']] : $_data;
        
        $excludeKeys = array('adminLoginName', 'adminPassword', 'adminPasswordConfirmation');
        foreach ($excludeKeys as $key) {
            if ((isset($config[$key]) || array_key_exists($key, $config))) {
                unset($config[$key]);
            }
        }
        
        Tinebase_Auth::setBackendConfiguration($config, null, true);
        Tinebase_Auth::saveBackendConfiguration();
    }
    
    /**
     * Update accountsStorage
     *
     * @param array $_data
     * @return void
     */
    protected function _updateAccountsStorage($_data)
    {
        $originalBackend = Tinebase_User::getConfiguredBackend();
        $newBackend = $_data['backend'];
        
        Tinebase_User::setBackendType($_data['backend']);
        $config = (isset($_data[$_data['backend']])) ? $_data[$_data['backend']] : $_data;
        Tinebase_User::setBackendConfiguration($config, null, true);
        Tinebase_User::saveBackendConfiguration();
        
        if ($originalBackend != $newBackend && $this->isInstalled('Addressbook') && $originalBackend == Tinebase_User::SQL) {
            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Switching from $originalBackend to $newBackend account storage");
            try {
                $db = SetupCore::getDb();
                $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
                $this->_migrateFromSqlAccountsStorage();
                Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
        
            } catch (Exception $e) {
                Tinebase_TransactionManager::getInstance()->rollBack();
                SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getMessage());
                SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getTraceAsString());
                
                Tinebase_User::setBackendType($originalBackend);
                Tinebase_User::saveBackendConfiguration();
                
                throw $e;
            }
        }
    }
    
    /**
     * migrate from SQL account storage to another one (for example LDAP)
     * - deletes all users, groups and roles because they will be
     *   imported from new accounts storage backend
     */
    protected function _migrateFromSqlAccountsStorage()
    {
        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Deleting all user accounts, groups, roles and rights');
        Tinebase_User::factory(Tinebase_User::SQL)->deleteAllUsers();
        
        $contactSQLBackend = new Addressbook_Backend_Sql();
        $allUserContactIds = $contactSQLBackend->search(new Addressbook_Model_ContactFilter(array('type' => 'user')), null, true);
        if (count($allUserContactIds) > 0) {
            $contactSQLBackend->delete($allUserContactIds);
        }

        Tinebase_Group::factory(Tinebase_Group::SQL)->deleteAllGroups();
        $listsSQLBackend = new Addressbook_Backend_List();
        $allGroupListIds = $listsSQLBackend->search(new Addressbook_Model_ListFilter(array('type' => 'group')), null, true);
        if (count($allGroupListIds) > 0) {
            $listsSQLBackend->delete($allGroupListIds);
        }

        $roles = Tinebase_Acl_Roles::getInstance();
        $roles->deleteAllRoles();
        
        // import users (from new backend) / create initial users (SQL)
        Tinebase_User::syncUsers(array('syncContactData' => TRUE));
        
        $roles->createInitialRoles();
        $applications = Application::getInstance()->getApplications(NULL, 'id');
        foreach ($applications as $application) {
             Setup_Initialize::initializeApplicationRights($application);
        }
    }
    
    /**
     * Update redirect settings
     *
     * @param array $_data
     * @return void
     */
    protected function _updateRedirectSettings($_data)
    {
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_data, 1));
        $keys = array(Config::REDIRECTURL, Config::REDIRECTALWAYS, Config::REDIRECTTOREFERRER);
        foreach ($keys as $key) {
            if ((isset($_data[$key]) || array_key_exists($key, $_data))) {
                if (strlen($_data[$key]) === 0) {
                    Config::getInstance()->delete($key);
                } else {
                    Config::getInstance()->set($key, $_data[$key]);
                }
            }
        }
    }

    /**
     * update pw settings
     * 
     * @param array $data
     */
    protected function _updatePasswordSettings($data)
    {
        foreach ($data as $config => $value) {
            Config::getInstance()->get(Config::USER_PASSWORD_POLICY)->{$config} = $value;
        }
    }
    
    /**
     * update pw settings
     * 
     * @param array $data
     */
    protected function _updateReuseUsername($data)
    {
        foreach ($data as $config => $value) {
            Config::getInstance()->set($config, $value);
        }
    }
    
    /**
     *
     * get auth provider data
     *
     * @return array
     *
     * @todo get this from config table instead of file!
     */
    protected function _getAuthProviderData()
    {
        $result = Tinebase_Auth::getBackendConfigurationWithDefaults(SetupCore::get(SetupCore::CHECKDB));
        $result['backend'] = (SetupCore::get(SetupCore::CHECKDB)) ? Tinebase_Auth::getConfiguredBackend() : Tinebase_Auth::SQL;

        return $result;
    }
    
    /**
     * get Accounts storage data
     *
     * @return array
     */
    protected function _getAccountsStorageData()
    {
        $result = Tinebase_User::getBackendConfigurationWithDefaults(SetupCore::get(SetupCore::CHECKDB));
        $result['backend'] = (SetupCore::get(SetupCore::CHECKDB)) ? Tinebase_User::getConfiguredBackend() : Tinebase_User::SQL;

        return $result;
    }
    
    /**
     * Get redirect Settings from config table.
     * If Tinebase is not installed, default values will be returned.
     *
     * @return array
     */
    protected function _getRedirectSettings()
    {
        $return = array(
              Config::REDIRECTURL => '',
              Config::REDIRECTTOREFERRER => '0'
        );
        if (SetupCore::get(SetupCore::CHECKDB) && $this->isInstalled('Tinebase')) {
            $return[Config::REDIRECTURL] = Config::getInstance()->get(Config::REDIRECTURL, '');
            $return[Config::REDIRECTTOREFERRER] = Config::getInstance()->get(Config::REDIRECTTOREFERRER, '');
        }
        return $return;
    }

    /**
     * get password settings
     * 
     * @return array
     * 
     * @todo should use generic mechanism to fetch setup related configs
     */
    protected function _getPasswordSettings()
    {
        $configs = array(
            Config::PASSWORD_CHANGE                     => 1,
            Config::PASSWORD_POLICY_ACTIVE              => 0,
            Config::PASSWORD_POLICY_ONLYASCII           => 0,
            Config::PASSWORD_POLICY_MIN_LENGTH          => 0,
            Config::PASSWORD_POLICY_MIN_WORD_CHARS      => 0,
            Config::PASSWORD_POLICY_MIN_UPPERCASE_CHARS => 0,
            Config::PASSWORD_POLICY_MIN_SPECIAL_CHARS   => 0,
            Config::PASSWORD_POLICY_MIN_NUMBERS         => 0,
            Config::PASSWORD_POLICY_CHANGE_AFTER        => 0,
            Config::PASSWORD_POLICY_FORBID_USERNAME     => 0,
        );

        $result = array();
        $tinebaseInstalled = $this->isInstalled('Tinebase');
        foreach ($configs as $config => $default) {
            if ($tinebaseInstalled) {
                $result[$config] = ($config === Config::PASSWORD_CHANGE)
                    ? Config::getInstance()->get($config)
                    : Config::getInstance()->get(Config::USER_PASSWORD_POLICY)->{$config};
            } else {
                $result[$config] = $default;
            }
        }
        
        return $result;
    }
    
    /**
     * get Reuse Username to login textbox
     * 
     * @return array
     * 
     * @todo should use generic mechanism to fetch setup related configs
     */
    protected function _getReuseUsernameSettings()
    {
        $configs = array(
            Config::REUSEUSERNAME_SAVEUSERNAME         => 0,
        );

        $result = array();
        $tinebaseInstalled = $this->isInstalled('Tinebase');
        foreach ($configs as $config => $default) {
            $result[$config] = ($tinebaseInstalled) ? Config::getInstance()->get($config, $default) : $default;
        }
        
        return $result;
    }
    
    /**
     * get email config
     *
     * @return array
     */
    public function getEmailConfig()
    {
        $result = array();
        
        foreach ($this->_emailConfigKeys as $configName => $configKey) {
            $config = Config::getInstance()->get($configKey, new Config_Struct(array()))->toArray();
            if (! empty($config) && ! isset($config['active'])) {
                $config['active'] = TRUE;
            }
            $result[$configName] = $config;
        }
        
        return $result;
    }
    
    /**
     * save email config
     *
     * @param array $_data
     * @return void
     */
    public function saveEmailConfig($_data)
    {
        // this is a dangerous TRACE as there might be passwords in here!
        //if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_data, TRUE));
        
        $this->_enableCaching();
        
        foreach ($this->_emailConfigKeys as $configName => $configKey) {
            if ((isset($_data[$configName]) || array_key_exists($configName, $_data))) {
                // fetch current config first and preserve all values that aren't in $_data array
                $currentConfig = Config::getInstance()->get($configKey, new Config_Struct(array()))->toArray();
                $newConfig = array_merge($_data[$configName], array_diff_key($currentConfig, $_data[$configName]));
                Config::getInstance()->set($configKey, $newConfig);
            }
        }
    }
    
    /**
     * returns all email config keys
     *
     * @return array
     */
    public function getEmailConfigKeys()
    {
        return $this->_emailConfigKeys;
    }
    
    /**
     * get accepted terms config
     *
     * @return integer
     */
    public function getAcceptedTerms()
    {
        return Config::getInstance()->get(Config::ACCEPTEDTERMSVERSION, 0);
    }
    
    /**
     * save acceptedTermsVersion
     *
     * @param $_data
     * @return void
     */
    public function saveAcceptedTerms($_data)
    {
        Config::getInstance()->set(Config::ACCEPTEDTERMSVERSION, $_data);
    }
    
    /**
     * save config option in db
     *
     * @param string $key
     * @param string|array $value
     * @param string $applicationName
     * @return void
     */
    public function setConfigOption($key, $value, $applicationName = 'Tinebase')
    {
        $config = Config_Abstract::factory($applicationName);
        
        if ($config) {
            if (null === $config->getDefinition($key)) {
                throw new Tinebase_Exception_InvalidArgument('config property ' . $key .
                    ' does not exist in ' . get_class($config));
            }
            $config->set($key, $value);
        }
    }
    
    /**
     * create new setup user session
     *
     * @param   string $_username
     * @param   string $_password
     * @return  bool
     */
    public function login($_username, $_password)
    {
        $setupAuth = new Setup_Auth($_username, $_password);
        $authResult = Zend_Auth::getInstance()->authenticate($setupAuth);
        
        if ($authResult->isValid()) {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Valid credentials, setting username in session and registry.');
            Session::regenerateId();
            
            SetupCore::set(SetupCore::USER, $_username);
            Setup_Session::getSessionNamespace()->setupuser = $_username;
            return true;
            
        } else {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Invalid credentials! ' . print_r($authResult->getMessages(), TRUE));
            Session::expireSessionCookie();
            sleep(2);
            return false;
        }
    }
    
    /**
     * destroy session
     *
     * @return void
     */
    public function logout()
    {
        $_SESSION = array();
        
        Session::destroyAndRemoveCookie();
    }
    
    /**
     * install list of applications
     *
     * @param array $_applications list of application names
     * @param array|null $_options
     */
    public function installApplications($_applications, $_options = null)
    {
        $this->clearCache();
        
        // check requirements for initial install / add required apps to list
        if (! $this->isInstalled('Tinebase')) {
    
            $minimumRequirements = array('Addressbook', 'Tinebase', 'Admin');
            
            foreach ($minimumRequirements as $requiredApp) {
                if (!in_array($requiredApp, $_applications) && !$this->isInstalled($requiredApp)) {
                    // Addressbook has to be installed with Tinebase for initial data (user contact)
                    SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__
                        . ' ' . $requiredApp . ' has to be installed first (adding it to list).'
                    );
                    $_applications[] = $requiredApp;
                }
            }

            Application::getInstance()->omitModLog(true);
        } else {
            $setupUser = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly();
            if ($setupUser && ! Core::getUser() instanceof Tinebase_Model_User) {
                Core::set(Core::USER, $setupUser);
            }
        }
        
        // get xml and sort apps first
        $applications = array();
        foreach ($_applications as $appId => $applicationName) {
            if ($this->isInstalled($applicationName)) {
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . " skipping installation of application {$applicationName} because it is already installed");
            } else {
                $applications[$applicationName] = $this->getSetupXml($applicationName);
                if (strlen($appId) === 40) {
                    $applications[$applicationName]->id = $appId;
                }
            }
        }
        $applications = $this->sortInstallableApplications($applications);
        
        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Installing applications: ' . print_r(array_keys($applications), true));

        $fsConfig = Config::getInstance()->get(Config::FILESYSTEM);
        if ($fsConfig && ($fsConfig->{Config::FILESYSTEM_CREATE_PREVIEWS} ||
                $fsConfig->{Config::FILESYSTEM_INDEX_CONTENT})) {
            $fsConfig->unsetParent();
            $fsConfig->{Config::FILESYSTEM_CREATE_PREVIEWS} = false;
            $fsConfig->{Config::FILESYSTEM_INDEX_CONTENT} = false;
            Config::getInstance()->setInMemory(Config::FILESYSTEM, $fsConfig);
        }

        foreach ($applications as $name => $xml) {
            if (! $xml) {
                SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' Could not install application ' . $name);
            } else {
                $this->_installApplication($xml, $_options);
            }
        }

        $this->clearCache();

        Tinebase_Event::reFireForNewApplications();
    }

    public function setMaintenanceMode($options)
    {
        if (! isset($options['state'])) {
            return false;
        }
        switch ($options['state']) {
            case Config::MAINTENANCE_MODE_OFF:
                Config::getInstance()->{Config::MAINTENANCE_MODE} = '';
                break;

            case Config::MAINTENANCE_MODE_NORMAL:
                Config::getInstance()->{Config::MAINTENANCE_MODE} =
                    Config::MAINTENANCE_MODE_NORMAL;
                break;

            case Config::MAINTENANCE_MODE_ALL:
                Config::getInstance()->{Config::MAINTENANCE_MODE} =
                    Config::MAINTENANCE_MODE_ALL;
                break;

            default:
                return false;
        }
        return true;
    }

    /**
     * install tine from dump file
     *
     * @param $options
     * @throws Setup_Exception
     * @return boolean
     */
    public function installFromDump($options)
    {
        $this->clearCache();

        if ($this->isInstalled('Tinebase')) {
            SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Tinebase is already installed.');
            return false;
        }

        $mysqlBackupFile = null;
        if (isset($options['backupDir'])) {
            $mysqlBackupFile = $options['backupDir'] . '/tine20_mysql.sql.bz2';
        } else if (isset($options['backupUrl'])) {
            // download files first and put them in temp dir
            $tempDir = Core::getTempDir();
            foreach (array(
                         array('file' => 'tine20_config.tar.bz2', 'param' => 'config'),
                         array('file' => 'tine20_mysql.sql.bz2', 'param' => 'db'),
                         array('file' => 'tine20_files.tar.bz2', 'param' => 'files')
                    ) as $download) {
                if (isset($options[$download['param']])) {
                    $targetFile = $tempDir . DIRECTORY_SEPARATOR . $download['file'];
                    $fileUrl = $options['backupUrl'] . '/' . $download['file'];
                    SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Downloading ' . $fileUrl
                        . ' to ' . $targetFile);
                    if ($download['param'] === 'db') {
                        $mysqlBackupFile = $targetFile;
                    }
                    file_put_contents(
                        $targetFile,
                        fopen($fileUrl, 'r')
                    );
                }
            }
            $options['backupDir'] = $tempDir;
        } else {
            throw new Setup_Exception("backupDir or backupUrl param required");
        }

        if (! $mysqlBackupFile || ! file_exists($mysqlBackupFile)) {
            throw new Setup_Exception("$mysqlBackupFile not found");
        }

        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Installing from dump ' . $mysqlBackupFile);

        $this->_replaceTinebaseidInDump($mysqlBackupFile);
        $this->restore($options);

        $setupUser = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly();
        if ($setupUser && ! Core::getUser() instanceof Tinebase_Model_User) {
            Core::set(Core::USER, $setupUser);
        }

        // make sure we have the right instance id
        Core::unsetTinebaseId();
        // save the master id
        $replicationMasterId = Tinebase_Timemachine_ModificationLog::getInstance()->getMaxInstanceSeq();

        // do updates now, because maybe application state updates are not yet there
        Core::getCache()->clean(Zend_Cache::CLEANING_MODE_ALL);
        Application::getInstance()->resetClassCache();
        try {
            $this->updateApplications();
        } catch (Tinebase_Exception_Backend $e) {
            if (strpos($e->getMessage(), 'you still have some utf8 ') === 0) {
                $fe = new Setup_Frontend_Cli();
                $fe->_migrateUtf8mb4();
                $this->updateApplications();
            }
        }

        // then set the replication master id
        $tinebase = Application::getInstance()->getApplicationByName('Tinebase');
        Application::getInstance()->setApplicationState($tinebase,
            Application::STATE_REPLICATION_MASTER_ID, $replicationMasterId);

        return true;
    }

    /**
     * replace old Tinebase ID in dump to make sure we have a unique installation ID
     *
     * TODO: think about moving the Tinebase ID (and more info) to a metadata.json file in the backup zip
     *
     * @param $mysqlBackupFile
     * @return string the old TinebaseId
     * @throws Setup_Exception
     */
    protected function _replaceTinebaseidInDump($mysqlBackupFile)
    {
        // fetch old Tinebase ID
        $cmd = "bzcat $mysqlBackupFile | grep \",'Tinebase','enabled'\"";
        $result = exec($cmd);
        if (! preg_match("/'([0-9a-f]+)','Tinebase'/", $result, $matches)) {
            throw new Setup_Exception('could not find Tinebase ID in dump');
        }
        $oldTinebaseId = $matches[1];
        SetupCore::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Replacing old Tinebase id: ' . $oldTinebaseId);

        $cmd = "bzcat $mysqlBackupFile | sed s/"
            . $oldTinebaseId . '/'
            . Tinebase_Record_Abstract::generateUID() . "/g | " // g for global!
            . "bzip2 > " . $mysqlBackupFile . '.tmp';

        SetupCore::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $cmd);

        exec($cmd);
        copy($mysqlBackupFile . '.tmp', $mysqlBackupFile);
        unlink($mysqlBackupFile . '.tmp');

        return $oldTinebaseId;
    }

    /**
     * delete list of applications
     *
     * @param array $_applications list of application names
     * @throws Tinebase_Exception
     */
    public function uninstallApplications($_applications)
    {
        if (null === ($user = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly())) {
            throw new Tinebase_Exception('could not create setup user');
        }
        Core::set(Core::USER, $user);

        $this->clearCache();

        //sanitize input
        $_applications = array_unique(array_filter($_applications));

        $installedApps = Application::getInstance()->getApplications();
        
        // uninstall all apps if tinebase ist going to be uninstalled
        if (in_array('Tinebase', $_applications)) {
            $_applications = $installedApps->name;
        } else {
            // prevent Addressbook and Admin from being uninstalled
            if(($key = array_search('Addressbook', $_applications)) !== false) {
                unset($_applications[$key]);
            }
            if(($key = array_search('Admin', $_applications)) !== false) {
                unset($_applications[$key]);
            }
        }
        
        // deactivate foreign key check if all installed apps should be uninstalled
        $deactivatedForeignKeyCheck = false;
        if (in_array('Tinebase', $_applications) && get_class($this->_backend) === 'Setup_Backend_Mysql') {
            $this->_backend->setForeignKeyChecks(0);
            $deactivatedForeignKeyCheck = true;
        }

        // get xml and sort apps first
        $applications = array();
        foreach ($_applications as $applicationName) {
            try {
                $applications[$applicationName] = $this->getSetupXml($applicationName);
            } catch (Setup_Exception_NotFound $senf) {
                // application setup.xml not found
                Tinebase_Exception::log($senf);
                $applications[$applicationName] = null;
            }
        }
        $applications = $this->_sortUninstallableApplications($applications);

        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Uninstalling applications: '
            . print_r(array_keys($applications), true));

        if (count($_applications) > count($applications)) {
            SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Some applications could not be uninstalled (check dependencies).');
        }

        foreach ($applications as $name => $xml) {
            $app = Application::getInstance()->getApplicationByName($name);
            $this->_uninstallApplication($app);
        }

        if (true === $deactivatedForeignKeyCheck) {
            $this->_backend->setForeignKeyChecks(1);
        }
    }
    
    /**
     * install given application
     *
     * @param  SimpleXMLElement $_xml
     * @param  array|null $_options
     * @return void
     * @throws Tinebase_Exception_Backend_Database
     * @throws Exception
     */
    protected function _installApplication(SimpleXMLElement $_xml, $_options = null)
    {
        if ($this->_backend === NULL) {
            throw new Tinebase_Exception_Backend_Database('Need configured and working database backend for install.');
        }
        
        if (!$this->checkDatabasePrefix()) {
            throw new Tinebase_Exception_Backend_Database('Tableprefix is too long');
        }
        
        try {
            if (SetupCore::isLogLevel(LogLevel::INFO)) SetupCore::getLogger()->info(
                __METHOD__ . '::' . __LINE__ . ' Installing application: ' . $_xml->name);

            $appData = [
                'name'      => (string)$_xml->name,
                'status'    => $_xml->status ? (string)$_xml->status : Application::ENABLED,
                'order'     => $_xml->order ? (string)$_xml->order : 99,
                'version'   => (string)$_xml->version
            ];
            if ($_xml->id && strlen($_xml->id) === 40) {
                $appData['id'] = (string)$_xml->id;
            }
            $application = new Tinebase_Model_Application($appData);

            if ('Tinebase' !== $application->name) {
                $application = Application::getInstance()->addApplication($application);
            }

            // do doctrine/MCV2 then old xml
            $createdTables = $this->_createModelConfigSchema($_xml->name);

            // traditional xml declaration
            if (isset($_xml->tables)) {
                foreach ($_xml->tables[0] as $tableXML) {
                    $table = Setup_Backend_Schema_Table_Factory::factory('Xml', $tableXML);
                    if ($this->_createTable($table) !== true) {
                        // table was gracefully not created, maybe due to missing requirements, just continue
                        continue;
                    }
                    $createdTables[] = $table;
                }
            }

            if ('Tinebase' === $application->name) {
                $application = Application::getInstance()->addApplication($application);
            }

            // keep track of tables belonging to this application
            foreach ($createdTables as $table) {
                Application::getInstance()->addApplicationTable($application, (string) $table->name, (int) $table->version);
            }
            
            // insert default records
            if (isset($_xml->defaultRecords)) {
                foreach ($_xml->defaultRecords[0] as $record) {
                    $this->_backend->execInsertStatement($record);
                }
            }
            
            Setup_Initialize::initialize($application, $_options);

            if (!isset($_options[self::INSTALL_NO_IMPORT_EXPORT_DEFINITIONS])) {
                // look for import definitions and put them into the db
                $this->createImportExportDefinitions($application);
            }

            // fill update state with all available updates of the current version, as we do not need to run them again
            $appMajorV = (int)$application->getMajorVersion();
            for ($majorV = 0; $majorV <= $appMajorV; ++$majorV) {
                /** @var Setup_Update_Abstract $class */
                $class = $application->name . '_Setup_Update_' . $majorV;
                if (class_exists($class) && !empty($updatesByPrio = $class::getAllUpdates())) {
                    if (!($state = json_decode(Application::getInstance()->getApplicationState(
                            $application->getId(), Application::STATE_UPDATES, true), true))) {
                        $state = [];
                    }
                    $now = Tinebase_DateTime::now()->format(Tinebase_Record_Abstract::ISO8601LONG);

                    foreach ($updatesByPrio as $updates) {
                        foreach (array_keys($updates) as $updateKey) {
                            $state[$updateKey] = $now;
                        }
                    }

                    Application::getInstance()->setApplicationState($application->getId(),
                        Application::STATE_UPDATES, json_encode($state));
                }
            }
        } catch (Exception $e) {
            Tinebase_Exception::log($e, /* suppress trace */ false);
            throw $e;
        }
    }

    /**
     * @param $appName
     * @return array
     */
    protected function _createModelConfigSchema($appName)
    {
        $application = SetupCore::getApplicationInstance($appName, '', true);
        $models = $application->getModels(true /* MCv2only */);
        $createdTables = [];

        if (count($models) > 0) {
            // create tables using doctrine 2
            // NOTE: we don't use createSchema here because some tables might already been created
            // TODO or use createSchema, catch exception and fallback to updateSchema ?
            if ('Tinebase' === (string)$appName) {
                Setup_SchemaTool::updateSchema($models);
            } else {
                Setup_SchemaTool::updateAllSchema();
            }

            // adopt to old workflow
            /** @var Tinebase_Record_Abstract $model */
            foreach ($models as $model) {
                $modelConfiguration = $model::getConfiguration();
                $createdTables[] = (object)array(
                    'name' => Tinebase_Helper::array_value('name', $modelConfiguration->getTable()),
                    'version' => $modelConfiguration->getVersion(),
                );
            }
        }

        return $createdTables;
    }

    protected function _createTable($table)
    {
        if (SetupCore::isLogLevel(LogLevel::DEBUG)) SetupCore::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Creating table: ' . $table->name);

        try {
            $result = $this->_backend->createTable($table);
        } catch (Zend_Db_Statement_Exception $zdse) {
            throw new Tinebase_Exception_Backend_Database('Could not create table: ' . $zdse->getMessage());
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw new Tinebase_Exception_Backend_Database('Could not create table: ' . $zdae->getMessage());
        }

        return $result;
    }

    /**
     * look for export & import definitions and put them into the db
     *
     * @param Tinebase_Model_Application $_application
     * @param boolean $_onlyDefinitions
     */
    public function createImportExportDefinitions($_application, $_onlyDefinitions = false)
    {
        foreach (array('Import', 'Export') as $type) {
            $path =
                $this->_baseDir . $_application->name .
                DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . 'definitions';
    
            if (file_exists($path)) {
                foreach (new DirectoryIterator($path) as $item) {
                    $filename = $path . DIRECTORY_SEPARATOR . $item->getFileName();
                    if (preg_match("/\.xml/", $filename)) {
                        try {
                            Tinebase_ImportExportDefinition::getInstance()->updateOrCreateFromFilename($filename, $_application);
                        } catch (Exception $e) {
                            SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__
                                . ' Not installing import/export definion from file: ' . $filename
                                . ' / Error message: ' . $e->getMessage());
                        }
                    }
                }
            }

            if (true === $_onlyDefinitions) {
                continue;
            }

            $path =
                $this->_baseDir . $_application->name .
                DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . 'templates';

            if (file_exists($path)) {
                $fileSystem = Tinebase_FileSystem::getInstance();

                $basepath = $fileSystem->getApplicationBasePath(
                    'Tinebase',
                    Tinebase_FileSystem::FOLDER_TYPE_SHARED
                ) . '/' . strtolower($type);

                if (false === $fileSystem->isDir($basepath)) {
                    $fileSystem->createAclNode($basepath);
                }

                $templateAppPath = Tinebase_Model_Tree_Node_Path::createFromPath($basepath . '/templates/' . $_application->name);

                if (! $fileSystem->isDir($templateAppPath->statpath)) {
                    $fileSystem->mkdir($templateAppPath->statpath);
                }

                foreach (new DirectoryIterator($path) as $item) {
                    if (!$item->isFile()) {
                        continue;
                    }
                    if (false === ($content = file_get_contents($item->getPathname()))) {
                        SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . ' Could not import template: ' . $item->getPathname());
                        continue;
                    }
                    if (false === ($file = $fileSystem->fopen($templateAppPath->statpath . '/' . $item->getFileName(), 'w'))) {
                        SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . ' could not open ' . $templateAppPath->statpath . '/' . $item->getFileName() . ' for writting');
                        continue;
                    }
                    fwrite($file, $content);
                    if (true !== $fileSystem->fclose($file)) {
                        SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . ' write to ' . $templateAppPath->statpath . '/' . $item->getFileName() . ' did not succeed');
                        continue;
                    }
                }
            }
        }
    }
    
    /**
     * uninstall app
     *
     * @param Tinebase_Model_Application $_application
     * @throws Setup_Exception
     */
    protected function _uninstallApplication(Tinebase_Model_Application $_application, $uninstallAll = false)
    {
        if ($this->_backend === null) {
            throw new Setup_Exception('No setup backend available');
        }
        
        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Uninstall ' . $_application);
        try {
            $applicationTables = Application::getInstance()->getApplicationTables($_application);
        } catch (Zend_Db_Statement_Exception $zdse) {
            SetupCore::getLogger()->err(__METHOD__ . '::' . __LINE__ . " " . $zdse);
            throw new Setup_Exception('Could not uninstall ' . $_application . ' (you might need to remove the tables by yourself): ' . $zdse->getMessage());
        }
        $disabledFK = FALSE;
        $db = Core::getDb();
        
        do {
            $oldCount = count($applicationTables);

            if ($_application->name == 'Tinebase') {
                $installedApplications = Application::getInstance()->getApplications(NULL, 'id');
                if (count($installedApplications) !== 1) {
                    SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Installed apps: ' . print_r($installedApplications->name, true));
                    throw new Setup_Exception_Dependency('Failed to uninstall application "Tinebase" because of dependencies to other installed applications.');
                }
            }

            foreach ($applicationTables as $key => $table) {
                SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Remove table: $table");
                
                try {
                    // drop foreign keys which point to current table first
                    $foreignKeys = $this->_backend->getExistingForeignKeys($table);
                    foreach ($foreignKeys as $foreignKey) {
                        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . 
                            " Drop index: " . $foreignKey['table_name'] . ' => ' . $foreignKey['constraint_name']);
                        $this->_backend->dropForeignKey($foreignKey['table_name'], $foreignKey['constraint_name']);
                    }
                    
                    // drop table
                    $this->_backend->dropTable($table);
                    
                    if ($_application->name != 'Tinebase') {
                        Application::getInstance()->removeApplicationTable($_application, $table);
                    }
                    
                    unset($applicationTables[$key]);
                    
                } catch (Zend_Db_Statement_Exception $e) {
                    // we need to catch exceptions here, as we don't want to break here, as a table
                    // might still have some foreign keys
                    // this works with mysql only
                    $message = $e->getMessage();
                    SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " Could not drop table $table - " . $message);
                    
                    // remove app table if table not found in db
                    if (preg_match('/SQLSTATE\[42S02\]: Base table or view not found/', $message) && $_application->name != 'Tinebase') {
                        Application::getInstance()->removeApplicationTable($_application, $table);
                        unset($applicationTables[$key]);
                    } else {
                        SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " Disabling foreign key checks ... ");
                        if ($db instanceof Zend_Db_Adapter_Pdo_Mysql) {
                            $db->query("SET FOREIGN_KEY_CHECKS=0");
                        }
                        $disabledFK = TRUE;
                    }
                }
            }
            
            if ($oldCount > 0 && count($applicationTables) == $oldCount) {
                throw new Setup_Exception('dead lock detected oldCount: ' . $oldCount);
            }
        } while (count($applicationTables) > 0);
        
        if ($disabledFK) {
            if ($db instanceof Zend_Db_Adapter_Pdo_Mysql) {
                SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " Enabling foreign key checks again... ");
                $db->query("SET FOREIGN_KEY_CHECKS=1");
            }
        }
        
        if ($_application->name != 'Tinebase') {
            if (!$uninstallAll) {
                Tinebase_Relations::getInstance()->removeApplication($_application->name);

                Tinebase_Timemachine_ModificationLog::getInstance()->removeApplication($_application);

                // delete containers, config options and other data for app
                Application::getInstance()->removeApplicationAuxiliaryData($_application);
            }
            
            // remove application from table of installed applications
            Application::getInstance()->deleteApplication($_application);
        }

        Setup_Uninitialize::uninitialize($_application);

        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Removed app: " . $_application->name);
    }

    /**
     * sort applications by checking dependencies
     *
     * @param array $_applications
     * @return array
     */
    public function sortInstallableApplications($_applications)
    {
        $result = array();
        
        // begin with Tinebase, Admin and Addressbook
        $alwaysOnTop = array('Tinebase', 'Admin', 'Addressbook');
        foreach ($alwaysOnTop as $app) {
            if (isset($_applications[$app])) {
                $result[$app] = $_applications[$app];
                unset($_applications[$app]);
            }
        }

        // sort by order
        uasort($_applications, function($a, $b) {
            $aOrder = isset($a->order) ? (int) $a->order : 100;
            $bOrder = isset($b->order) ? (int) $b->order : 100;
            if ($aOrder == $bOrder) {
                // sort alphabetically
                return ((string) $a->name < (string) $b->name) ? -1 : 1;
            }
            return ($aOrder < $bOrder) ? -1 : 1;
        });

        // get all apps to install ($name => $dependencies)
        $appsToSort = array();
        foreach ($_applications as $name => $xml) {
            $depends = (array) $xml->depends;
            if (isset($depends['application'])) {
                if ($depends['application'] == 'Tinebase') {
                    $appsToSort[$name] = array();
                    
                } else {
                    $depends['application'] = (array) $depends['application'];
                    
                    foreach ($depends['application'] as $app) {
                        // don't add tinebase (all apps depend on tinebase)
                        if ($app != 'Tinebase') {
                            $appsToSort[$name][] = $app;
                        }
                    }
                }
            } else {
                $appsToSort[$name] = array();
            }
        }
        
        //SetupCore::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($appsToSort, true));
        
        // re-sort apps
        $count = 0;
        while (count($appsToSort) > 0 && $count < MAXLOOPCOUNT) {
            
            foreach($appsToSort as $name => $depends) {

                if (empty($depends)) {
                    // no dependencies left -> copy app to result set
                    $result[$name] = $_applications[$name];
                    unset($appsToSort[$name]);
                } else {
                    foreach ($depends as $key => $dependingAppName) {
                        if (in_array($dependingAppName, array_keys($result)) || $this->isInstalled($dependingAppName)) {
                            // remove from depending apps because it is already in result set
                            unset($appsToSort[$name][$key]);
                        }
                    }
                }
            }
            $count++;
        }
        
        if ($count == MAXLOOPCOUNT) {
            SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__ .
                " Some Applications could not be installed because of (cyclic?) dependencies: " . print_r(array_keys($appsToSort), TRUE));
        }
        
        return $result;
    }

    /**
     * sort applications by checking dependencies
     *
     * @param array $_applications
     * @return array
     */
    protected function _sortUninstallableApplications($_applications)
    {
        $result = array();

        // if not everything is going to be uninstalled, we need to check the dependencies of the applications
        // that stay installed.
        if (!isset($_applications['Tinebase'])) {
            $installedApps = Application::getInstance()->getApplications()->name;
            $xml = array();

            do {
                $changed = false;
                $stillInstalledApps = array_diff($installedApps, array_keys($_applications));
                foreach ($stillInstalledApps as $name) {
                    if (!isset($xml[$name])) {
                        try {
                            $xml[$name] = $this->getSetupXml($name);
                        } catch (Setup_Exception_NotFound $senf) {
                            Tinebase_Exception::log($senf);
                        }
                    }
                    $depends = isset($xml[$name]) ? (array) $xml[$name]->depends : array();
                    if (isset($depends['application'])) {
                        foreach ((array)$depends['application'] as $app) {
                            if(isset($_applications[$app])) {
                                unset($_applications[$app]);
                                $changed = true;
                            }
                        }
                    }
                }
            } while(true === $changed);
        }
        
        // get all apps to uninstall ($name => $dependencies)
        $appsToSort = array();
        foreach($_applications as $name => $xml) {
            if ($name !== 'Tinebase') {
                $appsToSort[$name] = array();
                $depends = $xml ? (array)$xml->depends : array();
                if (isset($depends['application'])) {
                    foreach ((array)$depends['application'] as $app) {
                        // don't add tinebase (all apps depend on Tinebase)
                        if ($app !== 'Tinebase') {
                            $appsToSort[$name][] = $app;
                        }
                    }
                }
            }
        }
        
        // re-sort apps
        $count = 0;
        while (count($appsToSort) > 0 && $count < MAXLOOPCOUNT) {

            foreach($appsToSort as $name => $depends) {
                // don't uninstall if another app depends on this one
                $otherAppDepends = FALSE;
                foreach($appsToSort as $innerName => $innerDepends) {
                    if(in_array($name, $innerDepends)) {
                        $otherAppDepends = TRUE;
                        break;
                    }
                }
                
                // add it to results
                if (!$otherAppDepends) {
                    $result[$name] = $_applications[$name];
                    unset($appsToSort[$name]);
                }
            }
            $count++;
        }
        
        if ($count == MAXLOOPCOUNT) {
            SetupCore::getLogger()->warn(__METHOD__ . '::' . __LINE__ .
                " Some Applications could not be uninstalled because of (cyclic?) dependencies: " . print_r(array_keys($appsToSort), TRUE));
        }

        // Tinebase is uninstalled last
        if (isset($_applications['Tinebase'])) {
            $result['Tinebase'] = $_applications['Tinebase'];
        }
        
        return $result;
    }
    
    /**
     * check if an application is installed
     *
     * @param string $appname
     * @return boolean
     */
    public function isInstalled($appname)
    {
        try {
            $result = Application::getInstance()->isInstalled($appname);
        } catch (Exception $e) {
            SetupCore::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Application ' . $appname . ' is not installed.');
            SetupCore::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . $e);
            $result = FALSE;
        }
        
        return $result;
    }
    
    /**
     * clear cache
     *
     * @return void
     */
    public function clearCache()
    {
        // setup cache (via tinebase because it is disabled in setup by default)
        Core::setupCache(TRUE);
        
        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Clearing cache ...');
        
        // clear cache
        SetupCore::getCache()->clean(Zend_Cache::CLEANING_MODE_ALL);

        Application::getInstance()->resetClassCache();
        Tinebase_Cache_PerRequest::getInstance()->reset();

        // deactivate cache again
        Core::setupCache(FALSE);
    }

    /**
     * returns TRUE if filesystem is available
     * 
     * @return boolean
     */
    public function isFilesystemAvailable()
    {
        if ($this->_isFileSystemAvailable === null) {
            try {
                $session = Session::getSessionNamespace();

                if (isset($session->filesystemAvailable)) {
                    $this->_isFileSystemAvailable = $session->filesystemAvailable;

                    return $this->_isFileSystemAvailable;
                }
            } catch (Zend_Session_Exception $zse) {
                $session = null;
            }

            $this->_isFileSystemAvailable = (!empty(Core::getConfig()->filesdir) && is_writeable(Core::getConfig()->filesdir));

            if ($session instanceof SessionNamespace) {
                if (Session::isWritable()) {
                    $session->filesystemAvailable = $this->_isFileSystemAvailable;
                }
            }

            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' Filesystem available: ' . ($this->_isFileSystemAvailable ? 'yes' : 'no'));
        }

        return $this->_isFileSystemAvailable;
    }

    /**
     * backup
     *
     * @param $options array(
     *      'backupDir'  => string // where to store the backup
     *      'noTimestamp => bool   // don't append timestamp to backup dir
     *      'config'     => bool   // backup config
     *      'db'         => bool   // backup database
     *      'files'      => bool   // backup files
     *    )
     */
    public function backup($options)
    {
        if (! $this->isInstalled('Tinebase')) {
            SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Tine 2.0 is not installed');
            return;
        }

        $config = SetupCore::getConfig();

        $backupDir = isset($options['backupDir']) ? $options['backupDir'] : $config->backupDir;
        if (! $backupDir) {
            throw new Exception('backupDir not configured');
        }

        if (! isset($options['db']) && ! isset($options['files']) && ! isset($options['config'])) {
            // files & db are default
            $options['db'] = true;
            $options['files'] = true;
        }

        if (! isset($options['noTimestamp'])) {
            $backupDir .= '/' . date_create('now', new DateTimeZone('UTC'))->format('Y-m-d-H-i-s');
        }

        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
            throw new Exception("$backupDir could  not be created");
        }

        if (isset($options['config']) && $options['config']) {
            $configFile = stream_resolve_include_path('config.inc.php');
            $configDir = dirname($configFile);

            $files = file_exists("$configDir/index.php") ? 'config.inc.php' : '.';
            `cd $configDir; tar cjf $backupDir/tine20_config.tar.bz2 $files`;

            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Backup of config file successful');
        }

        if (isset($options['db']) && $options['db']) {
            if (! $this->_backend) {
                throw new Exception('db not configured, cannot backup');
            }

            $backupOptions = array(
                'backupDir'         => $backupDir,
                'structTables'      => $this->getBackupStructureOnlyTables(),
            );

            $this->_backend->backup($backupOptions);

            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Backup of DB successful');
        }

        $filesDir = isset($config->filesdir) ? $config->filesdir : false;
        if (isset($options['files']) && $options['files'] && $filesDir) {
            `cd $filesDir; tar cjf $backupDir/tine20_files.tar.bz2 .`;

            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Backup of files successful');
        }
    }

    /**
     * returns an array of all tables of all applications that should only backup the structure
     *
     * @return array
     * @throws Setup_Exception_NotFound
     */
    public function getBackupStructureOnlyTables()
    {
        $tables = array();

        // find tables that only backup structure
        $applications = Application::getInstance()->getApplications();

        /**
         * @var $application Tinebase_Model_Application
         */
        foreach ($applications as $application) {
            $tableDef = $this->getSetupXml($application->name, true);
            if (! $tableDef) {
                continue;
            }
            $structOnlys = $tableDef->xpath('//table/backupStructureOnly[text()="true"]');

            foreach ($structOnlys as $structOnly) {
                $tableName = $structOnly->xpath('./../name/text()');
                $tables[] = SQL_TABLE_PREFIX . $tableName[0];
            }
        }

        return $tables;
    }

    /**
     * restore
     *
     * @param $options array(
     *      'backupDir'  => string // location of backup to restore
     *      'config'     => bool   // restore config
     *      'db'         => bool   // restore database
     *      'files'      => bool   // restore files
     *    )
     *
     * @param $options
     * @throws Setup_Exception
     */
    public function restore($options)
    {
        if (! isset($options['backupDir'])) {
            throw new Setup_Exception("you need to specify the backupDir");
        }

        if (isset($options['config']) && $options['config']) {
            $configBackupFile = $options['backupDir']. '/tine20_config.tar.bz2';
            if (! file_exists($configBackupFile)) {
                throw new Setup_Exception("$configBackupFile not found");
            }

            $configDir = isset($options['configDir']) ? $options['configDir'] : false;
            if (!$configDir) {
                $configFile = stream_resolve_include_path('config.inc.php');
                if (!$configFile) {
                    throw new Setup_Exception("can't detect configDir, please use configDir option");
                }
                $configDir = dirname($configFile);
            }

            `cd $configDir; tar xf $configBackupFile`;

            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Restore of config file successful');
        }

        SetupCore::setupConfig();
        $config = SetupCore::getConfig();

        if (isset($options['db']) && $options['db']) {
            $this->_backend->restore($options['backupDir']);

            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Restore of DB successful');
        }

        $filesDir = isset($config->filesdir) ? $config->filesdir : false;
        if (isset($options['files']) && $options['files']) {
            $dir = $options['backupDir'];
            $filesBackupFile = $dir . '/tine20_files.tar.bz2';
            if (! file_exists($filesBackupFile)) {
                throw new Setup_Exception("$filesBackupFile not found");
            }

            `cd $filesDir; tar xf $filesBackupFile`;

            SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Restore of files successful');
        }

        SetupCore::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Clearing cache after restore ...');
        $this->_enableCaching();
        Core::getCache()->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    public function compareSchema($options)
    {
        if (! isset($options['otherdb'])) {
            throw new Exception("you need to specify the otherdb");
        }

        return Setup_SchemaTool::compareSchema($options['otherdb']);
    }

    /**
     * @return array
     */
    public function upgradeMysql564()
    {
        $setupBackend = Setup_Backend_Factory::factory();
        if (!$setupBackend->supports('mysql >= 5.6.4 | mariadb >= 10.0.5')) {
            return ['DB backend does not support the features - upgrade to mysql >= 5.6.4 or mariadb >= 10.0.5'];
        }

        $failures = array();
        $setupUpdate = new Setup_Update_Abstract($setupBackend);

        /** @var Tinebase_Model_Application $application */
        foreach (Application::getInstance()->getApplications() as $application) {
            $xml = $this->getSetupXml($application->name);
            // should we check $xml->enabled? I don't think so, we asked Application for the applications...

            // get all MCV2 models for all apps, you never know...
            $controllerInstance = null;
            try {
                $controllerInstance = Core::getApplicationInstance($application->name);
            } catch(NotFound $tenf) {
                $failures[] = 'could not get application controller for app: ' . $application->name;
            }
            if (null !== $controllerInstance) {
                try {
                    $setupUpdate->updateSchema($application->name, $controllerInstance->getModels(true));
                } catch (Exception $e) {
                    $failures[] = 'could not update MCV2 schema for app: ' . $application->name;
                }
            }

            if (!empty($xml->tables)) {
                foreach ($xml->tables->table as $table) {
                    if (!empty($table->requirements) && !$setupBackend->tableExists((string)$table->name)) {
                        foreach ($table->requirements->required as $requirement) {
                            if (!$setupBackend->supports((string)$requirement)) {
                                continue 2;
                            }
                        }
                        $setupBackend->createTable(new Setup_Backend_Schema_Table_Xml($table->asXML()));
                        continue;
                    }

                    // check for fulltext index
                    foreach ($table->declaration->index as $index) {
                        if (empty($index->fulltext)) {
                            continue;
                        }
                        $declaration = new Setup_Backend_Schema_Index_Xml($index->asXML());
                        try {
                            $setupBackend->addIndex((string)$table->name, $declaration);
                        } catch (Exception $e) {
                            $failures[] = (string)$table->name . ': ' . (string)$index->name;
                        }
                    }
                }
            }
        }

        return $failures;
    }
}
