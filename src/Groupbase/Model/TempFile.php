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
 * class TempFile
 * 
 * @package     Tinebase
 * @subpackage  Record
 * @property    string  name
 * @property    string  path
 * @property    string  id
 * @property    string  session_id
 * @property    int     size
 * @property    string  type
 * @property    string  time
 */
class TempFile extends AbstractRecord
{
    /**
     * key in $_validators/$_properties array for the field which 
     * represents the identifier
     * 
     * @var string
     */
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * Defintion of properties.
     * This validators get used when validating user generated content with Zend_Input_Filter
     * 
     * @var array list of zend validator
     */
    protected $_validators = array(
        'id'         => array('presence' => 'required', 'allowEmpty' => false, 'Alnum' ),
        'session_id' => array('allowEmpty' => false, 'Alnum' ),
        'time'       => array('allowEmpty' => false),
        'path'       => array('allowEmpty' => false),
        'name'       => array('allowEmpty' => false),
        'type'       => array('allowEmpty' => false),
        'error'      => array('presence' => 'required', 'allowEmpty' => TRUE, 'Int'),
        'size'       => array('allowEmpty' => true)
    );
    
    /**
     * name of fields containing datetime or an array of datetime
     * information
     *
     * @var array list of datetime fields
     */
    protected $_datetimeFields = array(
        'time'
    );
}
