<?php
namespace Fgsl\Groupware\Setup\Backend\Schema;
use Fgsl\Groupware\Setup\Backend\AbstractBackend;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

abstract class AbstractSchema
{
 
    /**
     * Instance of Setup_Backend_Abstract with public getter {@see getBackend()}
     * @var AbstractBackend
     */
    protected $_backend;
    
     /**
     * the name of the table
     *
     * @var string
     */
    public $name;

    /**
     * constructor of this class
     *
     * @param string|\SimpleXMLElement $_declaration the xml definition of the field
     */
    public function __construct($_declaration = NULL)
    {
        $this->isValid(); //check validity (and thus implicitly log warnings)
    }
    
    /**
     * Setter for {@see $name} property
     * 
     * @param string $_name
     * @return void
     */      
    public function setName($_name)
    {
        $this->name = (string)$_name;
    }
    
    /**
     * Validate "syntax" of this field
     * 
     * @throws InvalidSchema if {@param $throwException} is set to true
     *  
     * @param $throwException
     * @return bool
     */
    public function isValid($throwException = false)
    {
        $isValid = true;
        $messages = array();
        
        $nameValidator = new Zend_Validate_StringLength(1, 30);
        if (!$nameValidator->isValid($this->name)) {
            $isValid = false;
            $messages = array_merge($messages, $nameValidator->getErrors());
        }

        if (!$isValid) {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Invalid schema specified for field ' . $this->name . ': ' . print_r($messages, 1));
            if ($throwException) {
                throw new Setup_Exception_InvalidSchema('Invalid schema specified for field ' . $this->name . ': ' . print_r($messages, 1));
            }
        }           

        return $isValid;
    }
    
    /**
     * Getter for {@see $_backend} property
     * 
     * Lazy loading: Initializes $_backend on first request
     * 
     * @return Setup_Backend_Abstract
     */
    public function getBackend()
    {
        if (!isset($this->_backend)) {
            $this->_backend = Setup_Backend_Factory::factory();
        }
        return $this->_backend;
    }
}
