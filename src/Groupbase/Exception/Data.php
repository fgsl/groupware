<?php
namespace Fgsl\Groupware\Groupbase\Exception;

use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Convert\ConvertFactory;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Tinebase exception with exception data
 * 
 * @package     Tinebase
 * @subpackage  Exception
 */
class Data extends ProgramFlow
{
    /**
     * exception data
     * 
     * @var RecordSet
     */
    protected $_exceptionData = NULL;
    
    /**
     * model name
     * 
     * @var string
     */
    protected $_modelName = NULL;
    
    /**
     * set model name
     * 
     * @param string $_modelName
     */
    public function setModelName($_modelName)
    {
        $this->_modelName = $_modelName;
    }
    
    /**
     * add record to exception data
     * 
     * @param RecordInterface $_record
     */
    public function addRecord(RecordInterface $_existingNode)
    {
        $this->getData()->addRecord($_existingNode);
    }
    
    /**
     * set exception data
     * 
     * @param RecordSet of RecordInterface
     */
    public function setData(RecordSet $_exceptionData)
    {
        $this->_exceptionData = $_exceptionData;
    }
        
    /**
     * get exception data
     * 
     * @return RecordSet of RecordInterface
     */
    public function getData()
    {
        if ($this->_exceptionData === NULL) {
            if (empty($this->_modelName)) {
                throw new NotFound('modelName not found in class.');
            }
        
            $this->_exceptionData = new RecordSet($this->_modelName);
        }
        
        return $this->_exceptionData;
    }
    
    /**
     * returns existing exception data as array
     * 
     * @return array
     */
    public function toArray()
    {
        return array(
            'code'            => $this->getCode(),
            'message'        => $this->getMessage(),
            'exceptionData' => $this->_dataToArray(),
        );
    }
    
    /**
    * get exception data as array
    *
    * @return array
    */
    protected function _dataToArray()
    {
        $result = array();
        if ($this->_exceptionData !== null) {
            $converter = ConvertFactory::factory($this->_modelName);
            $result = $converter->fromTine20RecordSet($this->_exceptionData);
        }
        
        return $result;
    }
}
