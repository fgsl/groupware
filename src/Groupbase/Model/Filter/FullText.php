<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Setup\Backend\BackendFactory;
use Fgsl\Groupware\Groupbase\Backend\Sql\Filter\FilterGroup as SqlFilterFilterGroup;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Cache\PerRequest;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Setup\Core as SetupCore;
use Zend\Db\Adapter\Adapter;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * FullText
 *
 * filters one filterstring in one property
 *
 * @package     Groupbase
 * @subpackage  Filter
 */
class FullText extends AbstractFilter
{
    /**
     * @var array list of allowed operators
     */
    protected $_operators = array(
        0 => 'contains',
        1 => 'notcontains',
        2 => 'equals',
        3 => 'not',
        4 => 'startswith',
        5 => 'endswith',
        6 => 'notin',
        7 => 'in',
        8 => 'wordstartswith'
    );

    /**
     * appends sql to given select statement
     *
     * @param  Select                $_select
     * @param  AbstractSql           $_backend
     * @throws InvalidArgument
     */
    public function appendFilterSql($_select, $_backend)
    {
        $db = $_select->getAdapter();

        $not = false;
        $in = false;
        if (false !== strpos($this->_operator, 'not')) {
            $not = true;
        }
        if ($this->_operator === 'in') {
            $in = true;
        }
        if ($this->_operator !== 'contains' && $this->_operator !== 'notcontains') {
            if (true === $not) {
                $this->_operator = 'notcontains';
            } else {
                $this->_operator = 'contains';
            }
        }
        // mysql supports full text for InnoDB as of 5.6.4
        // full text can't do a pure negative search...
        $useMysqlFullText = false === $not && BackendFactory::factory()->supports('mysql >= 5.6.4 | mariadb >= 10.0.5');

        $values = static::sanitizeValue($this->_value, $useMysqlFullText);

        if (count($values) === 0) {
            if (true === $not) {
                $_select->where('1 = 1');
            } else {
                $_select->where('1 = 0');
            }
            return;
        }

        if (false === $useMysqlFullText) {

            if (false === $not && SetupCore::isLogLevel(LogLevel::NOTICE)) SetupCore::getLogger()->notice(__METHOD__ . '::' . __LINE__ .
                ' full text search is only supported on mysql 5.6.4+ / mariadb 10.0.5+ ... do yourself a favor and migrate. This query now maybe very slow for larger amount of data!');

            $filterGroup = new FilterGroup(array(), $in ?
                FilterGroup::CONDITION_OR : FilterGroup::CONDITION_AND);

            foreach ($values as $value) {
                $filter = new Text($this->_field, $this->_operator, $value, is_array($this->_options) ? $this->_options : []);
                $filterGroup->addFilter($filter);
            }

            SqlFilterFilterGroup::appendFilters($_select, $filterGroup, $_backend);

        } else {
            $field = $this->_getQuotedFieldName($_backend);
            $searchTerm = '';

            foreach ($values as $value) {
                $searchTerm .= ($searchTerm !== '' ? ', ' : '') . ($in ? '' : '+') . $value . '*';
            }

            $_select->where('MATCH (' . $field . $db->quoteInto(') AGAINST (? IN BOOLEAN MODE)', $searchTerm));
        }
    }

    public static function sanitizeValue($_value, $_useMysqlFullText = true)
    {
        $values = array();

        foreach ((array)$_value as $value) {
            //replace full text meta characters
            //$value = str_replace(array('+', '-', '<', '>', '~', '*', '(', ')', '"'), ' ', $value);
            // replace any non letter, non digit, non underscore with blank
            $value = preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $value);
            // replace multiple spaces with just one
            $value = preg_replace('# +#u', ' ', trim($value));
            $values = array_merge($values, explode(' ', $value));
        }

        if (true === $_useMysqlFullText) {
            $ftConfig = static::getMySQLFullTextConfig();
            $values = array_filter($values, function($val) use ($ftConfig) {
                return mb_strlen($val) >= $ftConfig['tokenSize'] && !in_array(strtolower($val), $ftConfig['stopWords']);
            });
        } else {
            $values = array_filter($values, function($val) {
                return mb_strlen($val) >= 3;
            });
        }

        return $values;
    }

    /**
     * this looks up the default mysql innodb full text stop word list and returns the contents as array
     *
     * @return array
     */
    protected static function getMySQLFullTextConfig()
    {
        $cacheId = 'mysqlFullTextConfig';

        try {
            return PerRequest::getInstance()->load(__CLASS__, __METHOD__, $cacheId, PerRequest::VISIBILITY_SHARED);
        } catch (NotFound $tenf) {}

        $db = Core::getDb();

        $result = [];
        $result['stopWords'] = $db->query('SELECT `value` FROM INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD')->fetchAll(Adapter::FETCH_COLUMN, 0);
        $result['tokenSize'] = $db->query('SELECT @@innodb_ft_min_token_size')->fetchColumn(0);

        PerRequest::getInstance()->save(__CLASS__, __METHOD__, $cacheId, $result, PerRequest::VISIBILITY_SHARED);

        return $result;
    }

    /**
     * @return boolean
     */
    public function isQueryFilterEnabled()
    {
        return Config::getInstance()->get(Config::FULLTEXT)->{Config::FULLTEXT_QUERY_FILTER};
    }
}
