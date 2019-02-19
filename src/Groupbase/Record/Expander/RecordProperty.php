<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

class RecordProperty extends Property
{
    protected function _lookForDataToFetch(RecordSet $_records)
    {
        $this->_recordsToProcess = $_records;
        $ids = array_filter($_records->getIdFromProperty($this->_property, false));
        if (!empty($ids)) {
            $self = $this;
            $this->_rootExpander->_registerDataToFetch(new DataRequest(
                $this->_prio, Core::getApplicationInstance($this->_model), $ids,
                // workaround: [$this, '_setData'] doesn't work, even so it should!
                function($_data) use($self) {$self->_setData($_data);}));
        }
    }

    protected function _setData(RecordSet $_data)
    {
        $expandData = new RecordSet($_data->getRecordClassName());

        /** @var Tinebase_Record_Abstract $record */
        foreach ($this->_recordsToProcess as $record) {
            if (null !== ($id = $record->getIdFromProperty($this->_property, false)) && false !== ($subRecord =
                    $_data->getById($id))) {
                $record->{$this->_property} = $subRecord;
                $expandData->addRecord($subRecord);
            } elseif ($record->{$this->_property} instanceof RecordInterface) {
                $expandData->addRecord($record->{$this->_property});
            }
        }
        // clean up
        $this->_recordsToProcess = null;

        $this->expand($expandData);
    }
}