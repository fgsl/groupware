<?php
namespace Fgsl\Groupware\Groupbase\Model\Converter;
use Fgsl\Groupware\Groupbase\Helper;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * Json
 *
 * Json Converter
 *
 * @package     Groupbase
 * @subpackage  Converter
 */

class Json implements ConverterInterface
{
    /**
     * @param $blob
     * @return mixed
     */
    static public function convertToRecord($blob)
    {
        return Helper::jsonDecode($blob);
    }

    /**
     * @param $fieldValue
     * @return string
     */
    static public function convertToData($fieldValue)
    {
        if (is_null($fieldValue)) {
            return $fieldValue;
        }
        return json_encode($fieldValue);
    }
}