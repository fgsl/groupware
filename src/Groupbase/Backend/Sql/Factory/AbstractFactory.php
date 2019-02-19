<?php
namespace Fgsl\Groupware\Groupbase\Backend\Sql\Factory;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Backend\Sql\Command\CommandInterface;
use Zend\Db\Adapter\Adapter;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Abstract factory for customized SQL statements
 *
 * @package     Groupbase
 * @subpackage  Backend
 */

class AbstractFactory
{
    protected static $_instances = array();
     
    /**
     * @param AdapterInterface $adapter
     * @return CommandInterface
    */
    public static function factory(AdapterInterface $adapter)
    {
        $className = get_called_class() . '_' . self::_getClassName($adapter);
         
        // @todo find better array key (add loginname and host)
        if (!isset(self::$_instances[$className])) {
            self::$_instances[$className] = new $className($adapter);
        }
         
        return self::$_instances[$className];
    }

    /**
     *
     * @param Adapter $adapter
     * @return string
     */
    private static function _getClassName($adapter)
    {
        $completeClassName = explode('_',get_class($adapter));
        $className = $completeClassName[count($completeClassName)-1];
        $className = str_replace('Oci','Oracle',$className);
         
        return $className;
    }    
}
