<?php
namespace Fgsl\Groupware\Groupbase\Record;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ConfigConst as MCC;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ConfigConst;
use Fgsl\Groupware\Setup\Backend\BackendFactory;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Application\Application;

/**
 * Tinebase_Record_DoctrineMappingDriver
 *
 * @package     Tinebase
 * @subpackage  Record
 */
class DoctrineMappingDriver extends ConfigConst
    implements \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver
{
    /**
     * @var array modelConfigType => Doctrine2Type
     */
    protected static $_typeMap = array(
        MCC::TYPE_STRING                => 'string',
        MCC::TYPE_STRING_AUTOCOMPLETE   => 'string',
        MCC::TYPE_TEXT                  => 'text',
        MCC::TYPE_FULLTEXT              => 'text',
        MCC::TYPE_DATETIME              => 'datetime',
        MCC::TYPE_DATE                  => 'datetime',
        MCC::TYPE_TIME                  => 'time',
        MCC::TYPE_INTEGER               => 'integer',
        MCC::TYPE_BIGINT                => 'bigint',
        MCC::TYPE_NUMBERABLE_INT        => 'integer',
        MCC::TYPE_NUMBERABLE_STRING     => 'string',
        MCC::TYPE_FLOAT                 => 'float',
        MCC::TYPE_JSON                  => 'text',
        MCC::TYPE_CONTAINER             => 'string',
        MCC::TYPE_RECORD                => 'string',
        MCC::TYPE_KEY_FIELD             => 'string',
        MCC::TYPE_USER                  => 'string',
        MCC::TYPE_BOOLEAN               => 'boolean',
        MCC::TYPE_MONEY                 => 'float',
        // TODO replace that with a single type 'datetime_separated'?
//        'datetime_separated' => 'date',
        'datetime_separated_date' => 'date',
        // not used yet:
        'datetime_separated_time' => 'time',
        'datetime_separated_tz' => 'string',
    );

    /**
     * Loads the metadata for the specified class into the provided container.
     *
     * @param string        $className
     * @param \Doctrine\ORM\Mapping\ClassMetadata $metadata
     * @throws MappingException
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        /** @var Tinebase_Record_Interface $className */
        /** @var ModelConfiguration $modelConfig */
        if (null === ($modelConfig = $className::getConfiguration())) {
        //if (! $this->isTransient($className)) {
            throw new MappingException('Class ' . $className . 'has no appropriate ModelConfiguration');
        }

        if (empty($table = $modelConfig->getTable())) {
            $table = ['name' => $modelConfig->getTableName()];
        }
        if (! isset($table['name'])) {
            throw new MappingException('Table name missing');
        }
        $table['name'] = SQL_TABLE_PREFIX . $table['name'];

        // mysql supports full text for InnoDB as of 5.6.4 for everybody else: remove full text index
        if ( ! BackendFactory::factory()->supports('mysql >= 5.6.4 | mariadb >= 10.0.5') ) {
            $this->_removeFullTextIndex($table);
        }

        $metadata->setPrimaryTable($table);

        $this->_mapAssociations($modelConfig, $metadata);
        $this->_mapFields($modelConfig, $metadata);
    }

    /**
     * @param ModelConfiguration $modelConfig
     * @param ClassMetadata $metadata
     */
    protected function _mapAssociations(ModelConfiguration $modelConfig, ClassMetadata $metadata)
    {
        foreach ($modelConfig->getAssociations() as $type => $associations) {
            foreach ($associations as $name => $association) {
                switch ($type) {
                    case ClassMetadataInfo::ONE_TO_ONE:
                        $metadata->mapOneToOne($association);
                        break;
                    case ClassMetadataInfo::MANY_TO_ONE:
                        $metadata->mapManyToOne($association);
                        break;
                    case ClassMetadataInfo::ONE_TO_MANY:
                        $metadata->mapOneToMany($association);
                        break;
                    case ClassMetadataInfo::MANY_TO_MANY:
                        $metadata->mapManyToMany($association);
                        break;
                }
            }
        }
    }

    /**
     * @param ModelConfiguration $modelConfig
     * @param ClassMetadata $metadata
     */
    protected function _mapFields(ModelConfiguration $modelConfig, ClassMetadata $metadata)
    {
        $virtualFields = array_keys($modelConfig->getVirtualFields());
        foreach ($modelConfig->getFields() + $modelConfig->getDbColumns() as $fieldName => $config) {
            if (in_array($fieldName, $virtualFields, true)) {
                continue;
            }

            self::mapTypes($config);

            if (! $config['doctrineIgnore']) {
                try {

                    $metadata->mapField($config);
                } catch (\Doctrine\ORM\Mapping\MappingException $dome) {
                    // TODO ignore or fix exceptions like
                    //  "Property "id" in "Timetracker_Model_Timeaccount" was already declared,
                    //   but it must be declared only once"
                    if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                        . ' ' . $dome->getMessage());

                    if (!isset($config['columnName'])) {
                        $config['columnName'] = $config['fieldName'];
                    }
                    $metadata->addInheritedFieldMapping($config);
                }
            }
        }
    }

    /**
     * @param $table
     */
    protected function _removeFullTextIndex(&$table)
    {
        if (! isset($table['indexes'])) {
            return;
        }

        $toDelete = array();
        foreach ($table['indexes'] as $key => $index) {
            if (isset($index['flags']) && array_search('fulltext', $index['flags']) !== false) {
                $toDelete[] = $key;
            }
        }

        foreach ($toDelete as $key) {
            unset($table['indexes'][$key]);
        }
    }

    /**
     * map modelconfig type to doctrine type
     *
     * @param $config
     */
    public static function mapTypes(&$config)
    {
        // TODO associated foreign keys should be ignored by default
        $defaultDoctrineIgnore = isset($config['doctrineIgnore']) ? $config['doctrineIgnore'] : false;

        $config['doctrineIgnore'] = true;
        if (isset(self::$_typeMap[$config[self::TYPE]])) {
            if ($config[self::TYPE] === self::TYPE_CONTAINER) {
                $config[self::LENGTH] = 40;
            }
            $config[self::TYPE] = self::$_typeMap[$config[self::TYPE]];
            $config['doctrineIgnore'] = $defaultDoctrineIgnore;
            if (isset($config[self::UNSIGNED])) {
                if (!isset($config[self::OPTIONS])) {
                    $config[self::OPTIONS] = [];
                }
                $config[self::OPTIONS][self::UNSIGNED] = $config[self::UNSIGNED];
            }
        }
    }

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array The names of all mapped classes known to this driver.
     */
    public function getAllClassNames()
    {
        $result = [];

        /** @var Tinebase_Record_Interface $model */
        foreach (Application::getInstance()->getModelsOfAllApplications() as $model) {
            if ($this->isTransient($model)) {
                $result[] = $model;
            }
        }

        return $result;
    }

    /**
     * Returns whether the class with the specified name should have its metadata loaded.
     * This is only the case if it is either mapped as an Entity or a MappedSuperclass.
     *
     * @param string $className
     *
     * @return boolean
     */
    public function isTransient($className)
    {
        $modelConfig = $className::getConfiguration();

        return $modelConfig && is_int($modelConfig->getVersion());
    }
}
