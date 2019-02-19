<?php
namespace Fgsl\Groupware\Groupbase\Backend;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Abstract class for a Tine 2.0 backend
 * 
 * @package     Tinebase
 * @subpackage  Backend
 */
use Fgsl\Groupware\Groupbase\Backend\BackendInterface;

abstract class AbstractBackend implements BackendInterface
{
    /**
     * backend type constant
     *
     * @var string
     */
    protected $_type = NULL;
        
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = NULL;
        
    /**
     * get backend type
     *
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }
    
    /**
     * get model name
     *
     * @return string
     */
    public function getModelName()
    {
        return $this->_modelName;
    }
}
