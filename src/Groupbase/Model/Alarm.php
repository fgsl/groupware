<?php
namespace Fgsl\Groupware\Groupbase\Model;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * class Alarm
 * 
 * @package     Groupbase
 * @subpackage  Record
 *
 * @property string     id
 * @property string     record_id
 * @property string     model
 * @property string     alarm_time
 * @property string     minutes_before
 * @property string     sent_time
 * @property string     sent_status
 * @property string     sent_message
 * @property string     options
 */
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\DateTime;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;

class Alarm extends AbstractRecord
{
    /**
     * pending status
     *
     */
    const STATUS_PENDING = 'pending';
    
    /**
     * failure status
     *
     */
    const STATUS_FAILURE = 'failure';

    /**
     * success status
     *
     */
    const STATUS_SUCCESS = 'success';
    
    /**
     * minutes_before value for custom alarm time
     */
    const OPTION_CUSTOM = 'custom';
    
    /**
     * ack client option
     */
    const OPTION_ACK_CLIENT = 'ack_client';
    
    /**
     * ack ip option
     */
    const OPTION_ACK_IP = 'ack_ip';
    
    /**
     * default minutes_before value
     */
    const DEFAULT_MINUTES_BEFORE = 15;
    
    /**
     * identifier field name
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
     * record validators
     *
     * @var array
     */
    protected $_validators = array(
        'id'                => array('allowEmpty' => TRUE),
        'record_id'         => array('allowEmpty' => TRUE, /*'Alnum'*/),
        'model'             => array('presence' => 'required'),
        'alarm_time'        => array('presence' => 'required'),
        'minutes_before'    => array('allowEmpty' => TRUE),
        'sent_time'         => array('allowEmpty' => TRUE),
        'sent_status'       => array('presence' => 'required', array('InArray', array(
            self::STATUS_PENDING, 
            self::STATUS_FAILURE, 
            self::STATUS_SUCCESS,
        )), Input::DEFAULT_VALUE => self::STATUS_PENDING),
        'sent_message'      => array('allowEmpty' => TRUE),
    // xml field with app/model specific options
        'options'           => array('allowEmpty' => TRUE),
    );
    
    /**
     * datetime fields
     *
     * @var array
     */
    protected $_datetimeFields = array(
        'alarm_time',
        'sent_time',
    );
    
    /**
     * set alarm time depending on another date with minutes_before
     *
     * @param DateTime $_date
     */
    public function setTime(DateTime $_date)
    {
        if (! isset($this->minutes_before)) {
            if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                . ' minutes_before not set, reverting to default value(' . self::DEFAULT_MINUTES_BEFORE . ')');
            $this->minutes_before = self::DEFAULT_MINUTES_BEFORE;
        }
        
        if ($this->minutes_before !== self::OPTION_CUSTOM) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Calculating alarm_time ...');
            $date = clone $_date;
            $this->alarm_time = $date->subMinute(round($this->minutes_before));
        }
        
        $this->setOption(self::OPTION_CUSTOM, $this->minutes_before === self::OPTION_CUSTOM);
    }

    /**
     * set minutes_before depending on another date with alarm_time
     *
     * @param DateTime $_date
     */
    public function setMinutesBefore(DateTime $_date)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Current alarm: ' . print_r($this->toArray(), TRUE));
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Date: ' . $_date);
        
        if ($this->getOption(self::OPTION_CUSTOM) !== TRUE) {
            $dtStartTS = $_date->getTimestamp();
            $alarmTimeTS = $this->alarm_time->getTimestamp();
            $this->minutes_before = $dtStartTS < $alarmTimeTS ? 0 : round(($dtStartTS - $alarmTimeTS) / 60);
            
        } else {
            $this->minutes_before = self::OPTION_CUSTOM;
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Resulting minutes_before: ' . $this->minutes_before);
    }
    
    /**
     * sets an option
     * 
     * @param string|array $_key
     * @param mixed $_value
     */
    public function setOption($_key, $_value = null)
    {
        $options = $this->options ? json_decode($this->options) : array();
        
        $_key = is_array($_key) ?: array($_key => $_value);
        foreach ($_key as $key => $value) {
            $options[$key] = $value;
        }
        
        $this->options = json_encode($options);
    }
    
    /**
     * gets an option
     * 
     * @param  string $_key
     * @return mixed
     */
    public function getOption($_key)
    {
        $options = $this->options ? json_decode($this->options) : array();
        return (isset($options[$_key]) || array_key_exists($_key, $options)) ? $options[$_key] : NULL;
    }
}
