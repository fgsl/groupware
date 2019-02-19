<?php
namespace Fgsl\Groupware\Groupbase\Model\Tree\Node;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\FileSystem\FileSystem;
use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Fgsl\Groupware\Groupbase\Model\Tree\Node;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Model\FullUser;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class representing one node path
 * 
 * @package     Groupbase
 * @subpackage  Model
 * 
 * @property    string                      containerType
 * @property    string                      containerOwner
 * @property    string                      flatpath           "real" name path like /personal/user/containername
 * @property    string                      statpath           id path like /personal/USERID/nodeName1/nodeName2/...
 * @property    string                      realpath           path without app/type/container stuff 
 * @property    string                      streamwrapperpath
 * @property    ModelApplication  application
 * @property    Tinebase_Model_FullUser     user
 * @property    string                      name (last part of path)
 * @property    Path parentrecord
 * 
 * @todo rename this to Node_FoldersPath ?
 * 
 * exploded flat path should look like this:
 * 
 * [0] => app id [required]
 * [1] => folders [required]
 * [2] => type [required] (personal|shared)
 * [3] => container name | accountLoginName
 * [4] => container name | directory
 * [5] => directory
 * [6] => directory
 * [...]
 */
class Path extends AbstractRecord
{
    /**
     * streamwrapper path prefix
     */
    const STREAMWRAPPERPREFIX = 'tine20://';
    
    /**
     * root type
     */
    const TYPE_ROOT = 'root';

    /**
     * folders path part
     */
    const FOLDERS_PART = 'folders';

    /**
     * records path part
     */
    const RECORDS_PART = 'records';

    /**
     * key in $_validators/$_properties array for the field which 
     * represents the identifier
     * 
     * @var string
     */
    protected $_identifier = 'flatpath';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators = array (
        'containerType'     => array(Input::ALLOW_EMPTY => true),
        'containerOwner'    => array(Input::ALLOW_EMPTY => true),
        'flatpath'          => array(Input::ALLOW_EMPTY => true),
        'statpath'          => array(Input::ALLOW_EMPTY => true),
        'realpath'          => array(Input::ALLOW_EMPTY => true),
        'streamwrapperpath' => array(Input::ALLOW_EMPTY => true),
        'application'       => array(Input::ALLOW_EMPTY => true),
        'user'              => array(Input::ALLOW_EMPTY => true),
        'name'              => array(Input::ALLOW_EMPTY => true),
        'parentrecord'      => array(Input::ALLOW_EMPTY => true),
    );
    
    /**
     * (non-PHPdoc)
     * @see Tinebase/Record/Tinebase_Record_Abstract::__toString()
     */
    public function __toString()
    {
        return $this->flatpath;
    }
    
    /**
     * create new path record from given (flat) path string like this:
     *  /c09439cb1d73e923b31affdecb8f2c8feff90d66/folders/personal/f11458741d0319755a7366c1d782172ecbf1305f
     * 
     * @param string|Path $_path
     * @return Path
     */
    public static function createFromPath($_path)
    {
        $pathRecord = ($_path instanceof Path) ? $_path : new Path(array(
            'flatpath'  => $_path
        ));
        
        return $pathRecord;
    }

    /**
     * create new path record from given stat path (= path with ids) string
     *
     * @param string $statPath
     * @param string $appName
     * @return Path
     */
    public static function createFromStatPath($statPath, $appName = null)
    {
        $statPath = trim($statPath, '/');
        $pathParts = explode('/', $statPath);
        if ($appName !== null) {
            $app = Application::getInstance()->getApplicationByName($appName);
            array_unshift($pathParts, $app->getId(), self::FOLDERS_PART);
        }
        $newStatPath = '/' . implode('/', $pathParts);

        if (count($pathParts) > 3) {
            // replace account id with login name
            try {
                $user = User::getInstance()->getUserByPropertyFromSqlBackend('accountId', $pathParts[3], 'Tinebase_Model_FullUser');
                $containerType = FileSystem::FOLDER_TYPE_PERSONAL;
                $pathParts[3] = $user->accountLoginName;
            } catch (NotFound $tenf) {
                // not a user -> shared
                $containerType = FileSystem::FOLDER_TYPE_SHARED;
            }
        } else if (count($pathParts) === 3) {
            $containerType = (in_array($pathParts[2], array(
                FileSystem::FOLDER_TYPE_SHARED,
                FileSystem::FOLDER_TYPE_PERSONAL
            ))) ? $pathParts[2] : self::TYPE_ROOT;
        } else {
            $containerType = self::TYPE_ROOT;
        }

        $flatPath = '/' . implode('/', $pathParts);
        $pathRecord = new Path(array(
            'flatpath'      => $flatPath,
            'containerType' => $containerType,
            'statpath'      => $newStatPath,
        ));

        return $pathRecord;
    }

