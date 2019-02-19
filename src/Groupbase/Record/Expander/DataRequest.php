<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;

use Fgsl\Groupware\Groupbase\Controller\Record\RecordInterface as ControllerRecordInterface;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Exception\Record\NotAllowed;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
class DataRequest
{
    public $prio;
    /**
     * @var ControllerRecordInterface
     */
    public $controller;
    public $ids;
    public $callback;
    protected $_merged = false;
    protected static $_dataCache = [];

    public function __construct($prio, $controller, $ids, $callback)
    {
        $this->prio = $prio;
        $this->controller = $controller;
        $this->ids = $ids;
        $this->callback = $callback;
    }

    public function merge(DataRequest $_dataRequest)
    {
        $this->ids = array_merge($this->ids, $_dataRequest->ids);
        $this->_merged = true;
    }

    public function getData()
    {
        if ($this->_merged) {
            $this->ids = array_unique($this->ids);
            $this->_merged = false;
        }

        // get instances from datacache
        $data = static::_getInstancesFromCache($this->controller->getModel(), $this->ids);

        if (!empty($this->ids)) {
            $newRecords = $this->controller->getMultiple($this->ids);
            static::_addInstancesToCache($this->controller->getModel(), $newRecords);
            $data->mergeById($newRecords);
        }

        return $data;
    }

    protected static function _addInstancesToCache($_model, RecordSet $_data)
    {
        if (!isset(static::$_dataCache[$_model])) {
            static::$_dataCache[$_model] = [];
        }
        $array = &static::$_dataCache[$_model];

        /** @var Tinebase_Record_Abstract $record */
        foreach ($_data as $record) {
            $array[$record->getId()] = $record;
        }

    }
    /**
     * @param string $_model
     * @param $_ids
     * @return RecordSet
     * @throws InvalidArgument
     * @throws NotAllowed
     */
    protected static function _getInstancesFromCache($_model, &$_ids)
    {
        $data = new RecordSet($_model);
        if (isset(static::$_dataCache[$_model])) {
            foreach ($_ids as $key => $id) {
                if (isset(static::$_dataCache[$_model][$id])) {
                    $data->addRecord(static::$_dataCache[$_model][$id]);
                    unset($_ids[$key]);
                }
            }
        }

        return $data;
    }

    public static function clearCache()
    {
        static::$_dataCache = [];
    }
}