<?php
namespace Fgsl\Groupware\Groupbase\Event;

use Fgsl\Groupware\Groupbase\Record\AbstractRecord;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * base class for all events
 *
 * @package     Tinebase
 * @subpackage  Event
 */
abstract class AbstractEvent
{
    /**
     * @var string
     */
    protected $_id;
    
    public function __construct(array $_values = array())
    {
        $this->_id = AbstractRecord::generateUID();
        
        foreach($_values as $key => $value) {
            $this->$key = $value;
        }
    }
    
    /**
     * get id of event
     * 
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }
}
