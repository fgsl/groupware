<?php
namespace Fgsl\Groupware\Groupbase\Convert;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Sabre\DAV\Exception\NotImplemented;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\User;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Pagination;
use Fgsl\Groupware\Groupbase\Model\FullUser;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * convert functions for records from/to json (array) format
 *
 * @package     Tinebase
 * @subpackage  Convert
 */
class Json implements ConvertInterface
{
    /**
     * @var bool
     *
     * if this is true, type = records fields are always resolved recursively
     */
    protected $_recursiveResolve = false;

    /**
     * control variable for recursive resolving
     *
     * @var array
     */
    protected $_recursiveResolvingProtection = [];

    /**
     * converts external format to RecordInterface
     *
     * @param  mixed $_blob the input data to parse
     * @param  RecordInterface $_record update existing record
     * @return RecordInterface
     * @throws NotImplemented
     */
    public function toGroupwareModel($_blob, RecordInterface $_record = NULL)
    {
        throw new NotImplemented('From json to record is not implemented yet');
    }
    
    /**
     * converts RecordInterface to external format
     * 
     * @param  RecordInterface $_record
     * @return mixed
     */
    public function fromGroupwareModel(RecordInterface $_record)
    {
        if (! $_record) {
            return array();
        }
        
        // for resolving we'll use recordset
        /** @var RecordInterface $recordClassName */
        $recordClassName = get_class($_record);
        $records = new RecordSet($recordClassName, array($_record));
        $modelConfiguration = $recordClassName::getConfiguration();

        return $this->_fromTine20RecordSet($records, $modelConfiguration, false);
    }

    /**
     * converts RecordSet to external format
     *
     * @param RecordSet|RecordInterface  $_records
     * @param FilterGroup $_filter
     * @param Pagination $_pagination
     *
     * @return mixed
     */
    public function fromTine20RecordSet(RecordSet $_records = NULL, /** @noinspection PhpUnusedParameterInspection */
                                        $_filter = NULL, /** @noinspection PhpUnusedParameterInspection */ $_pagination = NULL)
    {
        if (! $_records || count($_records) == 0) {
            return array();
        }

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Processing ' . count($_records) . ' records ...');

        // find out if there is a modelConfiguration
        /** @var RecordInterface $ownRecordClass */
        $ownRecordClass = $_records->getRecordClassName();
        $config = $ownRecordClass::getConfiguration();

        return $this->_fromTine20RecordSet($_records, $config, true);
    }

    /**
     * @param RecordSet $_records
     * @param $config
     * @param bool $multiple
     * @return array
     */
    protected function _fromTine20RecordSet(RecordSet $_records, $config, $multiple)
    {
        $this->_resolveBeforeToArray($_records, $config, $multiple);

        $_records->setTimezone(Core::getUserTimezone());
        $_records->setConvertDates(true);

        if (false === $multiple) {
            $result = $_records->getFirstRecord()->toArray();
        } else {
            $result = $_records->toArray();
        }

        // resolve all virtual fields after converting to array, so we can add these properties "virtually"
        $result = $this->_resolveAfterToArray($result, $config, $multiple);

        return $result;
    }

    /**
     * temporary hacks to have json converter logic available outside (e.g. modelconfig/exports)
     *
     * @param RecordSet $records
     */
    public function resolveRecords(RecordSet $records, $multiple = true)
    {
        $recordClassName = $records->getRecordClassName();
        $modelConfiguration = $recordClassName::getConfiguration();
        $this->_resolveBeforeToArray($records, $modelConfiguration, $multiple);
        $this->_resolveAfterToArray($records, $modelConfiguration, $multiple);

        return $records;
    }

