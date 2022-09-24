<?php
/**
 * @author COINQVEST <service@coinqvest.com>
 * @copyright 2022 COINQVEST
 * @license https://www.apache.org/licenses/LICENSE-2.0
 */

namespace COINQVEST\Sdk;

class CQLoggingService
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
            $logFile = _PS_ROOT_DIR_ . '/var/logs/coinqvest.log';

            $type = file_exists($logFile) ? 'a' : 'w';
            $file = fopen($logFile, $type);
            fputs($file, date('r', time()) . ' ' . $message . PHP_EOL);
            fclose($file);
        }
    }
}
