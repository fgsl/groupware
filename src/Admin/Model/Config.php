<?php
namespace Fgsl\Groupware\Admin\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Config
 * 
 * @package     Admin
 * @subpackage  Record
 */
class Config extends AbstractRecord
{
    /**
     * default internal addressbook for new users/groups
     * 
     * @var string
     * 
     * @todo move to addressbook?
     */
    const DEFAULTINTERNALADDRESSBOOK = 'defaultInternalAddressbook';
        
    /**
     * identifier
     * 
     * @var string
     */ 
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Admin';
    
    /**
     * record validators
     *
     * @var array
     */
    protected $_validators = array(
        'id'                => array('allowEmpty' => true ),
        'defaults'          => array('allowEmpty' => true ),
    );
}
