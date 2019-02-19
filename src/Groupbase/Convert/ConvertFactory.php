<?php
namespace Fgsl\Groupware\Groupbase\Convert;

use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Sabre\DAV\Exception\NotImplemented;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * convert factory class
 *
 * @package     Groupbase
 * @subpackage  Convert
 */
class ConvertFactory
{
    /**
     * json converter type
     * 
     * @var string
     */
    const TYPE_JSON     = 'Json';
    
    /**
     * factory function to return a selected converter backend class
     *
     * @param   string|RecordInterface $_record record object or class name
     * @param   string $_type
     * @return  ConvertInterface
     * @throws  NotImplemented
     */
    static public function factory($_record, $_type = self::TYPE_JSON)
    {
        switch ($_type) {
            case self::TYPE_JSON:
                $recordClass = ($_record instanceof RecordInterface) ? get_class($_record) : $_record;
                $converterClass = str_replace('Model', 'Convert', $recordClass);
                $converterClass .= '_Json';
                
                $converter = class_exists($converterClass) ? new $converterClass() : new Tinebase_Convert_Json();
                return $converter;
                 
                break;
            default:
                throw new Tinebase_Exception_NotImplemented('type ' . $_type . ' not supported yet.');
        }
    }
}
