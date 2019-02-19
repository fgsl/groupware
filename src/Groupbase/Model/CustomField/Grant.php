<?php
namespace Fgsl\Groupware\Groupbase\Model\CustomField;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Acl\Rights;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * Grant
 * 
 * @package     Groupbase
 * @subpackage  CustomField
 */
class Grant extends AbstractRecord
{
    /**
     * grant to write custom field
     */
    const GRANT_READ = 'readGrant';
    
    /**
     * grant to write custom field
     */
    const GRANT_WRITE = 'writeGrant';
    
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
     * Defintion of properties
     * 
     * @var array list of zend validator
     */
    protected $_validators = array(
        'id'                => array('allowEmpty' => TRUE),
        'customfield_id'    => array('presence' => 'required'),
        'account_id'        => array('presence' => 'required', 'allowEmpty' => TRUE, 'default' => '0'),
        'account_type'      => array(
            'presence' => 'required',
            array('InArray', array(
                Rights::ACCOUNT_TYPE_ANYONE,
                Rights::ACCOUNT_TYPE_USER,
                Rights::ACCOUNT_TYPE_GROUP
            )),
        ),
        'account_grant'     => array('presence' => 'required'),
    );
    
    /**
     * get all possible grants
     *
     * @return  array   all container grants
     */
    public static function getAllGrants()
    {
        $allGrants = array(
            self::GRANT_READ,
            self::GRANT_WRITE,
        );
    
        return $allGrants;
    }
}