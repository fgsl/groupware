<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Acl\Rights;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Group\Group;
use Psr\Log\LogLevel;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * defines the datatype for a set of rights for one tag and one account
 * 
 * @package     Groupbase
 * @subpackage  Tags
 */
class TagRight extends AbstractRecord
{
    /**
     * Right to view/see/read the tag
     */
    const VIEW_RIGHT = 'view';
    /**
     * Right to attach the tag to a record
     */
    const USE_RIGHT = 'use';
    
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
    protected $_application = 'Tinebase';
    
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators = array(
        'id'           => array('Alnum', 'allowEmpty' => true),
        'tag_id'       => array('Alnum', 'allowEmpty' => true),
        'account_type' => array(array('InArray', array(
            Rights::ACCOUNT_TYPE_ANYONE, 
            Rights::ACCOUNT_TYPE_USER, 
            Rights::ACCOUNT_TYPE_GROUP,
        )), 'presence' => 'required', 'allowEmpty' => false),
        'account_id'   => array('presence' => 'required', 'allowEmpty' => TRUE, 'default' => '0'),
        'view_right'   => array('presence' => 'required', 'default' => false, 
            array('InArray', array(true, false)), 'allowEmpty' => true),
        'use_right'    => array('presence' => 'required', 'default' => false, 
            array('InArray', array(true, false)), 'allowEmpty' => true),
    );
    
    /**
     * overwrite default constructor as convinience for data from database
     */
    public function __construct($_data = NULL, $_bypassFilters = false, $_convertDates = true)
    {
        if (is_array($_data) && isset($_data['account_right'])) {
            $rights = explode(',', $_data['account_right']);
            $_data['view_right'] = in_array(self::VIEW_RIGHT, $rights);
            $_data['use_right']  = in_array(self::USE_RIGHT, $rights);
        }
        parent::__construct($_data, $_bypassFilters, $_convertDates);
    }
    
    /**
     * Applies the requierd params for tags acl to the given select object
     * 
     * @param  Select $_select
     * @param  string         $_right      required right
     * @param  string         $_idProperty property of tag id in select statement
     * @return void
     */
    public static function applyAclSql($_select, $_right = self::VIEW_RIGHT, $_idProperty = 'id')
    {
        if (empty($_right)) {
            throw new InvalidArgument('right is empty');
        }
        
        $db = Core::getDb();
        if($_idProperty == 'id'){
            $_idProperty = $db->quoteIdentifier('id');
        }
        
        if (! is_object(Core::getUser())) {
            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                ' Cannot apply ACL, no user object found. This might happen during setup/update.');
            return;
        }
        
        $currentAccountId = Core::getUser()->getId();
        $currentGroupIds = Group::getInstance()->getGroupMemberships($currentAccountId);
        $groupCondition = ( !empty($currentGroupIds) ) ? ' OR (' . $db->quoteInto($db->quoteIdentifier('acl.account_type') . ' = ?', Rights::ACCOUNT_TYPE_GROUP) .
            ' AND ' . $db->quoteInto($db->quoteIdentifier('acl.account_id') . ' IN (?)', $currentGroupIds) . ' )' : '';
        
        $where = $db->quoteInto($db->quoteIdentifier('acl.account_type') . ' = ?', Rights::ACCOUNT_TYPE_ANYONE) . ' OR (' .
            $db->quoteInto($db->quoteIdentifier('acl.account_type') . ' = ?', Rights::ACCOUNT_TYPE_USER) . ' AND ' .
            $db->quoteInto($db->quoteIdentifier('acl.account_id')   . ' = ?', $currentAccountId) . ' ) ' .
            $groupCondition;
        
        $_select->join(array('acl' => SQL_TABLE_PREFIX . 'tags_acl'), $_idProperty . ' = '. $db->quoteIdentifier('acl.tag_id'), array() )
            ->where($where)
            ->where($db->quoteIdentifier('acl.account_right') . ' = ?', $_right);
    }
}
