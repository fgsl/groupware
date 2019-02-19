<?php
namespace Fgsl\Groupware\Groupbase;

use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Controller\ControllerInterface;
use Fgsl\Groupware\Groupbase\Model\TempFile as ModelTempFile;
use Fgsl\Groupware\Groupbase\Model\TempFileFilter as ModelTempFileFilter;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Lock\Lock;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Record\RecordSet;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class of persistant temp files
 * 
 * This class handles generation of tempfiles and registers them in a tempFile table.
 * To access a tempFile, the session of the client must match
 * 
 * @package     Tinebase
 * @subpackage  File
 */
class TempFile extends AbstractSql implements ControllerInterface
{
    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'temp_files';
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'ModelTempFile';
    
    /**
     * holds the instance of the singleton
     *
     * @var TempFile
     */
    private static $_instance = NULL;
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}
    
    /**
     * the singleton pattern
     *
     * @return TempFile
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new TempFile();
        }
        
        return self::$_instance;
    }
    
    /**
     * get temp file description from db
     *
     * @param mixed $_fileId
     * @return ModelTempFile
     */
    public function getTempFile($_fileId)
    {
        $fileId = is_array($_fileId) ? $_fileId['id'] : $_fileId;
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . " Fetching temp file with id " . print_r($fileId, true));
        
        $select = $this->_getSelect('*');
        $select->where($this->_db->quoteIdentifier('id') . ' = ?', $fileId)
               ->where($this->_db->quoteIdentifier('session_id') . ' = ?', Core::getSessionId(/* $generateUid */ false));

        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();
        
        if (!$queryResult) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " Could not fetch row with id $fileId from temp_files table.");
            return NULL;
        }

        $result = new ModelTempFile($queryResult);
        return $result;
    }
    
    /**
     * uploads a file and saves it in the db
     *
     * @todo separate out frontend code
     * @todo work on a file model
     *  
     * @return ModelTempFile
     * @throws Exception
     * @throws NotFound
     * @throws UnexpectedValue
     */
    public function uploadTempFile()
    {
        $path = self::getTempPath();
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . " XMLHttpRequest style upload to path " . $path);
            
            $name =       base64_decode($_SERVER['HTTP_X_FILE_NAME']);
            $size =       (double) $_SERVER['HTTP_X_FILE_SIZE'];
            $type =       $_SERVER['HTTP_X_FILE_TYPE'];
            $error =      0;
            
            if ($name === false) {
                throw new Exception('Can\'t decode base64 string, no base64 provided?');
            }
            
            $success = copy("php://input", $path);
            if (! $success) {
                // try again with stream_copy_to_stream
                $input = fopen("php://input", 'r');
                if (! $input) {
                    throw new NotFound('No valid upload file found or some other error occurred while uploading! ');
                }
                $tempfileHandle = fopen($path, "w");
                if (! $tempfileHandle) {
                    throw new Exception('Could not open tempfile while uploading! ');
                }
                $size = (double) stream_copy_to_stream($input, $tempfileHandle);
                fclose($input);
                fclose($tempfileHandle);
            }
            
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . " successfully created tempfile at {$path}");
        } else {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . " Plain old form style upload");
            
            $uploadedFile = $_FILES['file'];
            
            $name  = $uploadedFile['name'];
            $size  = (double) $uploadedFile['size'];
            $type  = $uploadedFile['type'];
            $error = $uploadedFile['error'];
            
            if ($uploadedFile['error'] == UPLOAD_ERR_FORM_SIZE) {
                throw new Exception('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.');
            }
            if (! move_uploaded_file($uploadedFile['tmp_name'], $path)) {
                throw new NotFound('No valid upload file found or some other error occurred while uploading! ' . print_r($uploadedFile, true));
            }
        }
        
        return $this->createTempFile($path, $name, $type, $size, $error);
    }
    
    /**
     * creates temp filename
     * 
     * @throws UnexpectedValue
     * @return string
     */
    public static function getTempPath()
    {
        $path = tempnam(Core::getTempDir(), 'tine_tempfile_');
        if (! $path) {
            throw new UnexpectedValue('Can not upload file, tempnam() could not return a valid filename!');
        }
        return $path;
    }
    
    /**
     * create new temp file
     * 
     * @param string $_path
     * @param string $_name
     * @param string $_type
     * @param integer $_size
     * @param integer $_error
     * @return ModelTempFile
     */
    public function createTempFile($_path, $_name = 'tempfile.tmp', $_type = 'unknown', $_size = 0, $_error = 0)
    {
        // sanitize filename (convert to utf8)
        $filename = Helper::mbConvertTo($_name);
        
        $id = ModelTempFile::generateUID();
        $tempFile = new ModelTempFile(array(
           'id'          => $id,
           'session_id'  => Core::getSessionId(/* $generateUid */ false),
            'time'        => DateTime::now()->get(AbstractRecord::ISO8601LONG),
           'path'        => $_path,
           'name'        => $filename,
           'type'        => ! empty($_type)  ? $_type  : 'unknown',
           'error'       => ! empty($_error) ? $_error : 0,
           'size'        => ! empty($_size)  ? (double) $_size  : (double) filesize($_path),
        ));
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . " tempfile data: " . print_r($tempFile->toArray(), TRUE));
        
        $this->create($tempFile);
        
        return $tempFile;
    }
    
    /**
     * joins all given tempfiles in given order to a single new tempFile
     * 
     * @param RecordSet $_tempFiles
     * @return ModelTempFile
     */
    public function joinTempFiles($_tempFiles)
    {
        $path = self::getTempPath();
        $name = preg_replace('/\.\d+\.chunk$/', '', $_tempFiles->getFirstRecord()->name);
        $type = $_tempFiles->getFirstRecord()->type;
        $size = 0.0;
        
        $fJoin = fopen($path, 'w+b');
        foreach ($_tempFiles as $tempFile) {
            $fChunk = @fopen($tempFile->path, "rb");
            if (! $fChunk) {
                throw new UnexpectedValue("Can not open chunk {$tempFile->id}");
            }
            
            // NOTE: stream_copy_to_stream is about 15% slower
            while (!feof($fChunk)) {
                $bytesWritten = fwrite($fJoin, fread($fChunk, 2097152 /* 2 MB */));
                $size += (double) $bytesWritten;
            }
            fclose($fChunk);
        }
        
        fclose($fJoin);
        
        return $this->createTempFile($path, $name, $type, $size);
    }
    
    /**
     * remove all temp file records before $_date
     * 
     * @param DateTime|string $_date
     * @return bool
     */
    public function clearTableAndTempdir($_date = NULL)
    {
        $date = ($_date === NULL) ? DateTime::now()->subHour(6) : $_date;
        if (! $date instanceof DateTime) {
            $date = new DateTime($date);
        }
        
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Removing all temp files prior ' . $date->toString());
        
        $tempfiles = $this->search(new ModelTempFileFilter(array(array(
            'field'     => 'time',
            'operator'  => 'before',
            'value'     => $date
        ))));
        
        foreach ($tempfiles as $file) {
            if (file_exists($file->path)) {
                unlink($file->path);
            } else {
                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' File no longer found: ' . $file->path);
            }

            Lock::keepLocksAlive();
        }

        $result = $this->delete($tempfiles->getArrayOfIds());
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Removed ' . $result . ' temp files from database and filesystem.');

        $result = 0;
        foreach (new \DirectoryIterator(Core::getTempDir()) as $directoryIterator) {
            if ($directoryIterator->isFile() && $date->isLater(new DateTime($directoryIterator->getMTime()))) {
                unlink($directoryIterator->getPathname());
                ++$result;
            }

            Lock::keepLocksAlive();
        }
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Removed ' . $result . ' temp files from filesystem only.');

        return true;
    }
    
    /**
     * open a temp file
     *
     * @param boolean $createTempFile
     * @throws Exception
     * @return resource
     */
    public function openTempFile($createTempFile = true)
    {
        $path = self::getTempPath();
        
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Opening temp file ' . $path);
        
        $handle = fopen($path, 'w+');
        if (! $handle) {
            throw new Exception('Could not create temp file in ' . dirname($path));
        }

        if ($createTempFile) {
            $this->createTempFile($path);
        }
        
        return $handle;
    }
}
