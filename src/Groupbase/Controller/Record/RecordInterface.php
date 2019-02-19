<?php
namespace Fgsl\Groupware\Groupbase\Controller\Record;

use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Exception\Record\Validation;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Record\RecordSet;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * interface for record controller class for Tine 2.0 applications
 * 
 * @package     Groupbase
 * @subpackage  Controller
 */
interface RecordInterface
{
    /**
     * get by id
     *
     * @param string $_id
     * @return RecordInterface
     * @throws  AccessDenied
     */
    public function get($_id);
    
    /**
     * Returns a set of leads identified by their id's
     * 
     * @param   array array of record identifiers
     * @return  RecordSet of $this->_modelName
     */
    public function getMultiple($_ids);
    
    /**
     * Gets all entries
     *
     * @param string $_orderBy Order result by
     * @param string $_orderDirection Order direction - allowed are ASC and DESC
     * @throws InvalidArgument
     * @return RecordSet
     */
    public function getAll($_orderBy = 'id', $_orderDirection = 'ASC');
    
    /*************** add / update / delete lead *****************/    

    /**
     * add one record
     *
     * @param   RecordInterface $_record
     * @return  RecordInterface
     * @throws  AccessDenied
     * @throws  Validation
     */
    public function create(RecordInterface $_record);
    
    /**
     * update one record
     *
     * @param   RecordInterface $_record
     * @return  RecordInterface
     * @throws  AccessDenied
     * @throws  Validation
     */
    public function update(RecordInterface $_record);
    
    /**
     * update multiple records
     * 
     * @param   FilterGroup $_filter
     * @param   array $_data
     * @return  array $this->_updateMultipleResult
     */
    public function updateMultiple($_filter, $_data);
    
    /**
     * Deletes a set of records.
     * 
     * If one of the records could not be deleted, no record is deleted
     * 
     * @param   array|RecordInterface|RecordSet $_ids array of record identifiers
     * @return  RecordSet
     */
    public function delete($_ids);

    /**
     * checks if a records with identifiers $_ids exists, returns array of identifiers found
     *
     * @param array $_ids
     * @param bool $_getDeleted
     * @return array
     */
    public function has(array $_ids, $_getDeleted = false);

    /**
     * returns the model name
     *
     * @return string
     *
    public function getDefaultModel();*/
}
