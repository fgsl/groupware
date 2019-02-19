<?php
namespace Fgsl\Groupware\Groupbase\Auth\ModSsl\UsernameCallback;
/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
interface UsernameCallbackInterface
{
    public function getUsername();
}