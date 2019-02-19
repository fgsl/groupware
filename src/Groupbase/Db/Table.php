<?php
namespace Fgsl\Groupware\Groupbase\Db;

use Zend\Db\TableGateway\TableGateway;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Zend\Db\RowGateway\RowGateway;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Cache\PerRequest;
use Psr\Log\LogLevel;
use Zend\Db\Sql\Select;
use Zend\Db\Adapter\Adapter;

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
 * @subpackage  Db
 */
class Table extends TableGateway
{
    /**
     * wrapper around TableGateway::select
     *
     * @param string|array $_where OPTIONAL
     * @param string $_order OPTIONAL
     * @param string $_dir OPTIONAL
     * @param int $_count OPTIONAL
     * @param int $_offset OPTIONAL
     * @throws InvalidArgument if $_dir is not ASC or DESC
     * @return RowGateway array the row results per the AdapterInterface fetch mode.
     */
    public function fetchAll($_where = NULL, $_order = NULL, $_dir = 'ASC', $_count = NULL, $_offset = NULL)
    {
        if($_dir != 'ASC' && $_dir != 'DESC') {
            throw new InvalidArgument('$_dir can be only ASC or DESC');
        }
        
        $order = NULL;
        if($_order !== NULL) {
            $order = $_order . ' ' . $_dir;
        }
        
        // possibility to tracing queries
        if (Core::isLogLevel(LogLevel::TRACE) && $config = Core::getConfig()->logger) {
            if ($config->traceQueryOrigins) {
                $e = new \Exception();
                Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "\n" .
                    "BACKTRACE: \n" . $e->getTraceAsString() . "\n" .
                    "SQL QUERY: \n" . $this->select()->assemble());
            }
        }

        $rowSet = parent::fetchAll($_where, $order, $_count, $_offset);
        
        return $rowSet;
    }
    
    /**
     * get total count of rows
     *
     * @param string|array|Select $_where
     */
    public function getTotalCount($_where)
    {
        $tableInfo = $this->info();

        if ($_where instanceof Select ) {
            $select = $_where;
        } else {
            $select = $this->getAdapter()->select();
            foreach((array)$_where as $where) {
                $select->where($where);
            }
        }
        
        $select->from($tableInfo['name'], array('count' => 'COUNT(*)'));
        
        $stmt = $this->getAdapter()->query($select);
        $result = $stmt->fetch();
        
        return $result['count'];
    }
    
    /**
     * get describe table from metadata cache
     * 
     * @param string $tableName
     * @param AdapterInterface $db
     * @return array
     */
    public static function getTableDescriptionFromCache($tableName, $db = NULL)
    {
        if ($db === NULL) {
            $db = Core::getDb();
        }
        
        $dbConfig = $db->getConfig();
        
        $cacheId = md5($dbConfig['host'] . $dbConfig['dbname'] . $tableName);
        
        // try to get description from in-memory cache & persistent cache
        try {
            $result = PerRequest::getInstance()->load(__CLASS__, __METHOD__, $cacheId, PerRequest::VISIBILITY_SHARED);
            if (is_array($result) && count($result) > 0) {
                return $result;
            }
        } catch (NotFound $tenf) {
            // do nothing
        }
        
        // read description from database
        $result = $db->describeTable($tableName);
        
        // if table does not exist (yet), $result is an empty array
        if (count($result) > 0) {
            // save result for next request
            PerRequest::getInstance()->save(__CLASS__, __METHOD__, $cacheId, $result, PerRequest::VISIBILITY_SHARED);
        }
        
        return $result;
    }

    public static function clearTableDescriptionInCache($tableName)
    {
        if (strpos($tableName, SQL_TABLE_PREFIX) !== 0) {
            $tableName = SQL_TABLE_PREFIX . $tableName;
        }
        $db = Core::getDb();
        $dbConfig = $db->getConfig();
        $cacheId = md5($dbConfig['host'] . $dbConfig['dbname'] . $tableName);
        PerRequest::getInstance()->reset(__CLASS__, __CLASS__ . '::getTableDescriptionFromCache',
            $cacheId);
    }
}
