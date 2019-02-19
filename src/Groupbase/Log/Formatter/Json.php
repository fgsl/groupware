<?php
namespace Fgsl\Groupware\Groupbase\Log\Formatter;
use Fgsl\Groupware\Groupbase\Log\Formatter;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Class Tinebase_Log_Formatter_Json
 *
 * @todo support timelog
 */
class Json extends Formatter
{
    /**
     * Formats data into a single line to be written by the writer.
     *
     * @param array $event event data
     * @return string formatted line to write to the log
     */
    public function format($event)
    {
        $event = array_merge([
            'log_id' => self::getPrefix(),
            'user' => self::getUsername(),
        ], $event);

        if (isset($event['message'])) {
            $event['message'] = str_replace($this->_search, $this->_replace, $event['message']);
        }

        return @json_encode($event,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION)
            . PHP_EOL;
    }
}
