<?php
namespace Fgsl\Groupware\Groupbase\Auth;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Core;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * authentication backend factory class
 *  
 * @package     Groupbase
 * @subpackage  Auth
 */
class AuthFactory
{
    /**
     * factory function to return a selected authentication backend class
     *
     * @param   string $_type
     * @param   array $_options
     * @return  AuthInterface
     * @throws  InvalidArgument
     */
    static public function factory($_type, $_options = null)
    {
        switch($_type) {
            case Auth::LDAP:
                $options = array('ldap' => Auth::getBackendConfiguration()); //only pass ldap options without e.g. sql options
                $instance = new Ldap($options);
                break;
                
            case Auth::SQL:
                $instance = new Sql(
                    Core::getDb(),
                    SQL_TABLE_PREFIX . 'accounts',
                    'login_name',
                    'password',
                    'MD5(?)'
                );
                break;

            case Auth::PIN:
                $instance = new Sql(
                    Core::getDb(),
                    SQL_TABLE_PREFIX . 'accounts',
                    'login_name',
                    'pin'
                );
                break;
                
            case Auth::IMAP:
                $instance = new Imap(
                    Auth::getBackendConfiguration()
                );
                break;
            
            case Auth::MODSSL:
                $instance = new ModSsl(
                    Auth::getBackendConfiguration()
                );
                break;
                
            default:
                // check if we have a Auth_$_type backend
                $authProviderClass = 'Fgsl\Groupware\Groupbase\Auth_' . $_type;
                if (class_exists($authProviderClass)) {
                    $instance = new $authProviderClass($_options);
                } else {
                    throw new InvalidArgument('Unknown authentication backend');
                }
                break;
        }
        
        return $instance;
    }
}
