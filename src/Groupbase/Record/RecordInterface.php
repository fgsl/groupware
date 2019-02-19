<?php
namespace Fgsl\Groupware\Groupbase\Record;

use Fgsl\Groupware\Groupbase\Exception\Record\DefinitionFailure;
use Fgsl\Groupware\Groupbase\Exception\Record\Validation;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Fgsl\Groupware\Groupbase\DateTime;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * RecordInterface
 * 
 * This a the abstract interface of an record.
 * A record is e.g. a single address or or single event.
 * The idea behind is that we can have metaoperation over differnt apps by 
 * having a common interface.
 * A record is identified by a identifier. As we are a Webapp and want to 
 * deal with the objects in the browser, identifier should be a string!
 * 
 * @package     Groupbase
 * @subpackage  Record
 *
 * @property array $customfields
 *
 * @property string $container_id
 *
 * TODO are these all strings?!? guess not
 * @property string             $created_by
 * @property Tinebase_DateTime  $creation_time
 * @property string             $last_modified_by
 * @property Tinebase_DateTime  $last_modified_time
 * @property string $is_deleted
 * @property Tinebase_DateTime  $deleted_time
 * @property string $deleted_by
 * @property int $seq
 *
 * @property array|RecordSet                        $relations
 * @property array|RecordSet                        $notes
 * @property array|RecordSet|Tinebase_Model_Tag     $tags
 * @property RecordSet                              $alarms
 * @property array|RecordSet                        $attachments
 */
interface RecordInterface extends \ArrayAccess, \IteratorAggregate 
{
    /**
     * Default constructor
     * Constructs an object and sets its record related properties.
     *
     * @param mixed $_data
     * @param boolean $_bypassFilters Bypass filters at object creation with data
     * this is usefull when datas are for sure valid, e.g. after database query
     * @param boolean $_convertDates array with Tinebase_DateTime constructor parameters part and locale
     *
     * @throws DefinitionFailure
     */
    public function __construct($_data = NULL, $_bypassFilters = false, $_convertDates = true);

    /**
     * returns the configuration object
     *
     * @return ModelConfiguration|NULL
     */
    public static function getConfiguration();

    /**
     * resetConfiguration
     */
    public static function resetConfiguration();

    /**
     * sets identifier of record
     * 
     * @param string $_id
     */
    public function setId($_id);
    
    /**
     * gets identifier of record
     * 
     * @return string identifier
     */
    public function getId();
    
    /**
     * returns id property of this model
     *
     * @return string
     */
    public function getIdProperty();
    
    /**
     * gets application the records belongs to
     * 
     * @return string application
     */
    public function getApplication();
    
    /**
     * sets record related properties
     * 
     * @param string $_name of property
     * @param mixed $_value of property
     */
    public function __set($_name, $_value);
    
    /**
     * unsets record related properties
     * 
     * @param string $_name of property
     */
    public function __unset($_name);
    
    /**
     * gets record related properties
     * 
     * @param string $_name of property
     * @return mixed value of property
     */
    public function __get($_name);
    
    /**
     * sets the record related properties from user generated input.
     * 
     * Input-filtering and validation by Zend_Filter_Input can enabled and disabled
     *
     * @param array $_data the new data to set
     * @throws Validation when content contains invalid or missing data
     */
    public function setFromArray(array &$_data);
    
    /**
     * Sets timezone of $this->_datetimeFields
     * 
     * @see DateTime::setTimezone()
     * @param string $_timezone
     * @throws Validation
     * @return void
     */
    public function setTimezone($_timezone);
    
    /**
     * validate the the internal data
     *
     * @param $_throwExceptionOnInvalidData
     * @return bool
     * @throws Validation
     */
    public function isValid($_throwExceptionOnInvalidData = false);
    
    /**
     * returns array of fields with validation errors 
     *
     * @return array
     */
    public function getValidationErrors();
    
    /**
     * returns array with record related properties 
     *
     * @param boolean $_recursive
     * @return array
     */
    public function toArray($_recursive = TRUE);

    /**
     * @return array
     */
    public function getData();
    
