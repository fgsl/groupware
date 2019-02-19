<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Record\AbstractRecord;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class SAMUser
 * 
 * @property    string  acctFlags
 * @package     Groupbase
 * @subpackage  Samba
 */
class SAMUser extends AbstractRecord
{
   
    protected $_identifier = 'sid';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Groupbase';
    
    /**
     * name of fields containing datetime or or an array of datetime
     * information
     *
     * @var array list of datetime fields
     */    
    protected $_datetimeFields = array(
        'logonTime',
        'logoffTime',
        'kickoffTime',
        'pwdLastSet',
        'pwdCanChange',
        'pwdMustChange'
    );

    protected $_validators = array(
        'sid'              => array('allowEmpty' => true),
        'primaryGroupSID'  => array('allowEmpty' => true),
        'acctFlags'        => array('allowEmpty' => true),
        'homeDrive'        => array('allowEmpty' => true),
        'homePath'         => array('allowEmpty' => true),
        'profilePath'      => array('allowEmpty' => true),
        'logonScript'      => array('allowEmpty' => true),    
        'logonTime'        => array('allowEmpty' => true),
        'logoffTime'       => array('allowEmpty' => true),
        'kickoffTime'      => array('allowEmpty' => true),
        'pwdLastSet'       => array('allowEmpty' => true),
        'pwdCanChange'     => array('allowEmpty' => true),
        'pwdMustChange'    => array('allowEmpty' => true),
    );
} 
