<?php
namespace Fgsl\Groupware\Groupbase\Server;

use Fgsl\Groupware\Groupbase\Session\AbstractSession;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Zend\Session\SessionManager;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\Server\Method\Definition;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Server Abstract with handle function
 * 
 * @package     Groupbase
 * @subpackage  Server
 */
abstract class AbstractServer implements ServerInterface
{
    /**
     * the request
     *
     * @var \Zend\Http\PhpEnvironment\Request
     */
    protected $_request = NULL;
    
    /**
     * the request body
     * 
     * @var resource|string
     */
    protected $_body;
    
    /**
     * set to true if server supports sessions
     * 
     * @var boolean
     */
    protected $_supportsSessions = false;

    /**
     * cache for modelconfig methods by frontend
     *
     * @var array
     */
    protected static $_modelConfigMethods = array();

    public function __construct()
    {
        if ($this->_supportsSessions) {
            AbstractSession::setSessionEnabled('GROUPWARESESSID');
        }
    }
    
    /**
     * read auth data from all available sources
     * 
     * @param \Zend\Http\PhpEnvironment\Request $request
     * @throws NotFound
     * @return array
     */
    protected function _getAuthData(\Zend\Http\PhpEnvironment\Request $request)
    {
        if ($authData = $this->_getPHPAuthData($request)) {
            return $authData;
        }
        
        if ($authData = $this->_getBasicAuthData($request)) {
            return $authData;
        }
        
        throw new NotFound('No auth data found');
    }
    
    /**
     * fetch auch from PHP_AUTH*
     * 
     * @param  \Zend\Http\PhpEnvironment\Request  $request
     * @return array
     */
    protected function _getPHPAuthData(\Zend\Http\PhpEnvironment\Request $request)
    {
        if ($request->getServer('PHP_AUTH_USER')) {
            return array(
                $request->getServer('PHP_AUTH_USER'),
                $request->getServer('PHP_AUTH_PW')
            );
        }
    }
    
    /**
     * fetch basic auth credentials
     * 
     * @param  \Zend\Http\PhpEnvironment\Request  $request
     * @return array
     */
    protected function _getBasicAuthData(\Zend\Http\PhpEnvironment\Request $request)
    {
        if ($header = $request->getHeaders('Authorization')) {
            return explode(
                ":",
                base64_decode(substr($header->getFieldValue(), 6)),  // "Basic didhfiefdhfu4fjfjdsa34drsdfterrde..."
                2
            );
            
        } elseif ($header = $request->getServer('HTTP_AUTHORIZATION')) {
            return explode(
                ":",
                base64_decode(substr($header, 6)),  // "Basic didhfiefdhfu4fjfjdsa34drsdfterrde..."
                2
            );
            
        } else {
            // check if (REDIRECT_)*REMOTE_USER is found in SERVER vars
            $name = 'REMOTE_USER';
            
            for ($i=0; $i<5; $i++) {
                if ($header = $request->getServer($name)) {
                    return explode(
                        ":",
                        base64_decode(substr($header, 6)),  // "Basic didhfiefdhfu4fjfjdsa34drsdfterrde..."
                        2
                    );
                }
                
                $name = 'REDIRECT_' . $name;
            }
        }
    }

    /**
     * get default modelconfig methods
     *
     * @param string $frontend
     * @return array of Zend_Server_Method_Definition
     */
    protected static function _getModelConfigMethods($frontend)
    {
        if (array_key_exists($frontend, AbstractServer::$_modelConfigMethods)) {
            return AbstractServer::$_modelConfigMethods[$frontend];
        }

        // get all apps user has RUN right for
        try {
            $userApplications = Core::getUser() ? Core::getUser()->getApplications() : array();
        } catch (NotFound $tenf) {
            // session might be invalid, destroy it
            $sessionManager = new SessionManager();
            $sessionManager->destroy(['send_expire_cookie']);
            $userApplications = array();
        }

        $definitions = array();
        foreach ($userApplications as $application) {
            try {
                $controller = Core::getApplicationInstance($application->name);
                $models = $controller->getModels();
                if (!$models) {
                    continue;
                }
            } catch (\Exception $e) {
                Exception::log($e);
                continue;
            }

            foreach ($models as $model) {
                $config = $model::getConfiguration();
                if ($frontend::exposeApi($config)) {
                    $simpleModelName = AbstractRecord::getSimpleModelName($application, $model);
                    $commonApiMethods = $frontend::_getCommonApiMethods($application, $simpleModelName);

                    foreach ($commonApiMethods as $name => $method) {
                        $key = $application->name . '.' . $name . $simpleModelName . ($method['plural'] ? 's' : '');
                        $object = $frontend::_getFrontend($application);

                        $definitions[$key] = new Definition(array(
                            'name'            => $key,
                            'prototypes'      => array(array(
                                'returnType' => 'array',
                                'parameters' => $method['params']
                            )),
                            'methodHelp'      => $method['help'],
                            'invokeArguments' => array(),
                            'object'          => $object,
                            'callback'        => array(
                                'type'   => 'instance',
                                'class'  => get_class($object),
                                'method' => $name . $simpleModelName . ($method['plural'] ? 's' : '')
                            ),
                        ));
                    }
                }
            }
        }

        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Got MC definitions: ' . print_r(array_keys($definitions), true));

        AbstractServer::$_modelConfigMethods[$frontend] = $definitions;

        return $definitions;
    }
}
