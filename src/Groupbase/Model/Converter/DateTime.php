<?php
namespace Fgsl\Groupware\Groupbase\Model\Converter;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * DateTime Converter
 *
 * @package     Groupbase
 * @subpackage  Converter
 */

class DateTime implements ConverterInterface
{
    /**
     * @param $blob
     * @return mixed
     */
    static public function convertToRecord($blob)
    {
        if ($blob instanceof \DateTime) {
            return $blob;
        }
        return (int)$blob == 0 ? null : new DateTime($blob);
    }

    /**
     * @param $fieldValue
     * @return string
     */
    static public function convertToData($fieldValue)
    {
        if ($fieldValue instanceof DateTime) {
            return $fieldValue->format(AbstractRecord::ISO8601LONG);
        }
        return $fieldValue;
    }
}