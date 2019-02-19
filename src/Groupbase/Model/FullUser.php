<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Groupbase\DateTime;
use Zend\InputFilter\Input;
use Zend\Validator\NotEmpty;
use Zend\Filter\StringToLower;
use Zend\Filter\StringTrim;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\Group\Group;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Auth\Auth;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * defines the datatype for a full users
 * 
 * this datatype contains all information about an user
 * the usage of this datatype should be restricted to administrative tasks only
 * 
 * @package     Groupbase
 * @subpackage  Model
 *
 * @property    string                      accountStatus
 * @property    SAMUser                     sambaSAM            object holding samba settings
 * @property    string                      accountEmailAddress email address of user
 * @property    DateTime                    accountExpires      date when account expires
 * @property    string                      accountFullName     fullname of the account
 * @property    string                      accountDisplayName  displayname of the account
 * @property    string                      accountLoginName    account login name
 * @property    string                      accountLoginShell   account login shell
 * @property    string                      accountPrimaryGroup primary group id
 * @property    string                      container_id
 * @property    string                      configuration
 * @property    array                       groups              list of group memberships
 * @property    DateTime                    lastLoginFailure    time of last login failure
 * @property    int                         loginFailures       number of login failures
 * @property    string                      visibility          displayed/hidden in/from addressbook
 * @property    EmailUser    emailUser
 * @property    EmailUser    imapUser
 * @property    EmailUser    smtpUser
 * @property    DateTime           accountLastPasswordChange      date when password was last changed
 *
 */
