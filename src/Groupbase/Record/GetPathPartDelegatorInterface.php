<?php
namespace Fgsl\Groupware\Groupbase\Record;
/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * interface for getPathPart() decorator
 *
 * @package     Groupbase
 * @subpackage  Record
 */
interface AbstractGetPathPartDelegatorInterface
{
    /**
     * @param RecordInterface $_record
     * @param RecordInterface|null $_parent
     * @param RecordInterface|null $_child
     * @return string
     */
    public function getPathPart(RecordInterface $_record, RecordInterface $_parent = null, RecordInterface $_child = null);
}