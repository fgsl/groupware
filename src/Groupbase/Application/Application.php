<?php
namespace Fgsl\Groupware\Groupbase\Application;

use Fgsl\Groupware\Groupbase\Container as GroupbaseContainer;
use Fgsl\Groupware\Groupbase\Controller\Record\ModlogTrait;
use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Helper;
use Psr\Log\LogLevel;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Exception\Backend as ExceptionBackend;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Model\ApplicationFilter;
use Fgsl\Groupware\Groupbase\Model\Pagination;
use Fgsl\Groupware\Groupbase\Cache\PerRequest;
use Fgsl\Groupware\Groupbase\Acl\Rights;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\CustomField\CustomField;
use Fgsl\Groupware\Groupbase\Record\PersistentObserver;
use Fgsl\Groupware\Groupbase\FileSystem\FileSystem;
use Fgsl\Groupware\Groupbase\Backend\Sql;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;
use Fgsl\Groupware\Groupbase\Record\Diff;
use Fgsl\Groupware\Groupbase\TransactionManager;
use Fgsl\Groupware\Setup\Core as SetupCore;
use Fgsl\Groupware\Setup\Controller as SetupController;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * the class provides functions to handle applications
 * 
 * @package     Groupbase
 * @subpackage  Application
 */
class Application
{
    use ModlogTrait;

    /**
     * application enabled
     *
     */
    const ENABLED  = 'enabled';
    
    /**
     * application disabled
     *
     */
    const DISABLED = 'disabled';

    const STATE_ACTION_QUEUE_LAST_DURATION = 'actionQueueLastDuration';
    const STATE_ACTION_QUEUE_LAST_DURATION_UPDATE = 'actionQueueLastDurationUpdate';
    const STATE_ACTION_QUEUE_LAST_JOB_CHANGE = 'actionQueueLastJobChange';
    const STATE_ACTION_QUEUE_LAST_JOB_ID = 'actionQueueLastJobId';
    const STATE_FILESYSTEM_ROOT_REVISION_SIZE = 'filesystemRootRevisionSize';
    const STATE_FILESYSTEM_ROOT_SIZE = 'filesystemRootSize';
    const STATE_REPLICATION_MASTER_ID = 'replicationMasterId';
    const STATE_UPDATES = 'updates';


    /**
     * Table name
     *
     * @var string
     */
    protected $_tableName = 'applications';
    
    /**
     * the db adapter
     *
     * @var AdapterInterface
     */
    protected $_db;

    /**
     * @var string
     */
    protected $_modelName = 'ModelApplication';
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() 
    {
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }

    /**
     * holds the instance of the singleton
     *
     * @var Application
     */
    private static $instance = NULL;
    
    /**
     * Returns instance of Application
     *
     * @return Application
     */
    public static function getInstance() 
    {
        if (self::$instance === NULL) {
            self::$instance = new Application;
        }
        
        return self::$instance;
    }

    /**
     * @param bool $boolean
     */
    public function omitModLog($boolean)
    {
        $this->_omitModLog = (bool)$boolean;
    }

    public static function destroyInstance()
    {
        self::$instance = null;
    }

    /**
     * returns one application identified by id
     *
     * @param ModelApplication|string $_applicationId the id of the application
     * @throws NotFound
     * @return ModelApplication the information about the application
     */
    public function getApplicationById($_applicationId)
    {
        $applicationId = ModelApplication::convertApplicationIdToInt($_applicationId);

        /** @var ModelApplication $application */
        $application = $this->getApplications()->getById($applicationId);
        
        if (!$application) {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Application not found. Id: ' . $applicationId);
            throw new NotFound("Application $applicationId not found.");
        }
        
        return $application;
    }

    /**
     * returns one application identified by application name
     * - results are cached
     *
     * @param string $_applicationName the name of the application
     * @return ModelApplication the information about the application
     * @throws InvalidArgument
     * @throws NotFound
     */
    public function getApplicationByName($_applicationName)
    {
        if(empty($_applicationName) || ! is_string($_applicationName)) {
            throw new InvalidArgument('$_applicationName can not be empty / has to be string.');
        }

        $applications = $this->getApplications();
        if ($applications) {
            $application = $applications->find('name', $_applicationName);
        } else {
            $application = false;
        }
        
        if (!$application) {
            throw new NotFound("Application $_applicationName not found.");
        }

        /** @var ModelApplication $application */
        return $application;
    }
    