    /**
     * create new parent path record from given path string
     * 
     * @param string $_path
     * @return array with (Path, string)
     * 
     * @todo add child to model?
     */
    public static function getParentAndChild($_path)
    {
        $pathParts = $pathParts = explode('/', trim($_path, '/'));
        $child = array_pop($pathParts);
        
        $pathRecord = Path::createFromPath('/' . implode('/', $pathParts));
        
        return array(
            $pathRecord,
            $child
        );
    }
    
    /**
     * removes app id (and /folders namespace) from a path
     * 
     * @param string $_flatpath
     * @param ModelApplication|string $_application
     * @return string
     */
    public static function removeAppIdFromPath($_flatpath, $_application)
    {
        $appId = (is_string($_application)) ? Application::getInstance()->getApplicationById($_application)->getId() : $_application->getId();
        return preg_replace('@^/' . $appId . '/' . self::FOLDERS_PART . '@', '', $_flatpath);
    }
    
    /**
     * get parent path of this record
     * 
     * @return Path
     */
    public function getParent()
    {
        if (! $this->parentrecord) {
            list($this->parentrecord, $unused) = self::getParentAndChild($this->flatpath);
        }
        return $this->parentrecord;
    }
    
    /**
     * sets the record related properties from user generated input.
     * 
     * if flatpath is set, parse it and set the fields accordingly
     *
     * @param array $_data            the new data to set
     */
    public function setFromArray(array &$_data)
    {
        parent::setFromArray($_data);
        
        if (isset($_data['flatpath'])) {
            $this->_parsePath($_data['flatpath']);
        }
    }
    
    /**
     * parse given path: check validity, set container type, do replacements
     * 
     * @param string $_path
     */
    protected function _parsePath($_path = NULL)
    {
        if ($_path === NULL) {
            $_path = $this->flatpath;
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
            . ' Parsing path: ' . $_path);
        
        $pathParts = $this->_getPathParts($_path);
        
        $this->name                 = $pathParts[count($pathParts) - 1];
        $this->containerType        = isset($this->containerType) && in_array($this->containerType, array(
            FileSystem::FOLDER_TYPE_PERSONAL,
            FileSystem::FOLDER_TYPE_SHARED,
            FileSystem::FOLDER_TYPE_RECORDS,
            FileSystem::FOLDER_TYPE_PREVIEWS,
        )) ? $this->containerType : $this->_getContainerType($pathParts);
        $this->containerOwner       = $this->_getContainerOwner($pathParts);
        $this->application          = $this->_getApplication($pathParts);
        $this->statpath             = isset($this->statpath) ? $this->statpath : $this->_getStatPath($pathParts);
        $this->realpath             = $this->_getRealPath($pathParts);
        $this->streamwrapperpath    = self::STREAMWRAPPERPREFIX . $this->statpath;
    }
    
    /**
     * get path parts
     * 
     * @param string $_path
     * @return array
     * @throws InvalidArgument
     */
    protected function _getPathParts($_path = NULL)
    {
        if ($_path === NULL) {
            $_path = $this->flatpath;
        }
        if (! is_string($_path)) {
            throw new InvalidArgument('Path needs to be a string!');
        }
        $pathParts = explode('/', trim($_path, '/'));
        if (count($pathParts) < 1) {
            throw new InvalidArgument('Invalid path: ' . $_path);
        }
        
        return $pathParts;
    }
    
    /**
     * get container type from path:
     *  - type is ROOT for all paths with 3 or less parts
     * 
     * @param array $_pathParts
     * @return string
     * @throws InvalidArgument
     */
    protected function _getContainerType($_pathParts)
    {
        $containerType = isset($_pathParts[2])? $_pathParts[2] : self::TYPE_ROOT;
        
        if (! in_array($containerType, array(
            FileSystem::FOLDER_TYPE_PERSONAL,
            FileSystem::FOLDER_TYPE_SHARED,
            FileSystem::FOLDER_TYPE_RECORDS,
            FileSystem::FOLDER_TYPE_PREVIEWS,
            self::TYPE_ROOT
        ))) {
            throw new InvalidArgument('Invalid type: ' . $containerType);
        }
        
        return $containerType;
    }
    
    /**
     * get container owner from path
     * 
     * @param array $_pathParts
     * @return string
     */
    protected function _getContainerOwner($_pathParts)
    {
        $containerOwner = ($this->containerType === FileSystem::FOLDER_TYPE_PERSONAL && isset($_pathParts[3])) ? $_pathParts[3] : NULL;
        
        return $containerOwner;
    }
    
    /**
     * get application from path
     * 
     * @param array $_pathParts
     * @return string
     * @throws AccessDenied
     */
    protected function _getApplication($_pathParts)
    {
        $application = Application::getInstance()->getApplicationById($_pathParts[0]);
        
        return $application;
    }
    