    /**
     * resolves single record fields (ModelConfiguration._recordsFields)
     * 
     * @param RecordSet $_records the records
     * @param ModelConfiguration $modelConfig
     */
    protected function _resolveSingleRecordFields(RecordSet $_records, $modelConfig = NULL)
    {
        if (! $modelConfig) {
            return;
        }
        
        $resolveFields = $modelConfig->recordFields;
        
        if ($resolveFields && is_array($resolveFields)) {
            // don't search twice if the same recordClass gets resolved on multiple fields
            $resolveRecords = array();
            foreach ($resolveFields as $fieldKey => $fieldConfig) {
                $resolveRecords[$fieldConfig['config']['recordClassName']][] = $fieldKey;
            }
            
            foreach ($resolveRecords as $foreignRecordClassName => $fields) {
                $foreignIds = array();
                $foreignRecordsArray = array();
                $fields = (array) $fields;
                foreach ($fields as $field) {
                    $idsForField = $_records->{$field};
                    foreach ($idsForField as $key => $value) {
                        if ($value instanceof RecordInterface) {
                            $foreignRecordsArray[$value->getId()] = $value;
                        } else {
                            if ($value && is_scalar($value) && ! isset($foreignRecordsArray[$value])) {
                                $foreignIds[$value] = $value;
                            }
                        }
                    }
                }
                
                if (empty($foreignIds) && empty($foreignRecordsArray)) {
                    continue;
                }
                
                $cfg = $resolveFields[$fields[0]];

                if ($cfg['type'] === 'user') {
                    $foreignRecords = User::getInstance()->getMultiple($foreignIds);
                } else if ($cfg['type'] === 'container') {
                    // TODO: resolve recursive records of records better in controller
                    // TODO: resolve containers
                    if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . ' No handling for container foreign records implemented');

                    //$foreignRecords = new RecordSet('Tinebase_Model_Container');
                    // $foreignRecords->addRecord(Tinebase_Container::getInstance()->get(XXX));
                    continue;
                } else {
                    try {
                        $foreignRecords = $this->_getForeignRecords($foreignIds, $foreignRecordClassName);
                    } catch (AccessDenied $tead) {
                        if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                            . ' No right to access application of record ' . $foreignRecordClassName);
                        continue;
                    }
                }

                foreach ($foreignRecordsArray as $id => $rec) {
                    if ($foreignRecords->getById($id) === false) {
                        if ($cfg['type'] === 'user' && get_class($rec) === ModelUser::class) {
                            $rec = new FullUser($rec->toArray(), true);
                        }
                        $foreignRecords->addRecord($rec);
                    }
                }
                
                if ($foreignRecords->count() === 0) {
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' No matching foreign records found (' . $foreignRecordClassName . ')');
                    continue;
                }
                $foreignRecords->setTimezone(Core::getUserTimezone());
                $foreignRecords->setConvertDates(true);
                Tinebase_Frontend_Json_Abstract::resolveContainersAndTags($foreignRecords, $modelConfig);
                $fr = $foreignRecords->getFirstRecord();
                if ($fr && $fr->has('notes')) {
                    Tinebase_Notes::getInstance()->getMultipleNotesOfRecords($foreignRecords);
                }

