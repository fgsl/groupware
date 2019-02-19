<?php
namespace Fgsl\Groupware\Groupbase\Model\Converter;
/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * Converter Interface
 *
 * @package     Groupbase
 * @subpackage  Converter
 */
interface ConverterInterface
{
    static function convertToRecord($blob);

    static function convertToData($fieldValue);
}