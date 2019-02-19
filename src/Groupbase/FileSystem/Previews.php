<?php
namespace Fgsl\Groupware\Groupbase\FileSystem;
use Fgsl\Groupware\Groupbase\FileSystem\Preview\ServiceInterface;
use Fgsl\Groupware\Groupbase\Model\Tree\Node;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Model\Tree\FileObject;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\TempFile;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\TransactionManager;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * filesystem preview images for file revisions
 *
 * @package     Groupbase
 * @subpackage  Filesystem
 */
class Previews
{
    /**
     * @var ServiceInterface
     */
    protected $_previewService = NULL;

    /**
     * filesystem controller
     *
     * @var FileSystem
     */
    protected $_fsController = NULL;

    /**
     * @var string
     */
    protected $_basePath = NULL;

    /**
     * @var Node
     */
    protected $_basePathNode = NULL;

    /**
     * @var array
     */
    protected $_supportedFileExtensions = array(
        'txt', 'rtf', 'odt', 'ods', 'odp', 'doc', 'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx', 'pdf', 'jpg', 'jpeg', 'gif', 'tiff', 'png'
    );

    /**
     * holds the instance of the singleton
     *
     * @var Previews
     */
    private static $_instance = NULL;

    /**
     * the constructor
     */
    protected function __construct()
    {
        $this->_fsController = FileSystem::getInstance();
        $this->_previewService = Core::getPreviewService();
    }

    /**
     * sets the preview service to be used. returns the old service
     *
     * @param ServiceInterface $_service
     * @return ServiceInterface
     */
    public function setPreviewService(ServiceInterface $_service)
    {
        $return = $this->_previewService;
        $this->_previewService = $_service;
        return $return;
    }

    /**
     * the singleton pattern
     *
     * @return Previews
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Previews();
        }

        return self::$_instance;
    }

    /**
     * @return string
     */
    protected function _getBasePath()
    {
        if (null === $this->_basePath) {
            $this->_basePath = $this->_fsController->getApplicationBasePath(Application::getInstance()->getApplicationByName('Tinebase'), FileSystem::FOLDER_TYPE_PREVIEWS);
            if (!$this->_fsController->fileExists($this->_basePath)) {
                $this->_basePathNode = $this->_fsController->mkdir($this->_basePath);
            }
        }

        return $this->_basePath;
    }

    /**
     * @return Node
     */
    public function getBasePathNode()
    {
        if (null === $this->_basePathNode) {
            $this->_basePathNode = $this->_fsController->stat($this->_getBasePath());
        }
        return $this->_basePathNode;
    }

    /**
     * @param string $_fileExtension
     * @return bool
     */
    public function isSupportedFileExtension($_fileExtension)
    {
        return in_array(mb_strtolower($_fileExtension), $this->_supportedFileExtensions);
    }

    protected function _getConfig()
    {
        return array(
            'thumbnail' => array(
                'firstPage' => true,
                'filetype'  => 'jpg',
                'x'         => 142,
                'y'         => 200,
                'color'     => 'white'
            ),
            'previews'  => array(
                'firstPage' => false,
                'filetype'  => 'jpg',
                'x'         => 708,
                'y'         => 1000,
                'color'     => 'white'
            )
        );
    }

