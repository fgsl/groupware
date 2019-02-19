<?php
namespace Fgsl\Groupware\Setup\Backend;
use Fgsl\Groupware\Setup\Exception\Exception;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;

/**
 * @package     Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * backend factory class for the Setup
 *
 * @package     Setup
 */
class BackendFactory
{

    static protected $_instanceCache = array();

    /**
     * factory function to return a selected setup backend class
     *
     * @param string|null $_type
     * @return BackendInterface
     * @throws Exception
     * @throws InvalidArgument
     */
    static public function factory($_type = null)
    {
        if (isset(static::$_instanceCache[$_type])) {
            return static::$_instanceCache[$_type];
        }

        if (empty($_type)) {
            $db = Core::getDb();
            $adapterName = get_class($db);

            // get last part of class name
            if (empty($adapterName) || strpos($adapterName, '_') === FALSE) {
                throw new Exception('Could not get DB adapter name.');
            }
            $adapterNameParts = explode('_',$adapterName);
            $type = array_pop($adapterNameParts);
            
            // special handling for Oracle
            $type = str_replace('Oci', Core::ORACLE, $type);
            
            $className = 'Setup_Backend_' . ucfirst($type);
        } else {
            $className = 'Setup_Backend_' . ucfirst($_type);
        }
        
        if (!class_exists($className)) {
            throw new InvalidArgument('Invalid database backend type defined.');
        }
        
        $instance = new $className();

        static::$_instanceCache[$_type] = $instance;

        return $instance;
    }

    static public function clearCache()
    {
        static::$_instanceCache = [];
    }
}