    /**
     * get list of installed applications
     *
     * @param string $_sort optional the column name to sort by
     * @param string $_dir optional the sort direction can be ASC or DESC only
     * @param string $_filter optional search parameter
     * @param int $_limit optional how many applications to return
     * @param int $_start optional offset for applications
     * @return RecordSet of ModelApplication
     */
    public function getApplications($_filter = NULL, $_sort = null, $_dir = 'ASC', $_start = NULL, $_limit = NULL)
    {
        $filter = null;
        if ($_filter) {
            $filter = new ApplicationFilter(array(
                array('field' => 'name', 'operator' => 'contains', 'value' => $_filter),
            ));
        }
        
        $pagination = null;
        if ($_sort) {
            $pagination = new Pagination(array(
                'sort'  => $_sort,
                'dir'   => $_dir,
                'start' => $_start,
                'limit' => $_limit
            ));
        }
        
        if ($filter === null && $pagination === null) {
            try {
                $result = PerRequest::getInstance()->load(__CLASS__, __METHOD__, 'allApplications', PerRequest::VISIBILITY_SHARED);
                
                return $result;
            } catch (NotFound $tenf) {
                // do nothing
            }
        }
        
        $result = $this->_getBackend()->search($filter, $pagination);
        
        if ($filter === null && $pagination === null) {
            // cache result in persistent shared cache too
            // cache will be cleared, when an application will be added or updated
            PerRequest::getInstance()->save(__CLASS__, __METHOD__, 'allApplications', $result, PerRequest::VISIBILITY_SHARED);
        }
        
        return $result;
    }

    public function clearCache()
    {
        PerRequest::getInstance()->reset(__CLASS__, __CLASS__ . '::getApplications', 'allApplications');
    }

    /**
     * get enabled or disabled applications
     *
     * @param  string  $state  can be Application::ENABLED or Application::DISABLED
     * @return RecordSet list of applications
     * @throws InvalidArgument
     */
    public function getApplicationsByState($state)
    {
        if (!in_array($state, array(Application::ENABLED, Application::DISABLED))) {
            throw new InvalidArgument('$status can be only Application::ENABLED or Application::DISABLED');
        }
        
        $result = $this->getApplications(null, /* sort = */ 'order')->filter('status', $state);
        
        return $result;
    }
    
    /**
     * get hash of installed applications
     *
     * @param string $_sort optional the column name to sort by
     * @param string $_dir optional the sort direction can be ASC or DESC only
     * @param string $_filter optional search parameter
     * @param int $_limit optional how many applications to return
     * @param int $_start optional offset for applications
     * @return string
     */
    public function getApplicationsHash($_filter = NULL, $_sort = null, $_dir = 'ASC', $_start = NULL, $_limit = NULL)
    {
        $applications = $this->getApplications($_filter, $_sort, $_dir, $_start, $_limit);
        
        // create a hash of installed applications and their versions
        $applications = array_combine(
            $applications->id,
            $applications->version
        );
        
        ksort($applications);
        
        return Helper::arrayHash($applications, true);
    }
    
    /**
     * return the total number of applications installed
     *
     * @param $_filter
     * 
     * @return int
     */
    public function getTotalApplicationCount($_filter = NULL)
    {
        $select = $this->_getDb()->select()
            ->from(SQL_TABLE_PREFIX . $this->_tableName, array('count' => 'COUNT(*)'));
        
        if($_filter !== NULL) {
            $select->where($this->_getDb()->quoteIdentifier('name') . ' LIKE ?', '%' . $_filter . '%');
        }
        
        $stmt = $this->_getDb()->query($select);
        $result = $stmt->fetchAll();
        
        return $result[0];
    }
    
