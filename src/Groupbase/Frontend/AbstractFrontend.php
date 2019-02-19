<?php
use Fgsl\Groupware\Groupbase\Helper;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Abstract class for an Groupware application
 * 
 * @package     Groupbase
 * @subpackage  Application
 */
abstract class AbstractFrontend implements FrontendInterface
{
    /**
     * Application name
     *
     * @var string
     */
    protected $_applicationName;

    /**
     * returns function parameter as object, decode Json if needed
     *
     * Prepare function input to be an array. Input maybe already an array or (empty) text.
     * Starting PHP 7 Zend_Json::decode can't handle empty strings.
     *
     * @param  mixed $_dataAsArrayOrJson
     * @return array
     */
    protected function _prepareParameter($_dataAsArrayOrJson)
    {
        return Helper::jsonDecode($_dataAsArrayOrJson);
    }
}