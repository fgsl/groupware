<?php
namespace Fgsl\Groupware\Groupbase\Log;
use Zend\Log\Logger;
use Zend\Log\Writer\AbstractWriter;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Config\Struct;
use Zend\Config\Config;
use Sabre\DAV\Exception\NotFound;
use Zend\Log\Writer\Stream;
use Zend\Log\Filter\Priority;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Helper;
use Fgsl\Groupware\Groupbase\Log\Filter\User as FilterUser;
use Fgsl\Groupware\Groupbase\Log\Filter\Message as FilterMessage;

/**
 *
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * class Log
 * 
 * @package Groupbase
 * @subpackage Log
 *
 * @method void trace(string $msg)
 * @method void debug(string $msg)
 * @method void info(string $msg)
 * @method void warn(string $msg)
 * @method void err(string $msg)
 */
class Log extends Logger
{
    /**
     * known formatters
     *
     * @var array of Formatter
     */
    protected $_formatters = [];

    /**
     * Keeps flipped priorities from _priorities array in LogLevel
     * @var array
     */
    protected $_flippedPriorities;

    /**
     * Stores timezone information
     * @var string
     */
    protected $_tz = NULL;

    /**
     * Class constructor.  Create a new logger
     *
     * @param AbstractWriter|null  $writer  default writer
     */
    public function __construct(AbstractWriter $writer = null)
    {
        parent::__construct($writer);
        $this->_flippedPriorities = array_flip($this->_priorities);
    }

    /**
     * adds a priority in flippedPriorities array
     * @param string  $name
     * @param integer $priority
     * @return void
     */
    public function addPriority($name, $priority)
    {
        parent::addPriority($name, $priority);
        $this->_flippedPriorities[strtoupper($name)] = $priority;
    }

    /**
     * Checks the priority and calls respective log is its it can be called
     * @param string $method
     * @param string $params
     */
    public function __call($method, $params)
    {
        $priority = strtoupper($method);
        if(isset($this->_flippedPriorities[$priority])) {
            if(Core::isLogLevel($this->_flippedPriorities[$priority])) {
                parent::__call($method, $params);
            }
        }
    }

    /**
     * add strings to replace in log output (passwords for example)
     * 
     * @param string $search
     * @param string $replace
     */
    public function addReplacement($search, $replace = '********')
    {
        foreach ($this->_formatters as $formatter) {
            $formatter->addReplacement($search, $replace);
        }
    }
    
    /**
     * get formatter (by config, if given)
     *
     * @return Formatter
     * @param Struct
     *
     * @todo allow multiple formatters for different classes
     */
    public function getFormatter($config = null)
    {
        if ($config && $config->formatter) {
            $formatterClass = 'Formatter_' . ucfirst($config->formatter);
            if (class_exists($formatterClass)) {
                if (! isset($this->_formatters[$config->formatter])) {
                    $this->_formatters[$config->formatter] = new $formatterClass();
                }
                return $this->_formatters[$config->formatter];
            }
        } else {
            if (! isset($this->_formatters['default'])) {
                $this->_formatters['default'] = new Formatter();
            }
        }

        return $this->_formatters['default'];
    }
    
    /**
     * add new log writer defined by a config object/array
     * 
     * @param Struct|Config|array $loggerConfig
     * 
     * @throws NotFound
     */
    public function addWriterByConfig($loggerConfig)
    {
        $loggerConfig = ($loggerConfig instanceof Struct || $loggerConfig instanceof Config)
            ? $loggerConfig : new Struct($loggerConfig);

        if (isset($loggerConfig->active) && $loggerConfig->active == false) {
            // writer deactivated
            return;
        }

        if (empty($loggerConfig->filename)) {
            throw new NotFound('filename missing in logger config');
        }

        $filename = $loggerConfig->filename;
        $writer = new Stream($filename);
        
        $writer->setFormatter($this->getFormatter($loggerConfig));

        $priority = ($loggerConfig->priority) ? (int)$loggerConfig->priority : LogLevel::EMERG;
        $filter = new Priority($priority);
        $writer->addFilter($filter);

        // add more filters here
        if (isset($loggerConfig->filter->user)) {
            $writer->addFilter(new FilterUser($loggerConfig->filter->user));
        }
        if (isset($loggerConfig->filter->message)) {
            $writer->addFilter(new FilterMessage($loggerConfig->filter->message));
        }
        
        $this->addWriter($writer);
    }
    
    /**
     * get max log priority
     * 
     * @param Struct|Config $loggerConfig
     * @return integer
     */
    public static function getMaxLogLevel($loggerConfig)
    {
        $logLevel = $loggerConfig && $loggerConfig->priority ? (int)$loggerConfig->priority : LogLevel::WARN;
        if ($loggerConfig && $loggerConfig->additionalWriters) {
            foreach ($loggerConfig->additionalWriters as $writerConfig) {
                $writerConfig = ($writerConfig instanceof Struct || $writerConfig instanceof Config)
                    ? $writerConfig : new Struct($writerConfig);
                if ($writerConfig->priority && $writerConfig->priority > $logLevel) {
                    $logLevel = (int) $writerConfig->priority;
                }
            }
        }
        return $logLevel;
    }

    /**
     * Log a message at a priority
     *
     * @param  string   $message   Message to log
     * @param  integer  $priority  Priority of message
     * @param  mixed    $extras    Extra information to log in event
     * @return void
     * @throws \Exception
     */
    public function log($message, $priority, $extras = null)
    {
        if ($this->_tz){
            $oldTZ = date_default_timezone_get();
            date_default_timezone_set($this->_tz);
            parent::log($message, $priority, $extras);
            date_default_timezone_set($oldTZ);
        } else {
            parent::log($message, $priority, $extras);
        }
    }

    /**
     * Sets the timezone for the log output
     *
     * @param  string   $_tz   valid PHP timezone or NULL for UTC
     * @return void
     */
    public function setTimezone($_tz)
    {
        $this->_tz = $_tz;
    }

    /**
     * Returns the timezone for the log output
     *
     * @return  string   $_tz   valid PHP timezone or NULL for UTC
     */
    public function getTimezone()
    {
        return($this->_tz);
    }

    /**
     * logUsageAndMethod
     *
     * @param string $file
     * @param float $time_start
     * @param string $method
     * @param int $pid
     *
     * @todo we could make $time_start optional and use Core::STARTTIME if set
     */
    public static function logUsageAndMethod($file, $time_start, $method, $pid = null)
    {
        if (Core::isLogLevel(LogLevel::INFO)) {
            // log profiling information
            $time_end = microtime(true);
            $time = $time_end - $time_start;
            $pid = $pid === null ? getmypid() : $pid;

            Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' FILE: ' . $file
                . ' METHOD: ' . $method
                . ' / TIME: ' . Helper::formatMicrotimeDiff($time)
                . ' / ' . Core::logMemoryUsage() . ' / ' . Core::logCacheSize()
                . ' / PID: ' . $pid
            );
        }
    }
}
