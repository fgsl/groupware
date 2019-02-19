<?php
namespace Fgsl\Groupware\Groupbase\Model\Tree\Node;
use Fgsl\Groupware\Groupbase\Model\Filter\Text;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Admin\Acl\Rights;
use Fgsl\Groupware\Groupbase\Model\Tree\Node;
use Fgsl\Groupware\Groupbase\FileSystem\FileSystem;
use Fgsl\Groupware\Groupbase\Acl\AbstractRights;


/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * PathFilter
 * 
 * @package     Groupbase
 * @subpackage  Filter
 * 
 */
class PathFilter extends Text 
{
    /**
     * @var array list of allowed operators
     */
    protected $_operators = array(
        0 => 'equals',
    );
    
    /**
     * the parsed path record
     * 
     * @var Path
     */
    protected $_path = NULL;
    
    /**
     * set options 
     *
     * @param  array $_options
     */
    protected function _setOptions(array $_options)
    {
        $_options['ignoreAcl'] = isset($_options['ignoreAcl']) ? $_options['ignoreAcl'] : false;
        
        $this->_options = $_options;
    }
    
    /**
     * returns array with the filter settings of this filter
     *
     * @param  bool $_valueToJson resolve value for json api?
     * @return array
     */
    public function toArray($_valueToJson = false)
    {
        $result = parent::toArray($_valueToJson);
        
        if (! $this->_path && '/' !== $this->_value) {
            $this->_path = Path::createFromPath($this->_value);
        }
        
        if ('/' === $this->_value || $this->_path->containerType === Path::TYPE_ROOT) {
            $node = new Node(array(
                'name' => 'root',
                'path' => '/',
            ), true);
        } else {
            $node = FileSystem::getInstance()->stat($this->_path->statpath);
            $node->path = $this->_path->flatpath;
        }

        $result['value'] = $node->toArray();
        
        return $result;
    }
    
    /**
     * appends sql to given select statement
     *
     * @param  Select                    $_select
     * @param  AbstractSql     $_backend
     */
    public function appendFilterSql($_select, $_backend)
    {
        $this->_parsePath();
        
        $this->_addParentIdFilter($_select, $_backend);
    }
    
    /**
     * parse given path (filter value): check validity, set container type, do replacements
     */
    protected function _parsePath()
    {
        if ('/' === $this->_value) {
            if (! Core::getUser()->hasRight('Admin', Rights::VIEW_QUOTA_USAGE)) {
                throw new AccessDenied('You don\'t have the right to run this application');
            }
            return;
        }

        $this->_path = Path::createFromPath($this->_value);
        
        if (! $this->_options['ignoreAcl'] && ! Core::getUser()->hasRight($this->_path->application->name, AbstractRights::RUN)) {
            throw new AccessDenied('You don\'t have the right to run this application');
        }
    }

    /**
     * adds parent id filter sql
     *
     * @param  Select                    $_select
     * @param  AbstractSql               $_backend
     */
    protected function _addParentIdFilter($_select, $_backend)
    {
        if ('/' === $this->_value) {
            $parentIdFilter = new Text('parent_id', 'isnull', '');
        } else {
            $node = FileSystem::getInstance()->stat($this->_path->statpath);
            $parentIdFilter = new Text('parent_id', 'equals', $node->getId());
        }
        $parentIdFilter->appendFilterSql($_select, $_backend);
    }
}
