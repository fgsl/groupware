<?php
namespace Fgsl\Groupware\Groupbase\Auth;

use Zend\Authentication\Adapter\AdapterInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * authentication backend interface
 *  
 * @package     Tinebase
 * @subpackage  Auth
 */
interface AuthInterface extends AdapterInterface
{
    /**
     * setIdentity() - set the value to be used as the identity
     *
     * @param  string $value
     * @return AdapterInterface Provides a fluent interface
     */
    public function setIdentity($value);
    
    /**
     * setCredential() - set the credential value to be used
     *
     * @param  string $credential
     * @return AdapterInterface Provides a fluent interface
     */
    public function setCredential($credential);
}
