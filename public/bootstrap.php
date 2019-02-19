<?php
use Zend\Config\Config;
use Zend\Config\Writer\PhpArray;
use Fgsl\Groupware\Groupbase\Registry;
use Zend\ServiceManager\ServiceManager;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
require __DIR__ . '/../vendor/autoload.php';

$configData = [];
if (file_exists(__DIR__ . '/../data/cache/config.cache.php'))
{
    $configData = include __DIR__ . '/../data/config.cache.php';
} else {
    $configData = array_merge(require  __DIR__ . '/../config/application.config.php', require __DIR__ . '/../data/global.cache.php');
    if (file_exists(__DIR__ . '/../config/local.config.php'))
    {
        $configData = array_merge($configData, include __DIR__ . '/../data/local.config.php');
        $configWriter = new PhpArray();
        $configWriter->toFile(__DIR__ . '/../data/cache/local.cache.php', $configData);
    }
}
$serviceManager = new ServiceManager();
if (!isset($configData['service_manager'])){
    throw new \Exception('Application needs configuration of service manager!');
}
foreach($configData['service_manager']['factories'] as $name => $factory )
{
    $serviceManager->setFactory($name, $factory);
}
Registry::set(Registry::SERVICE_MANAGER,$serviceManager);