class FullUser extends User
{
    const XPROP_PERSONAL_FS_QUOTA = 'personalFSQuota';

    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var ModelConfiguration
     */
    protected static $_configurationObject = NULL;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = [
        'recordName'        => 'User',
        'recordsName'       => 'Users', // ngettext('User', 'Users', n)
        'hasRelations'      => false,
        'hasCustomFields'   => false,
        'hasNotes'          => false,
        'hasTags'           => false,
        'hasXProps'         => true,
        'modlogActive'      => true,
        'hasAttachments'    => false,
        'createModule'      => false,
        'exposeHttpApi'     => false,
        'exposeJsonApi'     => false,

        'containerProperty' => 'container_id',
        // ????
        'containerUsesFilter' => false,

        'titleProperty'     => 'accountDisplayName',
        'appName'           => 'Tinebase',
        'modelName'         => 'FullUser',
        'idProperty'        => 'accountId',

        'filterModel'       => [],

        'fields'            => [
            'accountLoginName'              => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [
                    StringTrim::class => null,
                    StringToLower::class => null,
                ],
            ],
            'accountDisplayName'            => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'accountLastName'               => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'accountFirstName'              => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'accountEmailAddress'           => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'inputFilters'                  => [
                    StringTrim::class => null,
                    StringToLower::class => null,
                ],
            ],
            'accountFullName'               => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'contact_id'                    => [
                //'type'                          => 'record',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'accountLastLogin'              => [
                'type'                          => 'datetime',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'accountLastLoginfrom'          => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'accountLastPasswordChange'     => [
                'type'                          => 'datetime',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'accountStatus'                 => [
                'type'                          => 'string',
                'validators'                    => ['inArray' => [
                    self::ACCOUNT_STATUS_ENABLED,
                    self::ACCOUNT_STATUS_DISABLED,
                    self::ACCOUNT_STATUS_BLOCKED,
                    self::ACCOUNT_STATUS_EXPIRED
                ], Input::DEFAULT_VALUE => self::ACCOUNT_STATUS_ENABLED],
            ],
            'accountExpires'                => [
                'type'                          => 'datetime',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'accountPrimaryGroup'           => [
                //'type'                          => 'record',
                'validators'                    => ['presence' => 'required'],
            ],
            'accountHomeDirectory'          => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'accountLoginShell'             => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'lastLoginFailure'              => [
                'type'                          => 'datetime',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'loginFailures'                 => [
                'type'                          => 'integer',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'sambaSAM'                      => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'openid'                        => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'inputFilters'                  => [NotEmpty::class => false],
            ],
            'emailUser'                     => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'groups'                        => [
                // ??? array? 'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'imapUser'                      => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'smtpUser'                      => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            // ???!?
            'configuration'                 => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'visibility'                    => [
                'type'                          => 'string',
                'validators'                    => ['inArray' => [
                    self::VISIBILITY_HIDDEN,
                    self::VISIBILITY_DISPLAYED,
                ], Input::DEFAULT_VALUE => self::VISIBILITY_DISPLAYED],
            ],
        ],
    ];

    
    /**
     * adds email and samba users, generates username + user password and 
     *   applies multiple options (like accountLoginNamePrefix, accountHomeDirectoryPrefix, ...)
     * 
     * @param array $options
     * @param string $password
     * @return string
     */
    public function applyOptionsAndGeneratePassword($options, $password = NULL)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' ' . print_r($options, TRUE));
        
        if (! isset($this->accountLoginName)) {
            $this->accountLoginName = User::getInstance()->generateUserName($this, (isset($options['userNameSchema'])) ? $options['userNameSchema'] : 1);
            $this->accountFullName = User::getInstance()->generateAccountFullName($this);
        }
        
        if (empty($this->accountPrimaryGroup)) {
            if (! empty($options['group_id'])) {
                $groupId = $options['group_id'];
            } else {
                // use default user group
                $defaultUserGroup = Group::getInstance()->getDefaultGroup();
                $groupId = $defaultUserGroup->getId();
            }
            $this->accountPrimaryGroup = $groupId;
        }
        
        // add prefix to login name if given
        if (! empty($options['accountLoginNamePrefix'])) {
            $this->accountLoginName = $options['accountLoginNamePrefix'] . $this->accountLoginName;
        }
        
        // short username if needed
        $this->accountLoginName = $this->shortenUsername();
        
        // add home dir if empty and prefix is given (append login name)
        if (empty($this->accountHomeDirectory) && ! empty($options['accountHomeDirectoryPrefix'])) {
            $this->accountHomeDirectory = $options['accountHomeDirectoryPrefix'] . $this->accountLoginName;
        }
        
        // create email address if accountEmailDomain if given
        if (empty($this->accountEmailAddress) && ! empty($options['accountEmailDomain'])) {
            $this->accountEmailAddress = $this->accountLoginName . '@' . $options['accountEmailDomain'];
        }
        
        if (! empty($options['samba'])) {
            $this->_addSambaSettings($options['samba']);
        }
        
        if (empty($this->accountLoginShell) && ! empty($options['accountLoginShell'])) {
            $this->accountLoginShell = $options['accountLoginShell'];
        }
        
        // generate passwd (use accountLoginName or password from options or password from csv in this order)
        $userPassword = $this->accountLoginName;
        
        if (! empty($password)) {
            $userPassword = $password;
        } else if (! empty($options['password'])) {
            $userPassword = $options['password'];
        }
        
        $this->_addEmailUser($userPassword);
        
        return $userPassword;
    }
    
    /**
     * add samba settings to user
     *
     * @param array $options
     */
    protected function _addSambaSettings($options)
    {
        $samUser = new SAMUser(array(
            'homePath'      => (isset($options['homePath'])) ? $options['homePath'] . $this->accountLoginName : '',
            'homeDrive'     => (isset($options['homeDrive'])) ? $options['homeDrive'] : '',
            'logonScript'   => (isset($options['logonScript'])) ? $options['logonScript'] : '',
            'profilePath'   => (isset($options['profilePath'])) ? $options['profilePath'] . $this->accountLoginName : '',
            'pwdCanChange'  => isset($options['pwdCanChange'])  ? $options['pwdCanChange']  : new DateTime('@1'),
            'pwdMustChange' => isset($options['pwdMustChange']) ? $options['pwdMustChange'] : new DateTime('@2147483647')
        ));
    
        $this->sambaSAM = $samUser;
    }
    
    /**
     * add email users to record (if email set + config exists)
     *
     * @param string $_password
     */
    protected function _addEmailUser($password)
    {
        if (! empty($this->accountEmailAddress)) {
            
            if (isset($this->imapUser)) {
                $this->imapUser->emailPassword = $password;
            } else {
                $this->imapUser = new EmailUser(array(
                    'emailPassword' => $password
                ));
            }
            
            if (isset($this->smtpUser)) {
                $this->smtpUser->emailPassword = $password;
            } else {
                $this->smtpUser = new EmailUser(array(
                    'emailPassword' => $password
                ));
            }
        }
    }
    
    /**
     * check if windows password needs to b changed
     *  
     * @return boolean
     */
    protected function _sambaSamPasswordChangeNeeded()
    {
        if ($this->sambaSAM instanceof SAMUser 
            && isset($this->sambaSAM->pwdMustChange) 
            && $this->sambaSAM->pwdMustChange instanceof DateTime) 
        {
            if ($this->sambaSAM->pwdMustChange->compare(DateTime::now()) < 0) {
                if (!isset($this->sambaSAM->pwdLastSet)) {
                    if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                        . ' User ' . $this->accountLoginName . ' has to change his pw: it got never set by user');
                        
                    return true;
                    
                } else if (isset($this->sambaSAM->pwdLastSet) && $this->sambaSAM->pwdLastSet instanceof DateTime) {
                    $dateToCompare = $this->sambaSAM->pwdLastSet;
                    
                    if ($this->sambaSAM->pwdMustChange->compare($dateToCompare) > 0) {
                        if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                            . ' User ' . $this->accountLoginName . ' has to change his pw: ' . $this->sambaSAM->pwdMustChange . ' > ' . $dateToCompare);
                            
                        return true;
                    }
                } else {
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Password is up to date.');
                }
            }
        }
        
