<?php
/**
 * @author Whalestack <service@whalestack.com>
 * @copyright 2022 Whalestack
 * @license https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Whalestack\Sdk;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WsLoggingService
{

    /**
     * Writes to a log file and prepends current time stamp
     *
     * @param $message
     * @param $log
     */
    public static function write($message, $log = 1)
    {
        if ($log == 1) {
            $logFile = _PS_ROOT_DIR_ . '/var/logs/whalestack.log';

            $type = file_exists($logFile) ? 'a' : 'w';
            $file = fopen($logFile, $type);
            fputs($file, date('r', time()) . ' ' . $message . PHP_EOL);
            fclose($file);
        }
    }
}
