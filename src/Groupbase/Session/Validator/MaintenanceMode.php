<?php
namespace Fgsl\Groupware\Groupbase\Session\Validator;

use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\Acl\Rights;
use Zend\Session\Validator\ValidatorInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * MaintenanceMode
 *
 * @package    Groupbase
 * @subpackage Session
 */
class MaintenanceMode implements ValidatorInterface
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
        if (Core::inMaintenanceMode()) {
            if (Core::inMaintenanceModeAll()) {
                return false;
            }
            $currentAccount = User::getInstance()->getFullUserById($this->getValidData());
            if (!$currentAccount->hasRight('Tinebase', Rights::MAINTENANCE)) {
                return false;
            }
        }
        
        return true;   
    }

    public function getData() {
        return $this->data;
    }
}
