<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\InputFilter\Input;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */


/**
 * class Config
 * 
 * @package     Groupbase
 * @subpackage  Record
 */
class Config extends AbstractRecord
{
    const NOTSET = '###NOTSET###';

    const SOURCE_FILE     = 'FILE';
    const SOURCE_DB       = 'DB';
    const SOURCE_DEFAULT  = 'DEFAULT';

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
    protected $_application = 'Groupbase';
    
    /**
     * record validators
     *
     * @var array
     */
    protected $_validators = array(
        // config table fields
        'id'                    => array('allowEmpty' => true ),
        'application_id'        => array('presence' => 'required', 'allowEmpty' => false, 'Alnum' ),
        'name'                  => array('presence' => 'required', 'allowEmpty' => false ),
        'value'                 => array('presence' => 'required', 'allowEmpty' => true ),

        // virtual fields from definition
        'label'                 => array('allowEmpty' => true ),
        'description'           => array('allowEmpty' => true ),
        'type'                  => array('allowEmpty' => true ),
        'options'               => array('allowEmpty' => true ),
        'clientRegistryInclude' => array('allowEmpty' => true ),
        'setByAdminModule'      => array('allowEmpty' => true ),
        'setBySetupModule'      => array('allowEmpty' => true ),
        'default'               => array('allowEmpty' => true ),

        // source of config, as file config's can't be overwritten by db
        'source'                => array(
            Input::ALLOW_EMPTY => true,
            array('InArray', array(self::SOURCE_FILE, self::SOURCE_DB, self::SOURCE_DEFAULT))
        ),
    );
    
}
