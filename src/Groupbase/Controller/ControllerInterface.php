<?php
namespace Fgsl\Groupware\Groupbase\Controller;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Controller interface get Controller instance
 * 
 * 
 * @package     Groupbase
 * @subpackage  Controller
 */
interface ControllerInterface 
{
    /**
     * Instance of Controller Object.
     */
    public static function getInstance();
}
