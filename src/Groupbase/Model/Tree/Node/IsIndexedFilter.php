<?php
namespace Fgsl\Groupware\Groupbase\Model\Tree\Node;

use Fgsl\Groupware\Groupbase\Model\Filter\FilterBool;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * IsIndexedFilter
 *
 * filters for tree nodes that are indexed or not
 *
 * @package     Tinebase
 * @subpackage  Filter
 */
class IsIndexedFilter extends FilterBool
{
    /**
     * appends sql to given select statement
     *
     * @param Select                $_select
     * @param AbstractSql           $_backend
     */
    public function appendFilterSql($_select, $_backend)
    {
        $db = $_backend->getAdapter();

        // prepare value
        $op = $this->_value ? ' = ' : ' <> ';

        $quotedField1 = $db->quoteIdentifier('tree_fileobjects.indexed_hash');
        $quotedField2 = $db->quoteIdentifier('tree_filerevisions.hash');
        $_select->where($quotedField1 . $op . $quotedField2);
    }
}