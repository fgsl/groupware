<?php
namespace Fgsl\Groupware\Groupbase\Record;

use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * class to hold a list of records
 *
 * records are held as a unsorted set with a autoasigned numeric index.
 * NOTE: the index of an record is _not_ related to the record and/or its identifier!
 *
 * @package     Groupbase
 * @subpackage  Record
 *
 */
class RecordSetFast extends RecordSet
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct($_className, array &$_data)
    {
        if (! class_exists($_className)) {
            throw new InvalidArgument('Class ' . $_className . ' does not exist');
        }
        $this->_recordClass = $_className;

        foreach ($_data as &$data) {
            /** @var Tinebase_Record_Interface $toAdd */
            $toAdd = new $this->_recordClass(null, true);
            $toAdd->hydrateFromBackend($data);
            $this->addRecord($toAdd);
        }
    }
}