<?php
namespace Fgsl\Groupware\Addressbook\Backend;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Backend\BackendInterface;

/**
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * backend factory class for the addressbook
 * 
 * An instance of the addressbook backendclass should be created using this class
 * $contacts = Addressbook_Backend_Factory::factory(Addressbook_Backend::$type);
 * currently implemented backend classes: Addressbook_Backend_Factory::Sql
 * currently planned backend classed: Addressbook_Backend_Factory::Ldap
 * 
 */
class BackendFactory
{
    /**
     * backend object instances
     */
    private static $_backends = array();
    
    /**
     * constant for Sql contacts backend class
     *
     */
    const SQL = 'sql';
    
    /**
     * constant for LDAP contacts backend class
     *
     */
    const LDAP = 'ldap';

    /**
     * constant for LDAP contacts backend class
     *
     */
    const SALUTATION = 'salutation';

    /**
     * factory function to return a selected contacts backend class
     *
     * @param   string $_type
     * @return  BackendInterface
     * @throws  InvalidArgument if unsupported type was given
     */
    static public function factory ($_type)
    {
        $backend = 'Fgsl\Groupware\Addressbook\Backend\\' . ucfirst($_type);
        
        try {
            self::$_backends[$_type] = new $backend();
            return self::$_backends[$_type];
        } catch (\Exception $e) {
            throw new InvalidArgument('Unknown backend type (' . $_type . ').');
        }
    }

    static public function clearCache()
    {
        self::$_backends = [];
    }
}    
