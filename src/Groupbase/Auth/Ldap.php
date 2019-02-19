<?php
namespace Fgsl\Groupware\Groupbase\Auth;

use Zend\Authentication\Adapter\Ldap as AdapterLdap;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * LDAP authentication backend
 * 
 * @package     Groupbase
 * @subpackage  Auth
 */
class Ldap extends AdapterLdap implements AuthInterface
{
    /**
     * Constructor
     *
     * @param array  $options An array of arrays of Zend_Ldap options
     * @param string $username
     * @param string $password
     */
    public function __construct(array $options = array(),  $username = null, $password = null)
    {
        $this->setOptions($options);
        if ($username !== null) {
            $this->setIdentity($username);
        }
        if ($password !== null) {
            $this->setCredential($password);
        }
    }
    
    /**
     * Returns the LDAP Object
     *
     * @return Ldap The Ldap object used to authenticate the credentials
     */
    public function getLdap()
    {
        if ($this->_ldap === null) {
            /**
             * @see Ldap
             */
            $this->_ldap = new Ldap($this->getOptions());
        }
        return $this->_ldap;
    }
    
    /**
     * set login name
     *
     * @param string $_identity
     * @return Ldap
     */
    public function setIdentity($_identity)
    {
        parent::setUsername($_identity);
        return $this;
    }
    
    /**
     * set password
     *
     * @param string $_credential
     * @return Ldap
     */
    public function setCredential($_credential)
    {
        parent::setPassword($_credential);
        return $this;
    }    
}
