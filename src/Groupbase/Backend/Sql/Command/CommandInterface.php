<?php
namespace Fgsl\Groupware\Groupbase\Backend\Sql\Command;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * encapsulates SQL commands that are different for each dialect
 *
 * @package     Groupbase
 * @subpackage  Backend
 */
interface CommandInterface
{
    /**
     * @return string
     */
    public function getRnd();

    /**
     * @param string $field
     * @return string
     */
    public function getAggregate($field);
    
    /**
     * @param string $field
     * @param mixed $returnIfTrue
     * @param mixed $returnIfFalse
     * @return string
     */
    public function getIfIsNull($field, $returnIfTrue, $returnIfFalse);
    
    /**
     * @param string $condition
     * @param string $returnIfTrue
     * @param string $returnIfFalse
     * @return string
     */
    public function getIfElse($condition, $returnIfTrue, $returnIfFalse);
    
    /**
     * get switch case expression with multiple cases
     *
     * @param string $field
     * @param array $cases
     *
     * @return Zend_Db_Expr
     */
    public function getSwitch($field, $cases);
    
    /**
     * @param mixed $date
     */
    public function setDate($date);
    
    /**
     * @param mixed $date
     */
    public function setDateValue($date);

    /**
     * returns the false value according to backend
     * @return mixed
     */
    public function getFalseValue();
    
    /**
     * returns the true value according to backend
     * @return mixed
     */
    public function getTrueValue();
    
    /**
     *
     */
    public function setDatabaseJokerCharacters();
    
    /**
     * get like keyword
     *
     * @return string
     */
    public function getLike();

    /**
     * get case sensitive like keyword
     *
     * @return string
     */
    public function getCsLike();
    
    /**
     * prepare value for case insensitive search
     *
     * @param string $value
     * @return string
     */
    public function prepareForILike($value);
    
    /**
     * returns field without accents (diacritic signs) - for Pgsql;
     *
     * @param string $field
     * @return string
     */
    public function getUnaccent($field);
    
    /**
     * escape special char
     *
     * @param string $value
     * @return string
     */
    public function escapeSpecialChar($value);
    
    /**
     * Initializes database procedures
     * @param BackendInterface $backend
     */
    public function initProcedures(BackendInterface $backend);

    /**
     * returns something similar to "interval $staticPart * $dynamicPart $timeUnit"
     *
     * @param string $timeUnit
     * @param string $staticPart
     * @param string $dynamicPart
     * @return string
     */
    public function getDynamicInterval($timeUnit, $staticPart, $dynamicPart);

    /**
     * returns something similar to "interval $staticPart $timeUnit"
     *
     * @param string $timeUnit
     * @param string $staticPart
     * @return string
     */
    public function getInterval($timeUnit, $staticPart);
}
