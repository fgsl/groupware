<?php
namespace Fgsl\Groupware\Groupbase\Session\Validator;
use Zend\Session\Validator\ValidatorInterface;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;


/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * AccountStatus
 *
 * @package    Groupbase
 * @subpackage Session
 */
class AccountStatus implements ValidatorInterface
{
    /**
     * Internal data
     *
     * @var string
     */
    protected $data;
    
    /**
     * Constructor
     * get the current user agent and store it in the session as 'valid data'
     *
     * @param string|null $data
     */
    public function __construct($data = null)
    {
        $data= Core::getUser()->accountId;
        $this->data = $data;
    }

    public function getName() {
    }

    public function isValid() {
        $currentAccount = User::getInstance()->getFullUserById($this->getValidData());
        
        return !in_array(
            $currentAccount->accountStatus,
            array(
                ModelUser::ACCOUNT_STATUS_DISABLED,
                ModelUser::ACCOUNT_STATUS_EXPIRED
            )
        );
    }

    public function getData() {
        return $this->data;
    }

}
