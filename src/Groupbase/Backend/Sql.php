<?php
namespace Fgsl\Groupware\Groupbase\Backend;

use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Exception\Backend\Database;
use Zend\Db\Adapter\AdapterInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * default sql backend
 *
 * @package     Groupbase
 * @subpackage  Backend
 */
class Sql extends AbstractSql
{
    /**
     * the constructor
     * 
     * allowed options:
     *  - modelName
     *  - tableName
     *  - tablePrefix
     *  - modlogActive
     *
     * @param array $_options (optional)
     * @param AdapterInterface $_dbAdapter (optional) the db adapter
     * @see AbstractSql::__construct()
     * @throws Database
     */
    public function __construct($_options = array(), $_dbAdapter = NULL)
    {
        parent::__construct($_dbAdapter, $_options);
    }
}
