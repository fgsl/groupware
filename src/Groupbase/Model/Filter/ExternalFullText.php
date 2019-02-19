<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Exception\NotImplemented;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Zend\Db\Sql\Select;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * filters one filterstring in one property
 *
 * @package     Groupbase
 * @subpackage  Filter
 */
class ExternalFullText extends FullText
{

    /**
     * set options
     *
     * @param array $_options
     * @throws InvalidArgument
     */
    protected function _setOptions(array $_options)
    {
        if (!isset($_options['idProperty'])) {
            throw new InvalidArgument('a idProperty must be specified in the options');
        }
        parent::_setOptions($_options);
    }

    /**
     * appends sql to given select statement
     *
     * @param  Select                $_select
     * @param  AbstractSql           $_backend
     * @throws NotImplemented
     */
    public function appendFilterSql($_select, $_backend)
    {
        $fulltextConfig = Config::getInstance()->get(Config::FULLTEXT);

        if ('Sql' !== $fulltextConfig->{Config::FULLTEXT_BACKEND}) {
            throw new NotImplemented('only Sql backend is implemented currently');
        }

        $db = $_select->getAdapter();
        $select = $db->select()->from(array('external_fulltext' => SQL_TABLE_PREFIX . 'external_fulltext'), array('id'));
        $this->_field = 'text_data';
        if (isset($this->_options['tablename'])) {
            $oldTableName = $this->_options['tablename'];
        } else {
            $oldTableName = null;
        }
        $this->_options['tablename'] = 'external_fulltext';

        parent::appendFilterSql($select, $_backend);

        if (null === $oldTableName) {
            unset($this->_options['tablename']);
        } else {
            $this->_options['tablename'] = $oldTableName;
        }
        $this->_field = $this->_options['idProperty'];
        $stmt = $select->query(Adapter::FETCH_NUM);
        $ids = array();
        foreach($stmt->fetchAll() as $row) {
            $ids[] = $row[0];
        }

        $_select->where($this->_getQuotedFieldName($_backend) . ' IN (?)', empty($ids) ? new Expression('NULL') : $ids);
    }

    /**
     * @return boolean
     */
    public function isQueryFilterEnabled()
    {
        // external fulltext is always enabled in query filter
        return true;
    }
}
