<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Core;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
class RecordsProperty extends Property
{
    protected function _lookForDataToFetch(RecordSet $_records)
    {
        $ids = [];
        $this->_recordsToProcess = $_records;
        foreach ($_records->{$this->_property} as $data) {
            if (is_array($data)) {
                $ids = array_merge($ids, $data);
            }
        }
        if (!empty($ids) || !empty($this->_subExpanders)) {
            $ids = array_unique($ids);
            $self = $this;
            $this->_rootExpander->_registerDataToFetch(new DataRequest(
                $this->_prio, Core::getApplicationInstance($this->_model), $ids,
                // workaround: [$this, '_setData'] doesn't work, even so it should!
                function ($_data) use ($self) {
                    $self->_setData($_data);
                }));
        }
    }

    /** this will not clone the records.... they are the same instance in different parents! */
    protected function _setData(RecordSet $_data)
    {
        /** @var Tinebase_Record_Interface $record */
        foreach ($this->_recordsToProcess as $record) {
            $data = $record->{$this->_property};
            if (!is_array($data)) {
                if ($data instanceof RecordSet) {
                    $_data->mergeById($data);
                }
                continue;
            }
            $result = new RecordSet([], $this->_model);
            foreach ($data as $id) {
                if (null !== ($r = $_data->getById($id))) {
                    $result->addRecord($r);
                }
            }
            $record->{$this->_property} = $result;
        }

        // clean up
        $this->_recordsToProcess = null;

        $this->expand($_data);
    }
}