                $this->_mapForeignRecords($_records, $fields, $foreignRecords);
            }
        }
    }

    /**
     * @param $foreignIds
     * @param $foreignRecordClassName
     * @return RecordSet
     */
    protected function _getForeignRecords($foreignIds, $foreignRecordClassName)
    {
        $controller = Core::getApplicationInstance($foreignRecordClassName);
        $foreignRecords = $controller->getMultiple($foreignIds);
        return $foreignRecords;
    }

    /**
     * @param $records
     * @param $fields
     * @param RecordSet $foreignRecords
     */
    protected function _mapForeignRecords($records, $fields, $foreignRecords)
    {
        foreach ($records as $record) {
            foreach ($fields as $field) {
                $foreignId = $record->{$field};
                if (is_scalar($foreignId)) {
                    $idx = $foreignRecords->getIndexById($foreignId);
                    if (isset($idx) && $idx !== FALSE) {
                        $record->{$field} = $foreignRecords[$idx];
                    } else {
                        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' No matching foreign record found for id: ' . $foreignId);
                    }
                }
            }
        }
    }
    
    /**
     * resolves multiple records (fallback)
     * 
     * @deprecated use ModelConfiguration to configure your models, so this won't be used anymore 
     * @param RecordSet $records the records
     * @param array $resolveFields
     */
    public static function resolveMultipleIdFields($records, $resolveFields = NULL)
    {
        if (! $records instanceof RecordSet || !$records->count()) {
            return;
        }

        /** @var RecordInterface $ownRecordClass */
        $ownRecordClass = $records->getRecordClassName();
        if ($resolveFields === NULL) {
            $resolveFields = $ownRecordClass::getResolveForeignIdFields();
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Resolving ' . $ownRecordClass . ' fields: ' . print_r($resolveFields, TRUE));
        
        foreach ((array) $resolveFields as $foreignRecordClassName => $fields) {
            if ($foreignRecordClassName === 'recursive') {
                foreach ($fields as $field => $model) {
                    foreach ($records->$field as $subRecords) {
                        self::resolveMultipleIdFields($subRecords);
                    }
                }
            } else {
                self::_resolveForeignIdFields($records, $foreignRecordClassName, (array) $fields);
            }
        }
    }
    
    /**
     * resolve foreign fields for records like user ids to users, etc.
     * 
     * @param RecordSet $records
     * @param string $foreignRecordClassName
     * @param array $fields
     */
    protected static function _resolveForeignIdFields($records, $foreignRecordClassName, $fields)
    {
        $options = (isset($fields['options']) || array_key_exists('options', $fields)) ? $fields['options'] : array();
        $fields = (isset($fields['fields']) || array_key_exists('fields', $fields)) ? $fields['fields'] : $fields;
        
        $foreignIds = array();
        foreach ($fields as $field) {
            $value = $records->{$field};
            if (is_array($value)) {
                if (isset($value['id'])) {
                    $value = [$value['id']];
                } else {
                    $value = array_filter($value, function ($val) { return is_scalar($val); });
                }
            } elseif (is_scalar($value)) {
                $value = [$value];
            } elseif ($value instanceof RecordInterface) {
                $value = [$value->getId()];
            } else {
                $value = [];
            }
            $foreignIds = array_merge($foreignIds, $value);
        }
        $foreignIds = array_unique($foreignIds);
        
        try {
            $controller = Core::getApplicationInstance($foreignRecordClassName);
        } catch (AccessDenied $tead) {
            if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                . ' No right to access application of record ' . $foreignRecordClassName);
            return;
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Fetching ' . $foreignRecordClassName . ' by id: ' . print_r($foreignIds, TRUE));
        
        if ((isset($options['ignoreAcl']) || array_key_exists('ignoreAcl', $options)) && $options['ignoreAcl']) {
            // @todo make sure that second param of getMultiple() is $ignoreAcl
            $foreignRecords = $controller->getMultiple($foreignIds, TRUE);
        } else {
            $foreignRecords = $controller->getMultiple($foreignIds);
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Foreign records found: ' . print_r($foreignRecords->toArray(), TRUE));
        
        if (count($foreignRecords) === 0) {
            return;
        }
        
        foreach ($records as $record) {
            foreach ($fields as $field) {
                if (is_scalar($record->{$field})) {
                    $idx = $foreignRecords->getIndexById($record->{$field});
                    if (isset($idx) && $idx !== FALSE) {
                        $record->{$field} = $foreignRecords[$idx];
                    } else {
                        switch ($foreignRecordClassName) {
                            case 'Fgsl\Groupware\Groupbase\Model\User':
                            case 'Fgsl\Groupware\Groupbase\Model\FullUser':
                                $record->{$field} = Tinebase_User::getInstance()->getNonExistentUser();
                                break;
                            default:
                                // skip
                        }
                    }
                }
            }
        }
    }

    /**
     * @param RecordSet $_records
     * @param ModelConfiguration|null $modelConfiguration
     * @param bool $multiple
     */
    protected function _resolveRecursive(
        RecordSet $_records,
        $modelConfiguration = NULL,
        $multiple = false
    ) {
        if (! $modelConfiguration || (! $_records->count())) {
            return;
        }

        if ($this->_recursiveResolve) {
            $resolveFields = [];
            foreach ($modelConfiguration->getFields() as $name => $field) {
                if ($field['type'] === 'records') {
                    $resolveFields[] = $name;
                }
            }
        } else if (! ($resolveFields = $modelConfiguration->recursiveResolvingFields)) {
            return;
        }

        $first = true;
        $recursions = [];
        foreach ($resolveFields as $property) {
            foreach ($_records->{$property} as $idx => $recordOrRecords) {
                // cope with single records
                if ($recordOrRecords instanceof RecordInterface) {
                    $records = new RecordSet(get_class($recordOrRecords));
                    $records->addRecord($recordOrRecords);
                } else {
                    $records = $recordOrRecords;
                }

                // recursion protection
                $id = $_records->getByIndex($idx)->getId();
                if (isset($recursions[$id]) || ($first && isset($this->_recursiveResolvingProtection[$id]))) {
                    $recursions[$id] = true;
                    continue;
                }
                $this->_recursiveResolvingProtection[$id] = true;

                if ($records instanceof RecordSet && $records->count() > 0) {
                    /** @var RecordInterface $model */
                    $model = $records->getRecordClassName();
                    $mc = $model::getConfiguration();
                    Tinebase_Frontend_Json_Abstract::resolveContainersAndTags($records, $mc);

                    self::resolveAttachmentImage($records);

                    self::resolveMultipleIdFields($records);

                    // use modern record resolving, if the model was configured using ModelConfiguration
                    // at first, resolve all single record fields
                    if ($mc) {
                        $this->_resolveSingleRecordFields($records, $mc);

                        // resolve all multiple records fields
                        $this->_resolveMultipleRecordFields($records, $mc, $multiple);

                        $this->_resolveRecursive($records, $mc, $multiple);
                    }
                }
            }
            $first = false;
        }
    }

    /**
     * @param $value
     */
    public function setRecursiveResolve($value)
    {
        $this->_recursiveResolve = $value;
    }
    
    /**
     * resolve multiple record fields (ModelConfiguration._recordsFields)
     * 
     * @param RecordSet $_records
     * @param ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     * @throws Tinebase_Exception_UnexpectedValue
     */
    protected function _resolveMultipleRecordFields(RecordSet $_records, $modelConfiguration = NULL, $multiple = false)
    {
        if (! $modelConfiguration || (! $_records->count())) {
            return;
        }
        
        if (! ($resolveFields = $modelConfiguration->recordsFields)) {
            return;
        }
        
        $ownIds = $_records->{$modelConfiguration->idProperty};
        
        // iterate fields to resolve
        foreach ($resolveFields as $fieldKey => $c) {
            $config = $c['config'];

            if (isset($c['noResolve']) && $c['noResolve']) {
                continue;
            }

            // resolve records, if omitOnSearch is definitively set to FALSE (by default they won't be resolved on search)
            if ($multiple && !(isset($config['omitOnSearch']) && $config['omitOnSearch'] === FALSE)) {
                continue;
            }

            if (! isset($config['controllerClassName'])) {
                throw new Tinebase_Exception_UnexpectedValue('Controller class name needed');
            }

            // fetch the fields by the refIfField
            if (! class_exists($config['controllerClassName'])) {
                continue;
            }
            /** @var Tinebase_Controller_Record_Interface|Tinebase_Controller_SearchInterface $controller */
            /** @noinspection PhpUndefinedMethodInspection */
            $controller = $config['controllerClassName']::getInstance();
            $filterName = $config['filterClassName'];
            
            $filterArray = array(
                array('field' => $config['refIdField'], 'operator' => 'in', 'value' => $ownIds)
            );
            
            // addFilters can be added and must be added if the same model resides in more than one records fields
            if (isset($config['addFilters']) && is_array($config['addFilters'])) {
                $filterArray = $config['addFilters'];
            }

            $filter = FilterGroup::getFilterForModel($filterName, $filterArray,
                FilterGroup::CONDITION_AND, isset($config[MCC::FILTER_OPTIONS]) ?
                    $config[MCC::FILTER_OPTIONS] : []);

            $paging = NULL;
            if (isset($config['paging']) && is_array($config['paging'])) {
                $paging = new Pagination($config['paging']);
            }
            
            $foreignRecords = $controller->search($filter, $paging);
            /** @var RecordInterface $foreignRecordClass */
            $foreignRecordClass = $foreignRecords->getRecordClassName();
            $foreignRecordModelConfiguration = $foreignRecordClass::getConfiguration();
            
            $foreignRecords->setTimezone(Core::getUserTimezone());
            $foreignRecords->setConvertDates(true);
            
            $fr = $foreignRecords->getFirstRecord();

            // @todo: resolve alarms?
            // @todo: use parts parameter?
            if ($foreignRecordModelConfiguration->resolveRelated && $fr) {
                if ($fr->has('notes')) {
                    Tinebase_Notes::getInstance()->getMultipleNotesOfRecords($foreignRecords);
                }
                if ($fr->has('tags')) {
                    Tinebase_Tags::getInstance()->getMultipleTagsOfRecords($foreignRecords);
                }
                if ($fr->has('relations')) {
                    $relations = Tinebase_Relations::getInstance()->getMultipleRelations($foreignRecordClass, 'Sql', $foreignRecords->{$fr->getIdProperty()} );
                    $foreignRecords->setByIndices('relations', $relations);
                }
                if ($fr->has('customfields')) {
                    Tinebase_CustomField::getInstance()->resolveMultipleCustomfields($foreignRecords);
                }
                if ($fr->has('attachments') && Core::isFilesystemAvailable()) {
                    Tinebase_FileSystem_RecordAttachments::getInstance()->getMultipleAttachmentsOfRecords($foreignRecords);
                }
            }
            
            if ($foreignRecords->count() > 0) {
                /** @var RecordInterface $record */
                foreach ($_records as $record) {
                    $filtered = $foreignRecords->filter($config['refIdField'], $record->getId());
                    $record->{$fieldKey} = $filtered;
                }
                
            } else {
                $_records->{$fieldKey} = NULL;
            }
        }
        
    }

    /**
     * resolves virtual fields, if a function has been defined in the field definition
     *
     * @param array $resultSet
     * @param ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     * @return array
     */
    protected function _resolveVirtualFields($resultSet, $modelConfiguration = NULL, $multiple = false)
    {
        if (! $modelConfiguration || ! ($virtualFields = $modelConfiguration->virtualFields)) {
            return $resultSet;
        }
        
        if ($modelConfiguration->resolveVFGlobally === TRUE) {

            /** @var Tinebase_Controller_Record_Interface $controller */
            $controller = $modelConfiguration->getControllerInstance();
            
            if ($multiple) {
                return $controller->resolveMultipleVirtualFields($resultSet);
            }
            return $controller->resolveVirtualFields($resultSet);
        }
        
        foreach ($virtualFields as $field) {
            // resolve virtual relation record from relations property
            if (isset($field['type']) && $field['type'] == 'relation') {

                // tODO probably move this array nesting to the top of the function, check 'function' todo below though
                if ($multiple) {
                    $tmp = &$resultSet;
                } else {
                    $tmp = array(&$resultSet);
                }
                foreach($tmp as &$rS) {
                    $fc = $field['config'];
                    if (isset($rS['relations']) && (is_array($rS['relations'])
                            || $rS['relations'] instanceof RecordSet)) {
                        foreach ($rS['relations'] as $relation) {
                            if ($relation['type'] === $fc['type'] && $relation['related_model'] === $fc['appName'] .
                                    '_Model_' . $fc['modelName'] && isset($relation['related_record'])) {
                                $rS[$field['key']] = $relation['related_record'];
                            }
                        }
                    }
                }
            // resolve virtual field by function
                // TODO multiple is not passed along and not part of the if condition! this is bad I guess this is dead code?
            } else if ((isset($field['function']) || array_key_exists('function', $field))) {
                if (is_array($field['function'])) {
                    if (count($field['function']) > 1) { // static method call
                        $class  = $field['function'][0];
                        $method = $field['function'][1];
                        $resultSet = $class::$method($resultSet);

                    } else { // use key as classname and value as method name
                        $ks = array_keys($field['function']);
                        $class  = array_pop($ks);
                        $vs = array_values($field['function']);
                        $method = array_pop($vs);
                        $class = $class::getInstance();
                        
                        $resultSet = $class->$method($resultSet);
                        
                    }
                // if no array has been given, this should be a function name
                } else {
                    $resolveFunction = $field['function'];
                    $resultSet = $resolveFunction($resultSet);
                }
            }
        }
        
        return $resultSet;
    }
    
    /**
     * resolves child records before converting the record set to an array
     * 
     * @param RecordSet $records
     * @param ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     */
    protected function _resolveBeforeToArray($records, $modelConfiguration, $multiple = false)
    {
        Tinebase_Frontend_Json_Abstract::resolveContainersAndTags($records, $modelConfiguration);

        self::resolveAttachmentImage($records);

        if ($multiple) {
            if ($records->getFirstRecord()->has('attachments') && Core::isFilesystemAvailable()) {
                Tinebase_FileSystem_RecordAttachments::getInstance()->getMultipleAttachmentsOfRecords($records);
            }
        }
        
        self::resolveMultipleIdFields($records);
        
        // use modern record resolving, if the model was configured using ModelConfiguration
        // at first, resolve all single record fields
        if ($modelConfiguration) {
            $this->_resolveSingleRecordFields($records, $modelConfiguration);
        
            // resolve all multiple records fields
            $this->_resolveMultipleRecordFields($records, $modelConfiguration, $multiple);

            $this->_recursiveResolvingProtection = [];
            $this->_resolveRecursive($records, $modelConfiguration, $multiple);
        }
    }

    /**
     * adds image property with image url like this:
     *  'index.php?method=Tinebase.getImage&application=Tinebase&location=vfs&id=e4b7de34e229672c0d5e22be0912779441e6e051'
     *
     * @param $records
     */
    static public function resolveAttachmentImage($records)
    {
        // get all images from attachments and set 'image' properties

        // TODO find an additional condition to better detect the attachments that should be the record image(s)

        /** @var RecordInterface $record */
        foreach ($records as $record) {
            if ($record->has('image') && $record->has('attachments')) {
                if (! $record->attachments instanceof RecordSet) {
                    // re-fetch attachments
                    Tinebase_FileSystem_RecordAttachments::getInstance()->getRecordAttachments($record);
                }
                // order by creation time to show the latest attachment as record image
                $record->attachments->sort('creation_time', 'DESC');
                foreach ($record->attachments as $attachment) {
                    if (in_array($attachment->contenttype, Tinebase_ImageHelper::getSupportedImageMimeTypes())) {
                        $record->image = Tinebase_Model_Image::getImageUrl('Tinebase', $attachment->getId(), 'vfs');
                        break;
                    }
                }
            }
        }
    }

    /**
     * resolves child records after converting the record set to an array
     *
     * @param array $result
     * @param ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     *
     * @return array
     */
    protected function _resolveAfterToArray($result, $modelConfiguration, $multiple = false)
    {
        $result = $this->_resolveVirtualFields($result, $modelConfiguration, $multiple);
        $result = $this->_convertRightToAccountGrants($result, $modelConfiguration, $multiple);
        return $result;
    }

    /**
     * adds account_grants if configured in model
     *
     * @param array $resultSet
     * @param ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     * @return array
     */
    protected function _convertRightToAccountGrants($resultSet, $modelConfiguration = NULL, $multiple = false)
    {
        if (! $modelConfiguration || ! $modelConfiguration->convertRightToGrants) {
            return $resultSet;
        }

        $userGrants = Core::getUser()->hasRight(
            $modelConfiguration->appName,
            $modelConfiguration->convertRightToGrants['right']
        ) ? $modelConfiguration->convertRightToGrants['providesGrants'] : array();

        // TODO can't we do this in a more elegant way?? maybe use array_walk?
        $tmp = ($multiple) ? $resultSet : array($resultSet);
        foreach ($tmp as &$record) {
            if ($record instanceof RecordInterface || $record instanceof RecordSet) continue;
            $record['account_grants'] = $userGrants;
        }

        return ($multiple) ? $tmp : $tmp[0];
    }
}
