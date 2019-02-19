<?php
namespace Fgsl\Groupware\Groupbase\Auth;

use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Mail\Mail;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/

/**
 * IMAP authentication backend
 * 
 * @package     Groupbase
 * @subpackage  Auth
 */
class Imap implements AuthInterface
{
    /**
     * Constructor
     *
     * @param array  $options An array of arrays of IMAP options
     * @param string $username
     * @param string $password
     */
    public function __construct(array $options = array(), $username = null, $password = null)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(
            __METHOD__ . '::' . __LINE__ . ' ' . print_r($options, true));
        
        parent::__construct($options, $username, $password);

        $connectionOptions = Mail::getConnectionOptions(10);
        $this->getImap()->setConnectionOptions($connectionOptions);
    }
    
    /**
     * set loginname
     *
     * @param string $_identity
     * @return Imap
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
     * @return Imap
     */
    public function setCredential($_credential)
    {
        parent::setPassword($_credential);
        return $this;
    }
    public function authenticate() {
	}
    
}
