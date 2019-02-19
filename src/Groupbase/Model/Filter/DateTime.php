<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\DateTime as GroupbaseDateTime;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * DateTime
 * 
 * filters date in one property
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
class DateTime extends Date
{
    /**
     * returns array with the filter settings of this filter
     * - convert value to user timezone
     *
     * @param  bool $_valueToJson resolve value for json api?
     * @return array
     */
    public function toArray($_valueToJson = false)
    {
        $result = parent::toArray($_valueToJson);
       
        if ($this->_operator != 'within' && $_valueToJson == true && $result['value']) {
            $date = new GroupbaseDateTime($result['value']);
            $date->setTimezone(Core::getUserTimezone());
            $result['value'] = $date->toString(AbstractRecord::ISO8601LONG);
        }
        
        return $result;
    }
    
    /**
     * sets value
     *
     * @param mixed $_value
     */
    public function setValue($_value)
    {
        if ($this->_operator != 'within' && $_value) {
            $_value = $this->_convertStringToUTC($_value);
        }
        
        $this->_value = $_value;
    }
    
    /**
     * calculates the date filter values
     *
     * @param string $_operator
     * @param string $_value
     * @return array|string date value
     * @throws UnexpectedValue
     */
    protected function _getDateValues($_operator, $_value)
    {
        if ($_operator === 'within') {
            if (! is_array($_value)) {
                // get beginning / end date and add 00:00:00 / 23:59:59
                date_default_timezone_set((isset($this->_options['timezone']) || array_key_exists('timezone', $this->_options)) && !empty($this->_options['timezone']) ? $this->_options['timezone'] : Core::getUserTimezone());
                $value = parent::_getDateValues($_operator, $_value);
                $value[0] .= ' 00:00:00';
                $value[1] .= ' 23:59:59';
                date_default_timezone_set('UTC');

            } else {
                if (isset($_value['from']) && isset($_value['until'])) {
                    $value[0] = $_value['from'] instanceof GroupbaseDateTime
                        ? $_value['from']->toString() : $_value['from'];
                        $value[1] = $_value['until'] instanceof GroupbaseDateTime
                        ? $_value['until']->toString() : $_value['until'];
                } else {
                    throw new UnexpectedValue('did expect from and until in value');
                }
            }

            // convert to utc
            $value[0] = $this->_convertStringToUTC($value[0]);
            $value[1] = $this->_convertStringToUTC($value[1]);

        } elseif ($_operator === 'inweek') {
            // get beginning / end date and add 00:00:00 / 23:59:59
            date_default_timezone_set((isset($this->_options['timezone']) || array_key_exists('timezone', $this->_options)) && !empty($this->_options['timezone']) ? $this->_options['timezone'] : Core::getUserTimezone());
            $value = parent::_getDateValues($_operator, $_value);
            $value[0] .= ' 00:00:00';
            $value[1] .= ' 23:59:59';
            date_default_timezone_set('UTC');
        } else {
            $value = ($_value instanceof DateTime) ? $_value->toString(AbstractRecord::ISO8601LONG) : $_value;
        }
        
        return $value;
    }
}