    /**
     * return if application is installed (and enabled)
     *
     * @param  ModelApplication|string  $applicationId  the application name/id/object
     * @param  boolean $checkEnabled (FALSE by default)
     * 
     * @return boolean
     */
    public function isInstalled($applicationId, $checkEnabled = FALSE)
    {
        try {
            $app = $this->getApplicationById($applicationId);
            return ($checkEnabled) ? ($app->status === self::ENABLED) : TRUE;
        } catch (NotFound $tenf) {
            return FALSE;
        } catch (\Exception $tenf) {
            // database tables might be not available yet
            // @see 0011338: First Configuration fails after Installation
            return FALSE;
        }
    }
    
    /**
     * set application state
     *
     * @param   string|array|ModelApplication|RecordSet   $_applicationIds application ids to set new state for
     * @param   string  $state the new state
     * @throws  InvalidArgument
     */
    public function setApplicationStatus($_applicationIds, $state)
    {
        if (!in_array($state, array(Application::ENABLED, Application::DISABLED))) {
            throw new InvalidArgument('$_state can be only Application::DISABLED  or Application::ENABLED');
        }
        
        if ($_applicationIds instanceof ModelApplication ||
            $_applicationIds instanceof RecordSet
        ) {
            $applicationIds = (array)$_applicationIds->getId();
        } else {
            $applicationIds = (array)$_applicationIds;
        }
        
        $data = array(
            'status' => $state
        );
        
        $affectedRows = $this->_getBackend()->updateMultiple($applicationIds, $data);
        
        if ($affectedRows === count($applicationIds)) {
            if (Core::isLogLevel(LogLevel::INFO))
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Disabled/Enabled ' . $affectedRows . ' applications.');
        } else {
            if (Core::isLogLevel(LogLevel::NOTICE))
                Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                . ' Could not set state for all requested applications: ' . print_r($applicationIds, TRUE));
        }
        
