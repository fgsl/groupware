<?php
namespace Fgsl\Groupware\Groupbase\Application;
use Fgsl\Groupware\Groupbase\Controller\AbstractController;
use Fgsl\Groupware\Groupbase\Exception\Exception;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * generic controller for applications
 *
 * @package     Groupbase
 * @subpackage  Controller
 */
class Controller extends AbstractController
{
    /**
     * Tinebase_Controller_Abstract constructor.
     *
     * @param string $applicationName
     */
    public function __construct($applicationName)
    {
        $this->_applicationName = $applicationName;
    }

    /**
     * Instance of Controller Object.
     */
    public static function getInstance()
    {
        throw new Exception('Use the constructor');
    }
}