    /**
     * do path replacements (container name => container id, account name => account id)
     * 
     * @param array $pathParts
     * @return string
     */
    protected function _getStatPath($pathParts = NULL)
    {
        if ($pathParts === NULL) {
            $pathParts = array(
                $this->application->getId(),
                'folders',
                $this->containerType,
            );
            
            if ($this->containerOwner) {
                $pathParts[] = $this->containerOwner;
            }

            if ($this->realpath) {
                $pathParts += explode('/', $this->realpath);
            }
            $this->flatpath = '/' . implode('/', $pathParts);
        }
        $result = $this->_createStatPathFromParts($pathParts);
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
            . ' Path to stat: ' . $result);
        
        return $result;
    }
    
    /**
     * create stat path from path parts
     * 
     * @param array $pathParts
     * @return string
     */
    protected function _createStatPathFromParts($pathParts)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($pathParts, TRUE));
        
        if (count($pathParts) > 3) {
            // replace account login name with id
            if ($this->containerOwner) {
                try {
                    $pathParts[3] = User::getInstance()->getUserByPropertyFromSqlBackend('accountLoginName', $this->containerOwner, 'Tinebase_Model_FullUser')->getId();
                } catch (NotFound $tenf) {
                    // try again with id
                    $accountId = is_object($this->containerOwner) ? $this->containerOwner->getId() : $this->containerOwner;
                    try {
                        $user = User::getInstance()->getUserByPropertyFromSqlBackend('accountId', $accountId, 'Tinebase_Model_FullUser');
                    } catch (NotFound $tenf) {
                        $user = User::getInstance()->getUserByPropertyFromSqlBackend('accountDisplayName', $accountId, 'Tinebase_Model_FullUser');
                    }
                    $pathParts[3] = $user->getId();
                    $this->containerOwner = $user->accountLoginName;
                }
            }
        }
        
        $result = '/' . implode('/', $pathParts);
        return $result;
    }
    
    /**
     * get real path
     * 
     * @param array $pathParts
     * @return NULL|string
     */
    protected function _getRealPath($pathParts)
    {
        $result = NULL;
        $firstRealPartIdx = ($this->containerType === FileSystem::FOLDER_TYPE_SHARED) ? 4 : 5;
        if (isset($pathParts[$firstRealPartIdx])) {
            $result = implode('/', array_slice($pathParts, $firstRealPartIdx));
        }
        
        return $result;
    }

    /**
     * check if this path is on the top level (last part / name is personal. shared or user id)
     *
     * @return boolean
     */
    public function isToplevelPath()
    {
        $parts = $this->_getPathParts();
        return  (count($parts) == 3 &&
            (   $this->containerType === FileSystem::FOLDER_TYPE_PERSONAL ||
                $this->containerType === FileSystem::FOLDER_TYPE_SHARED)) ||
                (count($parts) == 4 && $this->containerType === FileSystem::FOLDER_TYPE_PERSONAL);
    }

    /**
     * check if this path is above the top level (/application|records|folders)
     *
     * @return boolean
     */
    public function isSystemPath()
    {
        $parts = $this->_getPathParts();
        return  (count($parts) < 3) ||
            (count($parts) == 3 && $this->containerType === FileSystem::FOLDER_TYPE_PERSONAL);
    }


    /**
     * check if this path is the users personal path (/application/personal/account_id)
     *
     * @param string|ModelUser          $_accountId
     * @return boolean
     */
    public function isPersonalPath($_accountId)
    {
        $account = $_accountId instanceof FullUser
            ? $_accountId
            : User::getInstance()->getUserByPropertyFromSqlBackend('accountId', $_accountId, 'Tinebase_Model_FullUser');

        $parts = $this->_getPathParts();
        return (count($parts) == 4
            && $this->containerType === FileSystem::FOLDER_TYPE_PERSONAL
            && ($parts[3] === $account->getId() || $parts[3] === $account->accountLoginName)
        );
    }

    /**
     * returns true if path belongs to a record or record attachment
     *
     * @return bool
     * @throws InvalidArgument
     */
    public function isRecordPath()
    {
        $parts = $this->_getPathParts();
        return (count($parts) > 2 && $parts[2] === self::RECORDS_PART);
    }

    /**
     * validate node/container existance
     * 
     * @throws NotFound
     */
    public function validateExistance()
    {
        if (! $this->containerType || ! $this->statpath) {
            $this->_parsePath();
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Validate statpath: ' . $this->statpath);
        
        if (! FileSystem::getInstance()->fileExists($this->statpath)) {
            throw new NotFound('Node not found');
        }
    }

    /**
     * get node of path
     *
     * @return Node
     */
    public function getNode()
    {
        return FileSystem::getInstance()->stat($this->statpath);
    }

    /**
     * return path user
     *
     * @return FullUser
     *
     * TODO handle IDs or unresolved paths?
     */
    public function getUser()
    {
        if (! $this->user) {
            if ($this->containerOwner) {
                $this->user = User::getInstance()->getUserByPropertyFromSqlBackend(
                    'accountLoginName',
                    $this->containerOwner,
                    'Tinebase_Model_FullUser'
                );
            }
        }

        return $this->user;
    }
}