        $this->resetClassCache();
    }
    
    /**
     * add new appliaction 
     *
     * @param ModelApplication $application the new application object
     * @return ModelApplication the new application with the applicationId set
     */
    public function addApplication(ModelApplication $application)
    {
        $application = $this->_getBackend()->create($application);
        
        $this->resetClassCache();

        $this->_writeModLog($application, null);

        /** @var ModelApplication $application */
        return $application;
    }
    
    /**
     * get all possible application rights
     *
     * @param   int $_applicationId
     * @return  array   all application rights
     */
    public function getAllRights($_applicationId)
    {
        $application = $this->getApplicationById($_applicationId);
        
        // call getAllApplicationRights for application (if it has specific rights)
        $appAclClassName = $application->name . '_Acl_Rights';
        if (@class_exists($appAclClassName)) {
            $appAclObj = call_user_func(array($appAclClassName, 'getInstance'));
            $allRights = $appAclObj->getAllApplicationRights();
        } else {
            $allRights = Rights::getInstance()->getAllApplicationRights($application->name);
        }
        
        return $allRights;
    }
    
    /**
     * get right description
     *
     * @param   int     $_applicationId
     * @return  array   right description
     */
    public function getAllRightDescriptions($_applicationId)
    {
        $application = $this->getApplicationById($_applicationId);
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Getting right descriptions for ' . $application->name );
        
        // call getAllApplicationRights for application (if it has specific rights)
        $appAclClassName = $application->name . '\Acl\Rights';
        if (! @class_exists($appAclClassName)) {
            $appAclClassName = 'Fgsl\Groupware\Groupbase\Acl\Rights';
            $function = 'getTranslatedBasicRightDescriptions';
        } else {
            $function = 'getTranslatedRightDescriptions';
        }
        
        $descriptions = call_user_func(array($appAclClassName, $function));
        
        return $descriptions;
    }
    
    /**
     * get tables of application
     *
     * @param ModelApplication $_applicationId
     * @return array
     */
    public function getApplicationTables($_applicationId)
    {
        $applicationId = ModelApplication::convertApplicationIdToInt($_applicationId);
        
        $select = $this->_getDb()->select()
            ->from(SQL_TABLE_PREFIX . 'application_tables', array('name'))
            ->where($this->_getDb()->quoteIdentifier('application_id') . ' = ?', $applicationId);
            
        $stmt = $this->_getDb()->query($select);
        $rows = $stmt->fetchAll();
        
        return $rows;
    }

    /**
     * remove table from application_tables table
     *
     * @param ModelApplication|string $_applicationId the applicationId
     * @param string $_tableName the table name
     */
    public function removeApplicationTable($_applicationId, $_tableName)
    {
        $applicationId = ModelApplication::convertApplicationIdToInt($_applicationId);
        
        $where = array(
            $this->_getDb()->quoteInto($this->_getDb()->quoteIdentifier('application_id') . '= ?', $applicationId),
            $this->_getDb()->quoteInto($this->_getDb()->quoteIdentifier('name') . '= ?', $_tableName)
        );
        
        $this->_getDb()->delete(SQL_TABLE_PREFIX . 'application_tables', $where);
    }
    
    /**
     * reset class cache
     * 
     * @param string $method
     * @return Application
     */
    public function resetClassCache($method = null)
    {
        PerRequest::getInstance()->reset(__CLASS__, $method);
        
        return $this;
    }
    
    /**
     * remove application from applications table
     *
     * @param ModelApplication|string $_applicationId the applicationId
     */
    public function deleteApplication($_applicationId)
    {
        if (Core::isLogLevel(LogLevel::DEBUG))
            Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Removing app ' . $_applicationId . ' from applications table.');
        
        $applicationId = ModelApplication::convertApplicationIdToInt($_applicationId);
        
        $this->resetClassCache();
        
        $this->_getBackend()->delete($applicationId);
    }
    
    /**
     * add table to tine registry
     *
     * @param ModelApplication|string $_applicationId
     * @param string $_name of table
     * @param int $_version of table
     * @return void
     */
    public function addApplicationTable($_applicationId, $_name, $_version)
    {
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Add application table: ' . $_name);

        $applicationId = ModelApplication::convertApplicationIdToInt($_applicationId);
        
        $applicationData = array(
            'application_id' => $applicationId,
            'name'           => $_name,
            'version'        => $_version
        );
        
        $this->_getDb()->insert(SQL_TABLE_PREFIX . 'application_tables', $applicationData);
    }

    /**
     * gets the current application state
     * we better do a select for update always
     *
     * @param mixed $_applicationId
     * @param string $_stateName
     * @param bool $_forUpdate
     * @return null|string
     * @throws InvalidArgument
     */
    public function getApplicationState($_applicationId, $_stateName, $_forUpdate = false)
    {
        $id = ModelApplication::convertApplicationIdToInt($_applicationId);

        $db = $this->_getDb();
        $result = $db->select()->forUpdate($_forUpdate)->from(SQL_TABLE_PREFIX . 'application_states', 'state')->where(
            $db->quoteIdentifier('id') . $db->quoteInto(' = ? AND ', $id) .
            $db->quoteIdentifier('name') . $db->quoteInto(' = ?', $_stateName))->query()
                ->fetchColumn(0);
        if (false === $result) {
            return null;
        }
        return $result;
    }

    /**
     * @param $_applicationId
     * @param $_stateName
     * @param $_state
     * @throws InvalidArgument
     * @throws \Exception::
     */
    public function setApplicationState($_applicationId, $_stateName, $_state)
    {
        $id = ModelApplication::convertApplicationIdToInt($_applicationId);

        $db = $this->_getDb();
        if (null === $this->getApplicationState($id, $_stateName)) {
            $db->insert(SQL_TABLE_PREFIX . 'application_states',
                [
                    'id' => $id,
                    'name' => $_stateName,
                    'state' => $_state
                ]);
        } else {
            $db->update(SQL_TABLE_PREFIX . 'application_states', ['state' => $_state], $db->quoteIdentifier('id') .
                $db->quoteInto(' = ?', $id) . ' AND ' . $db->quoteIdentifier('name') .
                $db->quoteInto(' = ?', $_stateName));
        }
    }

    /**
     * @param $_applicationId
     * @param $_stateName
     * @param $_state
     * @throws InvalidArgument
     * @throws Exception
     */
    public function deleteApplicationState($_applicationId, $_stateName)
    {
        $id = ModelApplication::convertApplicationIdToInt($_applicationId);

        $db = $this->_getDb();
        $db->delete(SQL_TABLE_PREFIX . 'application_states',
            $db->quoteIdentifier('id') . $db->quoteInto(' = ? AND ', $id) .
            $db->quoteIdentifier('name') . $db->quoteInto(' = ?', $_stateName));
    }

    /**
     * update application
     * 
     * @param ModelApplication $_application
     * @return ModelApplication
     */
    public function updateApplication(ModelApplication $_application)
    {
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Update application: ' . print_r($_application->toArray(), true));

        $result = $this->_getBackend()->update($_application);
        
        $this->resetClassCache();

        /** @var ModelApplication $result */
        return $result;
    }
    
    /**
     * delete containers, configs and other data of an application
     * ATTENTION this does NOT delete the application data itself! only auxiliary data
     * 
     * NOTE: if a table with foreign key constraints to applications is added, we need to make sure that the data is deleted here 
     * 
     * @param ModelApplication $_application
     */
    public function removeApplicationAuxiliaryData(ModelApplication $_application)
    {
        $dataToDelete = array(
            'container'     => array('tablename' => ''),
            'config'        => array('tablename' => ''),
            'customfield'   => array('tablename' => ''),
            'rights'        => array('tablename' => 'role_rights'),
            'definitions'   => array('tablename' => 'importexport_definition'),
            'filter'        => array('tablename' => 'filter'),
            'modlog'        => array('tablename' => 'timemachine_modlog'),
            'import'        => array('tablename' => 'import'),
            'rootnode'      => array('tablename' => ''),
            'pobserver'     => array(),
        );
        $countMessage = ' Deleted';
        
        $where = array(
            $this->_getDb()->quoteInto($this->_getDb()->quoteIdentifier('application_id') . '= ?', $_application->getId())
        );
        
        foreach ($dataToDelete as $dataType => $info) {
            switch ($dataType) {
                case 'container':
                    $count = GroupbaseContainer::getInstance()->dropContainerByApplicationId($_application->getId());
                    break;
                case 'config':
                    $count = Config::getInstance()->deleteConfigByApplicationId($_application->getId());
                    break;
                case 'customfield':
                    try {
                        $count = CustomField::getInstance()->deleteCustomFieldsForApplication($_application->getId());
                    } catch (Exception $e) {
                        Exception::log($e);
                        $count = 0;
                    }
                    break;
                case 'pobserver':
                    $count = PersistentObserver::getInstance()->deleteByApplication($_application);
                    break;
                case 'rootnode':
                    $count = 0;
                    try {
                        if (FileSystem::getInstance()->isDir($_application->name)) {
                            // note: TFS expects name here, not ID
                            $count = FileSystem::getInstance()->rmdir($_application->name, true);
                        }
                    } catch (NotFound $tenf) {
                        // nothing to do
                        Exception::log($tenf);
                    } catch (ExceptionBackend $teb) {
                        // nothing to do
                        Exception::log($teb);
                    } catch (Exception $e) {
                        // problem!
                        Exception::log($e);
                    }
                    break;
                default:
                    if ((isset($info['tablename']) || array_key_exists('tablename', $info)) && ! empty($info['tablename'])) {
                        try {
                            $count = $this->_getDb()->delete(SQL_TABLE_PREFIX . $info['tablename'], $where);
                        } catch (\Exception $zdse) {
                            Exception::log($zdse);
                            $count = 0;
                        }
                    } else {
                        Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' No tablename defined for ' . $dataType);
                        $count = 0;
                    }
            }
            $countMessage .= ' ' . $count . ' ' . $dataType . '(s) /';
        }
        
        $countMessage .= ' for application ' . $_application->name;
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . $countMessage);
    }
    
    /**
     * 
     * @return Sql
     */
    protected function _getBackend()
    {
        if (!isset($this->_backend)) {
            $this->_backend = new Sql(array(
                'modelName' => 'ModelApplication', 
                'tableName' => 'applications'
            ), $this->_getDb());
        }
        
        return $this->_backend;
    }

    public function resetBackend()
    {
        $this->_backend = null;
    }

    /**
     * 
     * @return AdapterInterface
     */
    protected function _getDb()
    {
        if (!isset($this->_db)) {
            $this->_db = Core::getDb();
        }
        
        return $this->_db;
    }

    /**
     * returns the Models of all installed applications
     * uses Application::getApplicationsByState
     * and Tinebase_Controller_Abstract::getModels
     *
     * @return array
     */
    public function getModelsOfAllApplications()
    {
        $models = array();

        $apps = $this->getApplicationsByState(Application::ENABLED);

        /** @var ModelApplication $app */
        foreach($apps as $app) {
            /** @var Tinebase_Controller $controllerClass */
            $controllerClass = $app->name . '_Controller';
            if (!class_exists(($controllerClass))) {
                try {
                    $controllerInstance = Core::getApplicationInstance($app->name, '', true);
                } catch(NotFound $tenf) {
                    continue;
                }
            } else {
                $controllerInstance = $controllerClass::getInstance();
            }

            $appModels = $controllerInstance->getModels();
            if (is_array($appModels)) {
                $models = array_merge($models, $appModels);
            }
        }

        return $models;
    }

    /**
     * extract model and app name from model name
     *
     * @param mixed $modelOrApplication
     * @param null $model
     * @return array
     */
    public static function extractAppAndModel($modelOrApplication, $model = null)
    {
        if (! $modelOrApplication instanceof ModelApplication && $modelOrApplication instanceof RecordInterface) {
            $modelOrApplication = get_class($modelOrApplication);
        }

        // modified (some model names can have both . and _ in their names and we should treat them as JS model name
        if (strpos($modelOrApplication, '_') && ! strpos($modelOrApplication, '.')) {
            // got (complete) model name name as first param
            list($appName, /*$i*/, $modelName) = explode('_', $modelOrApplication, 3);
        } else if (strpos($modelOrApplication, '.')) {
            // got (complete) model name name as first param (JS style)
            list(/*$j*/, $appName, /*$i*/, $modelName) = explode('.', $modelOrApplication, 4);
        } else {
            $appName = $modelOrApplication;
            $modelName = $model;
        }

        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Extracted appName: ' . $appName . ' modelName: ' . $modelName);

        return array(
            'appName'   => $appName,
            'modelName' => $modelName
        );
    }

    /**
     * apply modification logs from a replication master locally
     *
     * @param ModificationLog $_modification
     * @throws Exception
     */
    public function applyReplicationModificationLog(ModificationLog $_modification)
    {

        switch ($_modification->change_type) {
            case ModificationLog::CREATED:
                $diff = new Diff(json_decode($_modification->new_value, true));
                $model = $_modification->record_type;
                /** @var ModelApplication $record */
                $record = new $model($diff->diff);

                // close transaction open in \ModificationLog::applyReplicationModLogs
                TransactionManager::getInstance()->rollBack();
                SetupCore::set(SetupCore::CHECKDB, true);
                SetupController::destroyInstance();
                SetupController::getInstance()->installApplications([$record->getId() => $record->name],
                    [SetupController::INSTALL_NO_IMPORT_EXPORT_DEFINITIONS => true]);
                break;

            default:
                throw new Exception('unsupported Tinebase_Model_ModificationLog->change_type: ' . $_modification->change_type);
        }
    }

    public function getAllApplicationGrantModels($_applicationId)
    {
        $applicationId = ModelApplication::convertApplicationIdToInt($_applicationId);

        try {
            return PerRequest::getInstance()->load(__CLASS__, __METHOD__, $applicationId, PerRequest::VISIBILITY_SHARED);
        } catch (NotFound $tenf) {}

        $grantModels = [];
        $application = $this->getApplicationById($applicationId);
        /** @var DirectoryIterator $file */
        foreach (new \DirectoryIterator(dirname(__DIR__) . '/' . $application->name . '/Model') as $file) {
            if ($file->isFile() && strpos($file->getFilename(), 'Grants.php') > 0) {
                $grantModel = $application->name . '_Model_' . substr($file->getFilename(), 0, -4);
                if (class_exists($grantModel)) {
                    $grantModels[] = $grantModel;
                }
            }
        }

        PerRequest::getInstance()->save(__CLASS__, __METHOD__, $applicationId, $grantModels, PerRequest::VISIBILITY_SHARED);
        return $grantModels;
    }
}