    /**
     * @param string|Node $_id
     * @param int $_revision
     * @return bool
     * @throws \Exception
     */
    public function createPreviews($_id, $_revision = null)
    {
        $node = $_id instanceof Node ? $_id : FileSystem::getInstance()->get($_id, $_revision);

        try {
            return $this->createPreviewsFromNode($node);
        } catch (\Exception $zdse) {
            // this might throw Deadlock exceptions - ignore those
            if (strpos($zdse->getMessage(), 'Deadlock') !== false) {
                Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                    . ' Ignoring deadlock / skipping preview generation - Error: '
                    . $zdse->getMessage());
                return false;
            } else {
                throw $zdse;
            }
        }
    }

    /**
     * @param Node $node
     * @return bool
     */
    public function canNodeHavePreviews(Node $node)
    {
        if ($node->type !== FileObject::TYPE_FILE || empty($node->hash) || $node->size == 0 ||
                Config::getInstance()->{Config::FILESYSTEM}
                ->{Config::FILESYSTEM_PREVIEW_MAX_FILE_SIZE} < $node->size) {
            return false;
        }
        $fileExtension = pathinfo($node->name, PATHINFO_EXTENSION);

        return $this->isSupportedFileExtension($fileExtension);
    }

    /**
     * @param Node $node
     * @return bool
     * @throws Exception
     */
    public function createPreviewsFromNode(Node $node)
    {
        if (!$this->canNodeHavePreviews($node)) {
            return true;
        }

        $fileSystem = FileSystem::getInstance();
        $path = $fileSystem->getRealPathForHash($node->hash);
        $tempPath = TempFile::getTempPath() . '.' . pathinfo($node->name, PATHINFO_EXTENSION);
        if (!is_file($path)) {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                . ' file ' . $node->getId() . ' ' . $node->name . ' is not present in filesystem: ' . $path);
            return false;
        }
        if (false === copy($path, $tempPath)) {
            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                . ' could not copy file ' . $node->getId() . ' ' . $node->name . ' ' . $path . ' to temp path: '
                . $tempPath);
            return false;
        }

        try {
            $config = $this->_getConfig();

            if (false === ($result = $this->_previewService->getPreviewsForFile($tempPath, $config))) {
                if (Core::isLogLevel(LogLevel::WARN)) {
                    Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . ' preview creation for file ' . $node->getId() . ' ' . $node->name . ' failed');
                }
                return false;
            }
        } finally {
            unlink($tempPath);
        }

        foreach($config as $key => $cnf) {
            if (!isset($result[$key])) {
                return false;
            }
        }

        // reduce deadlock risk. We (remove and) create the base folder outside the transaction. This will fill
        // the stat cache and the update on the directory tree hashes will happen without prio read locks
        $basePath = $this->_getBasePath() . '/' . substr($node->hash, 0, 3) . '/' . substr($node->hash, 3);
        if (!$fileSystem->isDir($basePath)) {
            $fileSystem->mkdir($basePath);
        } else {
            $fileSystem->rmdir($basePath, true);
            $fileSystem->mkdir($basePath);
        }
        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());

        try {

            $files = array();
            $basePath = $this->_getBasePath() . '/' . substr($node->hash, 0, 3) . '/' . substr($node->hash, 3);
            if (!$fileSystem->isDir($basePath)) {
                $fileSystem->mkdir($basePath);
            }

            $maxCount = 0;
            foreach ($config as $key => $cnf) {
                $i = 0;
                foreach ($result[$key] as $blob) {
                    $files[$basePath . '/' . $key . '_' . ($i++) . '.' . $cnf['filetype']] = $blob;
                }
                if ($i > $maxCount) {
                    $maxCount = $i;
                }
            }

            unset($result);

            if ((int)$node->preview_count !== $maxCount) {
                $fileSystem->updatePreviewCount($node->hash, $maxCount);
            }

            foreach ($files as $name => &$blob) {
                $tempFile = TempFile::getTempPath();
                if (false === file_put_contents($tempFile, $blob)) {
                    throw new Exception('could not write content to temp file');
                }
                try {
                    $blob = null;
                    if (false === ($fh = fopen($tempFile, 'r'))) {
                        throw new Exception('could not open temp file for reading');
                    }

                    // this means we create a file node of type preview
                    $fileSystem->setStreamOptionForNextOperation(FileSystem::STREAM_OPTION_CREATE_PREVIEW,
                        true);
                    $fileSystem->copyTempfile($fh, $name);
                    fclose($fh);
                } finally {
                    unlink($tempFile);
                }
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;

        } finally {
            if (null !== $transactionId) {
                // this only happens if an exception is thrown, no need to return false
                TransactionManager::getInstance()->rollBack();
            }
        }

        return true;
    }

    /**
     * @param Node $_node
     * @param string $_type
     * @param int $_num
     * @return Node
     * @throws NotFound
     */
    public function getPreviewForNode(Node $_node, $_type, $_num)
    {
        if (empty($_node->hash) || strlen($_node->hash) < 4) {
            throw new NotFound('node needs to have proper hash set');
        }

        $config = $this->_getConfig();
        if (!isset($config[$_type])) {
            throw new NotFound('type ' . $_type . ' not configured');
        }

        $fileSystem = FileSystem::getInstance();
        $path = $this->_getBasePath() . '/' . substr($_node->hash, 0, 3) . '/' . substr($_node->hash, 3)
                . '/' . $_type . '_' . $_num . '.' . $config[$_type]['filetype'];

        return $fileSystem->stat($path);
    }

    /**
     * @param Node $_node
     * @return bool
     * @throws InvalidArgument
     */
    public function hasPreviews(Node $_node)
    {
        return $_node->preview_count > 0;
    }

    /**
     * @param array $_hashes
     */
    public function deletePreviews(array $_hashes)
    {
        $fileSystem = FileSystem::getInstance();
        $basePath = $this->_getBasePath();
        foreach($_hashes as $hash) {
            try {
                $fileSystem->rmdir($basePath . '/' . substr($hash, 0, 3) . '/' . substr($hash, 3), true);
                // these hashes are unchecked, there may not be previews for them! => catch, no logging (debug at most)
            } catch(NotFound $tenf) {}
        }
    }

    /**
     * @return bool
     */
    public function deleteAllPreviews()
    {
        return FileSystem::getInstance()->rmdir($this->_getBasePath(), true);
    }
}