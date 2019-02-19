<?php
namespace Fgsl\Groupware\Addressbook\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Addressbook\Controller\ListRole;
use Fgsl\Groupware\Addressbook\Controller\Contact as ControllerContact;

/**
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
class ListMemberRole extends AbstractRecord
{
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Addressbook';

    /**
     * key in $_validators/$_properties array for the field which
     * represents the identifier
     *
     * @var string
     */
    protected $_identifier = 'id';

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Record/Abstract::$_validators
     */
    protected $_validators = array(
        // tine record fields
        'id'                   => array('allowEmpty' => true,         ),

        // record specific
        'list_id'              => array('allowEmpty' => false         ),
        'list_role_id'         => array('allowEmpty' => false         ),
        'contact_id'           => array('allowEmpty' => false         ),
    );

    /**
     * returns the title of the record
     *
     * @return string
     */
    public function getTitle()
    {
        $listRole = ListRole::getInstance()->get($this->list_role_id);
        $contact = ControllerContact::getInstance()->get($this->contact_id);
        return $listRole->getTitle() . ': ' . $contact->getTitle();
    }
}
