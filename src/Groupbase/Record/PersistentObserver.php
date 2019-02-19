<?php
namespace Fgsl\Groupware\Groupbase\Record;

use Fgsl\Groupware\Groupbase\Db\Table;
use Fgsl\Groupware\Groupbase\Exception\Record\NotAllowed;
use Zend\Db\Adapter\AdapterInterface;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Model\AbstractObserver;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\Record\Validation;
use Fgsl\Groupware\Groupbase\DateTime;
use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Fgsl\Groupware\Groupbase\Backend\Sql\Command\Command;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */


/**
 * class PersistentObserver
 * 
 * @package     Groupbase
 * @subpackage  Record
 */
class PersistentObserver
{

    /**
     * Holds instance for SQL_TABLE_PREFIX . 'record_persistentobserver' table
     * 
     * @var Table
     */
    protected $_table;

    /**
     * @var AdapterInterface
     */
    protected $_db;

    /**
     * @var array
     */
    protected $_controllerCache = array();

    /**
     * @var array
     */
    protected $_eventRecursionPrevention = array();

    /**
     * @var bool
     */
    protected $_outerCall = true;
    
    /* holds the instance of the singleton
     *
     * @var Tinebase_Record_PersistentObserver
     */
    private static $instance = NULL;
    
    /**
     * the constructor
     *
     */
    private function __construct()
    {
        $this->_table = new Table(array(
            'name' => SQL_TABLE_PREFIX . 'record_observer',
            'primary' => 'id'
        ));
        $this->_db = $this->_table->getAdapter();
    }
    
    /**
     * the singleton pattern
     *
     * @return PersistentObserver
     */
    public static function getInstance() 
    {
        if (self::$instance === NULL) {
            self::$instance = new PersistentObserver();
        }
        
        return self::$instance;
    }
    
    /**
     *
     * @param AbstractObserver $_event
     */
    public function fireEvent(AbstractObserver $_event)
    {
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Fire Event ' . get_class($_event));

        $setOuterCall = false;
        if (true === $this->_outerCall) {
            $this->_eventRecursionPrevention = array();
            $this->_outerCall = false;
            $setOuterCall = true;
        }

        try {
            $observers = $this->getObserversByEvent($_event->observable, get_class($_event));

            /** @var PersistentObserver $observer */
            foreach ($observers as $observer) {

                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Handling event in observer ' . $observer->observer_model);

                $observerId = $observer->getId();
                if (isset($this->_eventRecursionPrevention[$observerId])) {
                    continue;
                }
                $this->_eventRecursionPrevention[$observerId] = true;

                /** @var Tinebase_Controller_Record_Abstract $controller */
                if (!isset($this->_controllerCache[$observer->observer_model])) {
                    $controller = Core::getApplicationInstance($observer->observer_model, '', true);
                    $this->_controllerCache[$observer->observer_model] = $controller;
                } else {
                    $controller = $this->_controllerCache[$observer->observer_model];
                }

                $_event->persistentObserver = $observer;

                $rightsCheck = null;
                $containerAclCheck = null;
                if (!$observer->do_acl) {
                    $rightsCheck = $controller->doRightChecks(false);
                    $containerAclCheck = $controller->doContainerACLChecks(false);
                }

                $controller->handleEvent($_event);

                if (!$observer->do_acl) {
                    $controller->doContainerACLChecks($containerAclCheck);
                    $controller->doRightChecks($rightsCheck);
                }
            }
        } finally {
            $this->_outerCall = $setOuterCall;
            if (true === $this->_outerCall) {
                $this->_eventRecursionPrevention = array();
            }
        }
    }

    /**
     * registers new persistent observer
     *
     * @param PersistentObserver $_persistentObserver
     * @return PersistentObserver the new persistentObserver
     * @throws NotAllowed
     * @throws Validation
     */
    public function addObserver(PersistentObserver $_persistentObserver) {
        if (null !== $_persistentObserver->getId()) {
            throw new NotAllowed('Can not add existing observer');
        }
        
        $_persistentObserver->created_by = Core::getUser()->getId();
        $_persistentObserver->creation_time = DateTime::now();
        
        if ($_persistentObserver->isValid()) {
            $data = $_persistentObserver->toArray();
            $data['do_acl'] = (isset($data['do_acl']) && false === $data['do_acl']) ? 0 : 1;
            
            $identifier = $this->_table->insert($data);

            $persistentObserver = $this->_table->fetchRow("id = $identifier");
            
            return new PersistentObserver($persistentObserver->toArray(), true);
            
        } else {
            throw new Validation('some fields have invalid content');
        }
    }

