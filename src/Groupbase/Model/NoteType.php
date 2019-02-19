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
 * defines the datatype for note types
 * 
 * @package     Groupbase
 * @subpackage  Notes
 */
class NoteType extends AbstractRecord
{
    /**
     * key in $_validators/$_properties array for the filed which 
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
     * list of zend inputfilter
     * 
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_filters = array(
        'name'              => 'StringTrim'
    );
    
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators = array(
        'id'                     => array('Alnum', 'allowEmpty' => true),
    
        'name'                   => array('presence' => 'required', 'allowEmpty' => false),    
        'icon'                   => array('allowEmpty' => true),
        'icon_class'             => array('allowEmpty' => true),
    
        'description'            => array('allowEmpty' => true),    
        'is_user_type'           => array('allowEmpty' => true),    
    );
    
    /**
     * fields to translate
     *
     * @var array
     */
    protected $_toTranslate = array(
        'name',
        'description'
    );
    
}
