<?php
namespace Fgsl\Groupware\Groupbase\Log\Filter;

use Zend\Log\Filter\FilterInterface;
use Fgsl\Groupware\Groupbase\Core;

/**
 *
 * @package Groupbase
 * @license http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *           
 */

/**
 * user filter for Zend\Log logger
 *
 * @package Groupbase
 * @subpackage Log
 */
class User implements FilterInterface
{

    /**
     *
     * @var string
     */
    protected $_name;

    /**
     * Filter out any log messages not matching $name.
     *
     * @param string $name
     *            Username to log message
     * @throws \Exception
     */
    public function __construct($name)
    {
        if (! is_string($name)) {
            throw new \Exception('Name must be a string');
        }

        $this->_name = $name;
    }

    /**
     * Returns TRUE to accept the message, FALSE to block it.
     *
     * @param array $event
     *            event data
     * @return boolean accepted?
     */
    public function accept($event)
    {
        $username = (is_object(Core::getUser())) ? Core::getUser()->accountLoginName : '';
        return strtolower($this->_name) == strtolower($username) ? true : false;
    }

    public function filter(array $event)
    {}
}