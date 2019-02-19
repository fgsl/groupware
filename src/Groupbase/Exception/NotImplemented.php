<?php
namespace Fgsl\Groupware\Groupbase\Exception;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * NotImplemented exception
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class NotImplemented extends Exception
{
    public function __construct($message, $code=501) {
        parent::__construct($message, $code);
    }
}