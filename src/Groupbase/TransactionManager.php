<?php
namespace Fgsl\Groupware\Groupbase;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Psr\Log\LogLevel;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Exception\Exception;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Transaction Manager for Groupware
 * 
 * This is the central class, all transactions within Groupware must be handled with.
 * For each supported transactionable (backend) this class start a real transaction on 
 * the first startTransaction request.
 * 
 * Transactions of all transactionable will be commited at once when all requested transactions
 * are being commited using this class.
 * 
 * Transactions of all transactionable will be roll back when one rollBack is requested
 * using this class.
 * 
 * @package     Groupware
 * @subpackage  TransactionManager
 */
class TransactionManager
{
    /**
     * @var array holds all transactionables with open transactions
     */
    protected $_openTransactionables = array();
    
    /**
     * @var array list of all open (not commited) transactions
     */
    protected $_openTransactions = array();

    /**
     * @var array list of callbacks to call just before really committing
     */
    protected $_onCommitCallbacks = array();

    /**
     * @var array list of callbacks to call just after really committing
     */
    protected $_afterCommitCallbacks = array();

    /**
     * @var array list of callbacks to call just before rollback
     */
    protected $_onRollbackCallbacks = array();

    /**
     * @var bool allow unittest to skip a roll back
     */
    protected $_unitTestForceSkipRollBack = false;

    /**
     * @var TransactionManager
     */
    private static $_instance = NULL;

    /**
     * @var bool
     */
    private static $_insideCallBack = false;

    /**
     * this is a state flag for the callbacks to be used internally only, do not implement a getter for it!
     * after rollback callbacks for example could reset the flag ... and why use it? code that issues a rollback
     * should always throw an exception!
     * 
     * @var bool
     */
    private static $_rollBackOccurred = false;
    
    /**
     * don't clone. Use the singleton.
     */
    private function __clone()
    {
        
    }
    
    /**
     * constructor
     */
    private function __construct()
    {
        
    }
    
    /**
     * @return TransactionManager
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new TransactionManager;
        }
        
        return self::$_instance;
    }
    
    /**
     * starts a transaction
     *
     * @param   mixed $_transactionable
     * @return  string transactionId
     * @throws  UnexpectedValue
     */
    public function startTransaction($_transactionable)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "  startTransaction request");
        if (! in_array($_transactionable, $this->_openTransactionables)) {
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "  new transactionable. Starting transaction on this resource");
            if ($_transactionable instanceof AdapterInterface) {
                $_transactionable->beginTransaction();
            } else {
                $this->rollBack();
                throw new UnexpectedValue('Unsupported transactionable!');
            }
            array_push($this->_openTransactionables, $_transactionable);
        }
        
        $transactionId = AbstractRecord::generateUID();
        array_push($this->_openTransactions, $transactionId);
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "  queued transaction with id $transactionId");
        
        return $transactionId;
    }
    
    /**
     * commits a transaction
     *
     * @param  string $_transactionId
     * @return void
     */
    public function commitTransaction($_transactionId)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "  commitTransaction request for $_transactionId");
         $transactionIdx = array_search($_transactionId, $this->_openTransactions);
         if ($transactionIdx !== false) {
             unset($this->_openTransactions[$transactionIdx]);
         }

         // inside a pre commit callback we don't want to really commit, as this will happen after and outside
         // the callback
         if (static::$_insideCallBack) {
             return;
         }

         $numOpenTransactions = count($this->_openTransactions);
         if ($numOpenTransactions === 0) {
             if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "  no more open transactions in queue commiting all transactionables");

             // avoid loop backs. The callback may trigger a new transaction + commit/rollback...
             $callbacks = $this->_onCommitCallbacks;
             $afterCallbacks = $this->_afterCommitCallbacks;
             $this->_onCommitCallbacks = array();
             $this->_afterCommitCallbacks = array();

             static::$_rollBackOccurred = false;
             try {
                 static::$_insideCallBack = true;
                 foreach ($callbacks as $callable) {
                     call_user_func_array($callable[0], $callable[1]);
                     // if a rollback happened we don't want to continue (the rollback method cleanup already)
                     // it would be better if the code issuing a rollback throws an exception anyway
                     if (static::$_rollBackOccurred) {
                         return;
                     }
                 }
             } finally {
                 static::$_insideCallBack = false;
             }

             foreach ($this->_openTransactionables as $transactionable) {
                 if ($transactionable instanceof AdapterInterface) {
                     $transactionable->commit();
                 }
             }
             // prevent call back issues. The callback may start and commit/rollback more transactions
             $this->_openTransactionables = array();
             $this->_onRollbackCallbacks = array();

             foreach($afterCallbacks as $callable) {
                 try {
                     call_user_func_array($callable[0], $callable[1]);
                 } catch (\Exception $e) {
                     // we don't want to fail after we commited. Otherwise a rollback maybe triggered outside which
                     // actually can't rollback anything anymore as we already commited.
                     // So afterCommitCallbacks will fail "silently", they only log and go to sentry
                     Exception::log($e, false);
                 }
             }


         } else {
             if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "  commiting defered, as there are still $numOpenTransactions in the queue");
         }
    }
    
    /**
     * perform rollBack on all transactionables with open transactions
     * 
     * @return void
     */
    public function rollBack()
    {
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . "  rollBack request, rollBack all transactionables");

        if ($this->_unitTestForceSkipRollBack) {
            return;
        }
        // if we are inside a precommit callback we need to break this as we are doing a rollback now
        // the rollbacks post callback may start new transactions, so we need to reset the flag
        static::$_insideCallBack = false;

        try {
            foreach ($this->_openTransactionables as $transactionable) {
                if ($transactionable instanceof AdapterInterface) {
                    $transactionable->rollBack();
                }
            }

            // avoid loop backs. The callback may trigger a new transaction + commit/rollback...
            $callbacks = $this->_onRollbackCallbacks;
            $this->_onCommitCallbacks = array();
            $this->_afterCommitCallbacks = array();
            $this->_onRollbackCallbacks = array();
            $this->_openTransactionables = array();
            $this->_openTransactions = array();

            foreach ($callbacks as $callable) {
                call_user_func_array($callable[0], $callable[1]);
            }

            // don't mess with this finally, the ->rollBack() may throw. The callback may start / commit => reset the
            // flag. The finally is well thought of. Be aware that the callback has the flag not yet set... but why
            // would the callback need it, it is the "onRollBack" callback anyway right?
        } finally {
            static::$_rollBackOccurred = true;
        }
    }

    /**
     * register a callable to call just before the real commit happens
     *
     * @param array $callable
     * @param array $param
     */
    public function registerOnCommitCallback(array $callable, array $param = array())
    {
        $this->_onCommitCallbacks[] = array($callable, $param);
    }

    /**
     * register a callable to call just after the real commit happens
     *
     * @param array $callable
     * @param array $param
     */
    public function registerAfterCommitCallback(array $callable, array $param = array())
    {
        $this->_afterCommitCallbacks[] = array($callable, $param);
    }

    /**
     * register a callable to call just before the rollback happens
     *
     * @param array $callable
     * @param array $param
     */
    public function registerOnRollbackCallback(array $callable, array $param = array())
    {
        $this->_onRollbackCallbacks[] = array($callable, $param);
    }

    /**
     * returns true if there are transactions started
     *
     * @return bool
     */
    public function hasOpenTransactions()
    {
        return count($this->_openTransactions) > 0;
    }

    /**
     * @param $bool
     */
    public function unitTestForceSkipRollBack($bool)
    {
        $this->_unitTestForceSkipRollBack = $bool;
    }
}
