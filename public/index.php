<?php
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Log\Log;

/**
 * this is the general file any request should be routed through
 *
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

$time_start = microtime(true);
$pid = getmypid();

require_once 'bootstrap.php';

Core::set(Core::STARTTIME, $time_start);

if (Core::isLogLevel(LogLevel::INFO)) {
    Core::getLogger()->info('index.php ('. __LINE__ . ')' . ' Start processing request ('
        . 'PID: ' . $pid . ')');
}

Core::dispatchRequest();
Log::logUsageAndMethod('index.php', $time_start, Core::get(Core::METHOD), $pid);