<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\Db\Sql\Select;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Tags Filter Class
 * 
 * @package    Groupbase
 * @subpackage Tags
 */
class TagFilter extends AbstractRecord
{
    /**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */    
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tasks';
    
    protected $_validators = array(
        'id'                   => array('allowEmpty' => true,  'Alnum'),

        'owner'                => array('allowEmpty' => true),
        'application'          => array('allowEmpty' => true),
        'name'                 => array('allowEmpty' => true),
        'description'          => array('allowEmpty' => true),
        'type'                 => array('presence'   => 'required',
                                        'allowEmpty' => true,
                                        array('InArray', array(Tag::TYPE_PERSONAL, Tag::TYPE_SHARED)),
                                        // tag type should have empty default value
                                        'default'    => ''
                                  ),
        'grant'                => array('presence'   => 'required',
                                        'allowEmpty' => false,
                                        array('InArray', array(TagRight::VIEW_RIGHT, TagRight::USE_RIGHT)),
                                        'default'    => TagRight::VIEW_RIGHT
                                  ),
    );
    
    /**
     * Returns a select object according to this filter
     * 
     * @return Select
     */
    public function getSelect()
    {
        $db = Core::getDb();
        $select = $db->select()
            ->from (array('tags' => SQL_TABLE_PREFIX . 'tags'))
            ->where($db->quoteIdentifier('is_deleted') . ' = 0')
            ->order('name', 'ASC');
        
        if (!empty($this->application)) {
            $applicationId = $this->application instanceof ModelApplication 
                ? $this->application->getId() 
                : Application::getInstance()->getApplicationByName($this->application)->getId();
            
            $select->join(
                array('context' => SQL_TABLE_PREFIX . 'tags_context'), 
                $db->quoteIdentifier('tags.id') . ' = ' . $db->quoteIdentifier('context.tag_id'),
                array()
            )->where($db->quoteInto($db->quoteIdentifier('context.application_id') . ' IN (?)', array('0', $applicationId)));
        }
        
        $orWhere = array();
        if (!empty($this->name)) {
            $orWhere[] = $db->quoteInto($db->quoteIdentifier('tags.name') . ' LIKE ?', $this->name);
        }
        if (!empty($this->description)) {
            $orWhere[] = $db->quoteInto($db->quoteIdentifier('tags.description') . ' LIKE ?', $this->description);
        }
        if (! empty($orWhere)) {
            $select->where(implode(' OR ', $orWhere));
        }
        
        if ($this->type) {
            $select->where($db->quoteInto($db->quoteIdentifier('tags.type') . ' = ?', $this->type));
        }
        
        $select->group('tags.id');
        
        return $select;
    }
}
