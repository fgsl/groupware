<?php
namespace Fgsl\Groupware\Groupbase\Backend\Sql;

use Fgsl\Groupware\Groupbase\Backend\BackendInterface;
use Zend\Db\Adapter\AdapterInterface;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Interface for Sql Backends
 * 
 * @package     Tinebase
 * @subpackage  Backend
 */
interface SqlInterface extends BackendInterface
{
    /**
     * get table prefix
     *
     * @return string
     */
    public function getTablePrefix();

    /**
     * get table name
     *
     * @return string
     */
    public function getTableName();

    /**
     * get db adapter
     *
     * @return AdapterInterface
     */
    public function getAdapter();

    /**
     * returns the db schema
     * 
     * @return array
     */
    public function getSchema();

    /**
     * sets modlog active flag
     *
     * @param $_bool
     * @return AbstractSql
     */
    public function setModlogActive($_bool);

    /**
     * checks if modlog is active or not
     *
     * @return bool
     */
    public function getModlogActive();

    /**
     * fetch a single property for all records defined in array of $ids
     *
     * @param array|string $ids
     * @param string $property
     * @return array (key = id, value = property value)
     */
    public function getPropertyByIds($ids, $property);

    /**
     * checks if a records with identifiers $_ids exists, returns array of identifiers found
     *
     * @param array $_ids
     * @param bool $_getDeleted
     * @return array
     *
     * TODO maybe move to abstract interface?
     */
    public function has(array $_ids, $_getDeleted = false);
}
