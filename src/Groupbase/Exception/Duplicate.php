<?php
namespace Fgsl\Groupware\Groupbase\Exception;

use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Convert\ConvertFactory;
use Fgsl\Groupware\Groupbase\Tags;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Groupbase duplicate exception 
 * 
 * @package     Tinebase
 * @subpackage  Exception
 */
class Duplicate extends Data
{
    /**
     * the client record
     * 
     * @var RecordInterface
     */
    protected $_clientRecord = NULL;
    
    /**
     * construct
     * 
     * @param string $_message
     * @param integer $_code
     * @return void
     */
    public function __construct($_message = 'data exception', $_code = 629)
    {
        parent::__construct($_message, $_code);
    }
    
    /**
     * set client record
     * 
     * @param RecordInterface $_record
     */
    public function setClientRecord(RecordInterface $_record)
    {
        $this->_clientRecord = $_record;
    }
    
    /**
     * get client record
     * 
     * @return RecordInterface
     */
    public function getClientRecord()
    {
        return $this->_clientRecord;
    }
    
    /**
     * returns existing nodes info as array
     * 
     * @return array
     */
    public function toArray()
    {
        return array(
            'code'          => $this->getCode(),
            'message'       => $this->getMessage(),
            'clientRecord'  => $this->_clientRecordToArray(),
            'duplicates'    => $this->_dataToArray(),
        );
    }
    
    /**
     * convert client record to array
     * 
     * @return array
     */
    protected function _clientRecordToArray()
    {
        if (! $this->_clientRecord) {
            return array();
        }
        
        $this->_resolveClientRecordTags();
        $converter = ConvertFactory::factory($this->_clientRecord);
        $result = $converter->fromTine20Model($this->_clientRecord);
        
        return $result;
    }
    
    /**
     * resolve tag ids to tag record
     * 
     * @todo find a generic solution for this!
     */
    protected function _resolveClientRecordTags()
    {
        if (! $this->_clientRecord->has('tags') || empty($this->_clientRecord->tags)) {
            return;
        }
        
        $tags = new RecordSet('Tinebase_Model_Tag');
        foreach ($this->_clientRecord->tags as $tag) {
            if (is_string($tag)) {
                $tag = Tags::getInstance()->get($tag);
            }
            $tags->addRecord($tag);
        }
        $this->_clientRecord->tags = $tags;
    }
}
