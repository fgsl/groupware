<?php
namespace Fgsl\Groupware\Groupbase\Convert;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * interface for a class to convert between an external format and a Tine 2.0 record
 *
 * @package     Groupbase
 * @subpackage  Convert
 */
interface ConvertInterface
{
    /**
     * converts external format to RecordInterface
     * 
     * @param  mixed                     $_blob
     * @param  RecordInterface  $_record  update existing record
     * @return RecordInterface
     */
    public function toGroupwareModel($_blob, RecordInterface $_record = null);
    
    /**
     * converts RecordInterface to external format
     * 
     * @param  RecordInterface  $_record
     * @return mixed
     */
    public function fromGroupwareModel(RecordInterface $_record);
}
