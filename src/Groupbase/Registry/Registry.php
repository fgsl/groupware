<?php
namespace Fgsl\Groupware\Groupbase;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
final class Registry
{
    /**
     * @var string
     */
    const DB_ADAPTER = 'DbAdapter';
    /**
     * @var string
     */
    const SERVICE_MANAGER = 'ServiceManager';
    
    /**
     * @var array
     */
    private static $data = [];
    
    public static function isRegistered($index)
    {
        return isset(self::$data[$index]);
    }

    /**
     * @param string $index
     * @return string
     */
    public static function get($index)
    {
        return self::data[$index];
    }
    
    /**
     * @param string $index
     * @param mixed $value
     */
    public static function set($index, $value)
    {
        self::$data[$index] = $value;
    }
    
    /**
     * @param string $service
     * @return string
     */
    public static function getService($service)
    {
        return self::data[self::SERVICE_MANAGER]->get($service);
    }
}