    /**
     * returns an array with differences to the given record
     * 
     * @param  RecordInterface $_record record for comparism
     * @param array $omitFields omit fields (for example modlog fields)
     * @return Diff with differences field => different value
     */
    public function diff($_record, $omitFields = array());
    
    /**
     * check if two records are equal
     * 
     * @param  RecordInterface $_record record for comparism
     * @param  array                     $_toOmit fields to omit
     * @return bool
     */
    public function isEqual($_record, array $_toOmit = array());
     
    /**
     * translate this records' fields
     *
     */
    public function translate();
    
    /**
     * check if the model has a specific field (container_id for example)
     *
     * @param string $_field
     * @return boolean
     */
    public function has($_field);

    public function runConvertToRecord();

    public function runConvertToData();

    /**
     * returns read only fields
     *
     * @return array
     */
    public function getReadOnlyFields();

    /**
     * wrapper for setFromJason which expects datetimes in array to be in
     * users timezone and converts them to UTC
     *
     * @todo move this to a generic __call interceptor setFrom<API>InUsersTimezone
     *
     * @param  string $_data json encoded data
     * @throws Validation when content contains invalid or missing data
     */
    public function setFromJsonInUsersTimezone(&$_data);

    /**
     * returns the title of the record
     *
     * @return string
     */
    public function getTitle();

    /**
     * returns the foreignId fields (used in Tinebase_Convert_Json)
     *
     * @return array
     */
    public static function getResolveForeignIdFields();

    /**
     * convert this to string
     *
     * @return string
     */
    public function __toString();

    /**
     * undoes the change stored in the diff
     *
     * @param Diff $diff
     * @return void
     */
    public function undo(Diff $diff);

    /**
     * applies the change stored in the diff
     *
     * @param Diff $diff
     * @return void
     */
    public function applyDiff(Diff $diff);

    /**
     * returns true if this record should be replicated
     *
     * @return boolean
     */
    public function isReplicable();

    /**
     * @param RecordInterface|null $_parent
     * @param RecordInterface|null $_child
     * @return string
     */
    public function getPathPart(RecordInterface $_parent = null, RecordInterface $_child = null);

    /**
     * @param RecordInterface|null $_parent
     * @param RecordInterface|null $_child
     * @return string
     */
    public function getShadowPathPart(RecordInterface $_parent = null, RecordInterface $_child = null);

    /**
     * @return array
     */
    public function getPathNeighbours();

    /**
     * @return string
     */
    public function getTypeForPathPart();

    /**
     * extended properties getter
     *
     * @param string $_property
     * @return &array
     */
    public function &xprops($_property = 'xprops');

    /**
     * extended json data properties getter
     *
     * @param string $_property
     * @return &array
     */
    public function &jsonData($_property);

    /**
     * get fields
     *
     * @return array
     */
    public function getFields();

    /**
     * returns modlog omit fields
     *
     * @return array
     */
    public function getModlogOmitFields();

    /**
     * @param RecordSet $_recordSet
     * @param RecordSetDiff $_recordSetDiff
     * @return bool
     */
    public static function applyRecordSetDiff(RecordSet $_recordSet, RecordSetDiff $_recordSetDiff);

    /**
     * @param RecordSet $_recordSetOne
     * @param RecordSet $_recordSetTwo
     * @return null|RecordSetDiff
     */
    public static function recordSetDiff(RecordSet $_recordSetOne, RecordSet $_recordSetTwo);

    /**
     * @param string $_property
     * @param mixed $_diffValue
     * @param mixed $_oldValue
     * @return null|boolean
     */
    public function resolveConcurrencyUpdate($_property, $_diffValue, $_oldValue);

    /**
     * returns the id of a record property
     *
     * @param string $_property
     * @return string|null
     */
    public function getIdFromProperty($_property);

    /**
     * @param array $_validators
     */
    public function setValidators(array $_validators);

    /**
     * @return bool
     */
    public static function generatesPaths();

    /**
     * @param boolean $_bool the new value
     * @return boolean the old value
     */
    public function setConvertDates($_bool);

    /**
     * @param array $data
     */
    public function hydrateFromBackend(array &$_data);
}