<?php
namespace Fgsl\Groupware\Groupbase\Model\Converter;

use Fgsl\Groupware\Groupbase\DateTime;

/**
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

class Date extends DateTime
{
    /**
     * @param $fieldValue
     * @return string
     */
    static public function convertToData($fieldValue)
    {
        if ($fieldValue instanceof \DateTime) {
            return $fieldValue->format('Y-m-d');
        }
        return $fieldValue;
    }
}