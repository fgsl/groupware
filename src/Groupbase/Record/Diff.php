<?php
namespace Fgsl\Groupware\Groupbase\Record;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class Diff
 * 
 * @package     Tinebase
 * @subpackage  Record
 *
 * @property string id
 * @property string model
 * @property array  diff
 * @property array  oldData
 */
class Diff extends AbstractRecord 
{
    /**
     * identifier field name
     *
     * @var string
     */
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * record validators
     *
     * @var array
     */
    protected $_validators = array(
        'id'                => array('allowEmpty' => TRUE),
        'model'             => array('allowEmpty' => TRUE),
        'diff'              => array('allowEmpty' => TRUE), // array of mismatching fields containing new data
        'oldData'           => array('allowEmpty' => TRUE),
    );
    
    /**
     * returns array with record related properties 
     *
     * @param boolean $_recursive
     * @return array
     */
    public function toArray($_recursive = TRUE)
    {
        $recordArray = parent::toArray($_recursive);
        if ($_recursive && isset($recordArray['diff'])) {
            foreach ($recordArray['diff'] as $property => $value) {
                if ($this->_hasToArray($value)) {
                    $recordArray['diff'][$property] = $value->toArray();
                }
            }
        }
        if ($_recursive && isset($recordArray['oldData'])) {
            foreach ($recordArray['oldData'] as $property => $value) {
                if ($this->_hasToArray($value)) {
                    $recordArray['oldData'][$property] = $value->toArray();
                }
            }
        }
        
        return $recordArray;
    }
    
    /**
     * is equal = empty diff
     * 
     * @param array $toOmit
     * @return boolean
     */
    public function isEmpty($toOmit = array())
    {
        if (count($toOmit) === 0) {
            if (! is_array($this->diff) || count($this->diff) === 0) {
                return (! is_array($this->oldData) || count($this->oldData) === 0);
            } else {
                return false;
            }
        }

        $diff = array_diff(array_keys($this->diff), $toOmit);
        
        return (count($diff) === 0 ? count(array_diff(array_keys($this->oldData), $toOmit)) === 0 : false);
    }

    /**
     * only empty values have been replaced
     *
     * @return boolean
     */
    public function onlyEmptyValuesInOldData()
    {
        $nonEmptyValues = array_filter($this->oldData, function($v) {
            return ! empty($v);
        });
        return count($nonEmptyValues) === 0;
    }
}