        return false;
    }
    
    /**
     * check if sql password needs to be changed
     * 
     * @return boolean
     */
    protected function _sqlPasswordChangeNeeded()
    {
        if (empty($this->accountLastPasswordChange)) {
            return true;
        }
        $passwordChangeDays = Config::getInstance()->get(Config::PASSWORD_POLICY_CHANGE_AFTER);

        if ($passwordChangeDays > 0) {
            $now = DateTime::now();
            return $this->accountLastPasswordChange->isEarlier($now->subDay($passwordChangeDays));
        } else {
            return false;
        }
    }

    /**
     * return the public informations of this user only
     *
     * @return ModelUser
     */
    public function getPublicUser()
    {
        $result = new ModelUser($this->toArray(), true);
        
        return $result;
    }
    
    /**
     * returns user login name
     *
     * @return string
     */
    public function __toString()
    {
        return $this->accountLoginName;
    }
    
    /**
     * returns TRUE if user has to change his/her password (compare sambaSAM->pwdMustChange with DateTime::now())
     *
     * TODO switch check AUTH backend?
     *
     * @return boolean
     */
    public function mustChangePassword()
    {
        switch (User::getConfiguredBackend()) {
            case User::ACTIVEDIRECTORY:
                return $this->_sambaSamPasswordChangeNeeded();
                break;
                
            case User::LDAP:
                return $this->_sambaSamPasswordChangeNeeded();
                break;
                
            default:
                if (Auth::getConfiguredBackend() === Auth::SQL) {
                    return $this->_sqlPasswordChangeNeeded();
                } else {
                    // no pw change needed for non-sql auth backends
                    return false;
                }
                break;
        }
    }
    
    /**
     * Short username to a configured length
     */
    public function shortenUsername()
    {
        $username = $this->accountLoginName;
        $maxLoginNameLength = Config::getInstance()->get(Config::MAX_USERNAME_LENGTH);
        if (!empty($maxLoginNameLength) && strlen($username) > $maxLoginNameLength) {
            $username = substr($username, 0, $maxLoginNameLength);
        }
        
        return $username;
    }

    public function runConvertToData()
    {
        if (isset($this->_properties['configuration']) && is_array($this->_properties['configuration'])) {
            if (count($this->_properties['configuration']) > 0) {
                $this->_properties['configuration'] = json_encode($this->_properties['configuration']);
            } else {
                $this->_properties['configuration'] = null;
            }
        }

        parent::runConvertToData();
    }
}
