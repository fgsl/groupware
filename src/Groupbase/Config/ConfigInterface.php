<?php
namespace Fgsl\Groupware\Groupbase\Config;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * interface for config classes, just used to enforce the abstract static function(s)
 *
 * @package     Groupbase
 * @subpackage  Config
 */
interface ConfigInterface
{
    /**
     * get properties definitions
     *
     * @return array
     */
    static function getProperties();
}