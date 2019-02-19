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
 * Credential Cache Model
 * 
 * @package     Groupbase
 * @subpackage  Model
 *
 * @property string id
 * @property string key
 * @property string cache
 * @property string username
 * @property string password
 * @property string creation_time
 * @property string valid_until
 */
class CredentialCache extends AbstractRecord
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
     * Defintion of properties. All properties of record _must_ be declared here!
     * This validators get used when validating user generated content with Zend_Input_Filter
     * 
     * @var array list of zend validator
     */
    protected $_validators = array(
        'id'                     => array('Alnum', 'allowEmpty' => true),
        'key'                    => array('allowEmpty' => true),
        'cache'                  => array('allowEmpty' => true),
        'username'               => array('allowEmpty' => true),
        'password'               => array('allowEmpty' => true),
        'creation_time'          => array('allowEmpty' => true),
        'valid_until'            => array('allowEmpty' => true),
    );
    
    /**
     * name of fields containing datetime or an array of datetime
     * information
     *
     * @var array list of datetime fields
     */
    protected $_datetimeFields = array(
        'creation_time',
        'valid_until'
    );
    
    /**
     * returns cacheid 
     * 
     * @return array
     */
    public function getCacheId()
    {
        return array(
            'id'    => $this->getId(),
            'key'   => $this->key
        );
    }
    
    /**
     * returns array with record related properties 
     *
     * @param boolean $_recursive
     * @return array
     */
    public function toArray($_recursive = TRUE)
    {
        $array = parent::toArray($_recursive);
        
        // remove highly sensitive data to prevent accidental apperance in logs etc.
        unset($array['key']);
        unset($array['username']);
        unset($array['password']);
        
        return $array;
    }
}