    /**
     * unregisters a persistent observer
     * 
     * @param PersistentObserver $_persistentObserver 
     * @return void 
     */
    public function removeObserver(PersistentObserver $_persistentObserver)
    {
        $where = array(
            $this->_db->quoteIdentifier('id') . ' = ' . (int)$_persistentObserver->getId()
        );

        $this->_table->delete($where);
    }

    /**
     * Remove observer by it's identifier
     *
     * @param $identifier
     */
    public function removeObserverByIdentifier($identifier)
    {
        $where = array(
            $this->_db->quoteIdentifier('observer_identifier') . ' = ' . $this->_db->quote($identifier)
        );

        $this->_table->delete($where);
    }


    /**
     * unregisters all observables of a given observer 
     * 
     * @param RecordInterface $_observer 
     * @return void
     */
    public function removeAllObservables(RecordInterface $_observer)
    {
        $where = array(
            $this->_db->quoteIdentifier('observer_model') .       ' = ' . $this->_db->quote(get_class($_observer)),
            $this->_db->quoteIdentifier('observer_identifier') .  ' = ' . $this->_db->quote((string)$_observer->getId())
        );

        $this->_table->delete($where);
    }

    /**
     * returns all observables of a given observer
     * 
     * @param RecordInterface $_observer 
     * @return RecordSet of PersistentObserver
     */
    public function getAllObservables(RecordInterface $_observer)
    {
        $where = array(
            $this->_db->quoteIdentifier('observer_model') .       ' = ' . $this->_db->quote(get_class($_observer)),
            $this->_db->quoteIdentifier('observer_identifier') .  ' = ' . $this->_db->quote((string)$_observer->getId())
        );

        return new RecordSet('PersistentObserver', $this->_table->fetchAll($where)->toArray(), true);
    }

    /**
     * returns all observables of a given event and observer
     * 
     * @param RecordInterface $_observer 
     * @param string $_event
     * @return RecordSet of PersistentObserver
     */
    public function getObservablesByEvent(RecordInterface $_observer, $_event)
    {
        $where = array(
            $this->_db->quoteIdentifier('observer_model') .       ' = ' . $this->_db->quote(get_class($_observer)),
            $this->_db->quoteIdentifier('observer_identifier') .  ' = ' . $this->_db->quote((string)$_observer->getId()),
            $this->_db->quoteIdentifier('observed_event') .       ' = ' . $this->_db->quote((string)$_event)
        );
        
        return new RecordSet('PersistentObserver', $this->_table->fetchAll($where)->toArray(), true);
    }

    /**
     * returns all observables of a given observer model
     *
     * @param string $_model
     * @return RecordSet of PersistentObserver
     */
    public function getObservablesByObserverModel($_model)
    {
        $where = array(
            $this->_db->quoteIdentifier('observer_model') .       ' = ' . $this->_db->quote($_model)
        );

        return new RecordSet('PersistentObserver', $this->_table->fetchAll($where)->toArray(), true);
    }


    /**
     * returns all observers of a given observable and event
     * 
     * @param RecordInterface $_observable
     * @param string $_event
     * @return RecordSet of PersistentObserver
     */
    protected function getObserversByEvent(RecordInterface $_observable,  $_event)
    {
        $where =
            $this->_db->quoteIdentifier('observable_model') .      ' = ' . $this->_db->quote(get_class($_observable)) . ' AND (' .
            $this->_db->quoteIdentifier('observable_identifier') . ' = ' . $this->_db->quote((string)$_observable->getId()) . ' OR ' .
            $this->_db->quoteIdentifier('observable_identifier') . ' IS NULL ) AND ' .
            $this->_db->quoteIdentifier('observed_event') .        ' = ' . $this->_db->quote((string)$_event)
        ;

        return new RecordSet('Fgsl\Groupware\Groupbase\Model\PersistentObserver', $this->_table->fetchAll($where)->toArray(), true);
    }

    /**
     * @param ModelApplication $_application
     * @return int
     */
    public function deleteByApplication(ModelApplication $_application)
    {
        $sqlCommand = Command::factory($this->_db);
        $likeQName = $sqlCommand->getLike() . $this->_db->quote($_application->name . '%');

        $where =
            $this->_db->quoteIdentifier('observable_model') . ' ' . $likeQName . ' OR ' .
            $this->_db->quoteIdentifier('observer_model')   . ' ' . $likeQName;

        return $this->_table->delete($where);
    }
}