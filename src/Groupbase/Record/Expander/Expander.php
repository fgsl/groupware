<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;
use Fgsl\Groupware\Groupbase\Record\RecordSet;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

class Expander extends AbstractExpander
{
    protected $_dataToFetch = [];


    public function __construct($_model, $_expanderDefinition)
    {
        parent::__construct($_model, $_expanderDefinition, $this);
    }

    public function expand(RecordSet $_records)
    {
        parent::expand($_records);

        while (!empty($this->_dataToFetch)) {
            $this->_fetchData();
        }
    }

    protected function _fetchData()
    {
        $dataToFetch = $this->_dataToFetch;
        $this->_dataToFetch = [];
        ksort($dataToFetch);

        foreach ($dataToFetch as $controllerArray) {
            foreach ($controllerArray as $c => $dataRequestArray) {
                $currentDataRequest = null;
                /** @var Tinebase_Record_Expander_DataRequest $dataRequest */
                foreach ($dataRequestArray as $dataRequest) {
                    if (null === $currentDataRequest) {
                        $currentDataRequest = $dataRequest;
                    } else {
                        $currentDataRequest->merge($dataRequest);
                    }
                }

                $data = $currentDataRequest->getData();

                foreach ($dataRequestArray as $dataRequest) {
                    call_user_func($dataRequest->callback, $data);
                }
            }
        }
    }

    protected function _registerDataToFetch(Tinebase_Record_Expander_DataRequest $_dataRequest)
    {
        $cClass = get_class($_dataRequest->controller);
        if (!isset($this->_dataToFetch[$_dataRequest->prio])) {
            $this->_dataToFetch[$_dataRequest->prio] = [];
        }
        if (!isset($this->_dataToFetch[$_dataRequest->prio][$cClass])) {
            $this->_dataToFetch[$_dataRequest->prio][$cClass] = [];
        }
        $this->_dataToFetch[$_dataRequest->prio][$cClass][] = $_dataRequest;
    }

    protected function _lookForDataToFetch(RecordSet $_records)
    {
        throw new Tinebase_Exception_NotImplemented('do not call this method on ' . self::class);
    }

    protected function _setData(RecordSet $_data)
    {
        throw new Tinebase_Exception_NotImplemented('do not call this method on ' . self::class);
    }
}