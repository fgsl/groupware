<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Filesystem
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Paul Mehrer <p.mehrer@metaways.de>
 * @copyright   Copyright (c) 2018 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * filesystem preview service implementation
 *
 * @package     Tinebase
 * @subpackage  Filesystem
 */
class Tinebase_FileSystem_Preview_ServiceV2 extends Tinebase_FileSystem_Preview_ServiceV1
{
    protected $_networkAdapter;

    /**
     * Tinebase_FileSystem_Preview_ServiceV2 constructor.
     * @param $networkAdapter Tinebase_FileSystem_Preview_NetworkAdapter
     */
    public function __construct($networkAdapter)
    {
        parent::__construct();
        $this->_networkAdapter = $networkAdapter;
    }

    /**
     * Uses the DocumentPreviewService to generate previews (images or pdf files) for multiple files of same type.
     *
     * {@inheritDoc}
     *
     * @param $filePaths array of file Paths to convert
     * @param array $config
     * @return array|bool
     * @throws Zend_Http_Client_Exception
     */
    public function getPreviewsForFiles(array $_filePaths, array $_config)
    {
        if (isset($_config['synchronRequest']) && $_config['synchronRequest']) {
            $synchronRequest = true;
        } else {
            $synchronRequest = false;
        }

        $httpClient = $this->_getHttpClient($synchronRequest);
        $httpClient->setMethod(Zend_Http_Client::POST);
        $httpClient->setParameterPost('config', json_encode($_config));

        foreach ($_filePaths as $filePath) {
            $httpClient->setFileUpload($filePath, 'files[]');
        }

        return $this->_requestPreviews($httpClient, $synchronRequest);
    }

    /**
     * @param boolean $_synchronRequest
     * @return Zend_Http_Client
     */
    protected function _getHttpClient($_synchronRequest)
    {
        return $this->_networkAdapter->getHttpsClient(array('timeout' => ($_synchronRequest ? 10 : 300)));
    }

    protected function _processJsonResponse(array $responseJson)
    {
        $response = array();
        foreach ($responseJson as $key => $files) {
            $response[$key] = array();
            foreach ($files as $file) {
                $blob = base64_decode($file);
                if (false === $blob) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) {
                        Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' couldn\'t base64decode response file ' . $key);
                    }
                    return false;
                }
                $response[$key][] = $blob;
            }
        }

        return $response;
    }
}
