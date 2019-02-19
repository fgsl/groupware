<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\InputFilter\Input;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class EmailUser
 * 
 * - this class contains all email specific user settings like quota, forwards, ...
 * 
 * @package     Groupbase
 * @subpackage  LDAP
 *
 * @property string emailUID
 * @property string emailGID
 * @property string emailMailQuota
 * @property string emailMailSize
 * @property string emailSieveQuota
 * @property string emailSieveSize
 * @property string emailUserId
 * @property string emailLastLogin
 * @property string emailPassword
 * @property string emailForwards
 * @property string emailForwardOnly
 * @property string emailAliases
 * @property string emailAddress
 * @property string emailUsername
 * @property string emailHost
 * @property string emailPort
 * @property string emailSecure
 * @property string emailAuth
 */
class EmailUser extends AbstractRecord
{
   
    protected $_identifier = 'emailUserId';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * validators / fields
     *
     * @var array
     */
    protected $_validators = array(
        'emailUID'          => array('allowEmpty' => true),
        'emailGID'          => array('allowEmpty' => true),
        'emailMailQuota'    => array('allowEmpty' => true, 'Digits'),
        'emailMailSize'     => array('allowEmpty' => true),
        'emailSieveQuota'   => array('allowEmpty' => true, 'Digits'),
        'emailSieveSize'    => array('allowEmpty' => true),
        'emailUserId'       => array('allowEmpty' => true),
        'emailLastLogin'    => array('allowEmpty' => true),
        'emailPassword'     => array('allowEmpty' => true),
        'emailForwards'     => array('allowEmpty' => true, Input::DEFAULT_VALUE => array()),
        'emailForwardOnly'  => array('allowEmpty' => true, Input::DEFAULT_VALUE => 0),
        'emailAliases'      => array('allowEmpty' => true, Input::DEFAULT_VALUE => array()),
        'emailAddress'      => array('allowEmpty' => true),
    // dbmail username (tine username + dbmail domain)
        'emailUsername'     => array('allowEmpty' => true),
        'emailHost'         => array('allowEmpty' => true),
        'emailPort'         => array('allowEmpty' => true),
        'emailSecure'       => array('allowEmpty' => true),
        'emailAuth'         => array('allowEmpty' => true)
    );
    
    /**
     * datetime fields
     *
     * @var array
     */
    protected $_datetimeFields = array(
        'emailLastLogin'
    );

    /**
     * list of zend inputfilter
     *
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_filters = [
        'emailForwardOnly'      => [['Empty', 0]],
        'emailMailSize'         => [['Empty', 0]],
        'emailMailQuota'        => [['Empty', 0]],
        'emailForwards'         => [['Empty', []]],
        'emailAliases'          => [['Empty', []]],
    ];
    
    /**
     * sets the record related properties from user generated input.
     * 
     * Input-filtering and validation by Input can enabled and disabled
     *
     * @param array $_data            the new data to set
     */
    public function setFromArray(array &$_data)
    {
        foreach (array('emailForwards', 'emailAliases') as $arrayField) {
            if (isset($_data[$arrayField]) && ! is_array($_data[$arrayField])) {
                $_data[$arrayField] = explode(',', preg_replace('/ /', '', $_data[$arrayField]));
            }
        }
        
        parent::setFromArray($_data);
    }
    
} 
