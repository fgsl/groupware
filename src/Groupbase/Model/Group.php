<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Group as GroupbaseGroup;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Record\Diff;
use Zend\InputFilter\Input;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * defines the datatype for the group object
 * 
 * @package     Groupbase
 * @subpackage  Group
 *
 * @property    string  id
 * @property    string  name
 * @property    string  description
 * @property    string  email
 * @property    array   members
 * @property    string  visibility
 * @property    string  list_id
 */
class Group extends AbstractRecord
{
    /**
    * hidden from addressbook
    *
    * @var string
    */
    const VISIBILITY_HIDDEN    = 'hidden';
    
    /**
     * visible in addressbook
     *
     * @var string
     */
    const VISIBILITY_DISPLAYED = 'displayed';
    
    /**
     * list of zend inputfilter
     * 
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_filters = array(
        'id'            => 'StringTrim',
        'name'          => 'StringTrim',
        'description'   => 'StringTrim',
    );

    protected $_validators = array(
        'id'            => array(Input::ALLOW_EMPTY => true),
        'container_id'  => array(Input::ALLOW_EMPTY => true),
        'list_id'       => array(Input::ALLOW_EMPTY => true),
        'name'          => array(Input::PRESENCE => Input::PRESENCE_REQUIRED),
        'description'   => array(Input::ALLOW_EMPTY => true),
        'members'       => array(Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => array()),
        'email'         => array(Input::ALLOW_EMPTY => true),
        'visibility'    => array(
            ['InArray', [self::VISIBILITY_HIDDEN, self::VISIBILITY_DISPLAYED]],
            Input::DEFAULT_VALUE => self::VISIBILITY_DISPLAYED
        ),
        'created_by'             => array(Input::ALLOW_EMPTY => true),
        'creation_time'          => array(Input::ALLOW_EMPTY => true),
        'last_modified_by'       => array(Input::ALLOW_EMPTY => true),
        'last_modified_time'     => array(Input::ALLOW_EMPTY => true),
        'is_deleted'             => array(Input::ALLOW_EMPTY => true),
        'deleted_time'           => array(Input::ALLOW_EMPTY => true),
        'deleted_by'             => array(Input::ALLOW_EMPTY => true),
        'seq'                    => array(Input::ALLOW_EMPTY => true),
        );
    
   /**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */    
    protected $_identifier = 'id';
    
    /**
     * name of fields containing datetime or or an array of datetime
     * information
     *
     * @var array list of datetime fields
     */
    protected $_datetimeFields = array(
        'creation_time',
        'last_modified_time',
        'deleted_time',
    );

    protected static $_replicable = true;
    
    /**
     * converts a int, string or Group to a groupid
     *
     * @param   int|string|Group $_groupId the groupid to convert
     * @return  string
     * @throws  InvalidArgument
     * 
     * @todo rename this function because we now have string ids
     */
    static public function convertGroupIdToInt($_groupId)
    {
        return self::convertId($_groupId, 'Fgsl\Groupware\Groupbase\Model\Group');
    }
    
    /**
     * (non-PHPdoc)
     * @see AbstractRecord::setFromArray()
     */
    public function setFromArray(array &$_data)
    {
        parent::setFromArray($_data);
        
        // sanitize members (could be an array of user arrays -> expecting to contain only ids)
        if (isset($this->members) && is_array($this->members) && count($this->members) > 0 && is_array($this->members[0])) {
            $memberIds = array();
            foreach ($this->members as $member) {
                $memberIds[] = $member['id'];
            }
            $this->members = $memberIds;
        }
    }

    /**
     * returns true if this record should be replicated
     *
     * @return boolean
     */
    public function isReplicable()
    {
        return static::$_replicable;
    }

    /**
     * @param boolean $isReplicable
     */
    public static function setReplicable($isReplicable)
    {
        static::$_replicable = (bool)$isReplicable;
    }

    /**
     * undoes the change stored in the diff
     *
     * will (re)load and populate members property if required
     *
     * @param Diff $diff
     * @return void
     */
    public function undo(Diff $diff)
    {
        $members = null;
        $oldMembers = null;
        // clone diff here to prevent accidental/unintended change
        $diffWithoutMembers = clone $diff;
        if (isset($diff->diff['members'])) {
            $members = $diff->diff['members'];
            unset($diffWithoutMembers->xprops('diff')['members']);
        }
        if (isset($diff->oldData['members'])) {
            $oldMembers = $diff->oldData['members'];
            unset($diffWithoutMembers->xprops('oldData')['members']);
        }

        parent::undo($diffWithoutMembers);

        if (null === $members || null === $oldMembers) {
            return;
        }

        $currentMembers = GroupbaseGroup::getInstance()->getGroupMembers($this->getId());

        if (!empty($remove = array_diff($members, $oldMembers))) {
            $currentMembers = array_diff($currentMembers, $remove);
        }
        if (!empty($add = array_diff($oldMembers, $members))) {
            $currentMembers = array_merge($currentMembers, $add);
        }
        $this->members = $currentMembers;
    }
}
