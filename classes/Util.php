<?php
/**
 * Created by PhpStorm.
 * User: andy123
 * Date: 12/6/17
 * Time: 5:27 PM
 */

namespace Stanford\Shazam;

class Util
{
    static $log_session_first = false;  // Adds a delimiter between each new logging session/page hit

    /**
     * A generic logging function
     */
    public static function log() {
        // Get the log path if configured
        if (!empty($GLOBALS['external_module_log_path'])) {
            $plugin_log_file = $GLOBALS['external_module_log_path'];
        }

        $args = func_get_args();
        $arg_count = count($args);
        // \Plugin::log($args, "$arg_count ARGS");
        $last_arg = strtoupper($args[$arg_count-1]);
        // \Plugin::log($last_arg, "LAST ARG");

        if(in_array($last_arg, array('INFO','DEBUG','ERROR'))) {
            $type = $last_arg;
            array_pop($args);
        } else {
            $type = "INFO";
        }

        // ADD TRACE FOR DEBUG
        if ($type == "DEBUG") {
            $trace = self::generateCallTrace();
            $trace = "\n\t\tTRACE:\n\t\t" . implode("\n\t\t", $trace);
        } else {
            $trace = "";
        }

        // DEBUG OTHER ARGUMENTS AS VARIABLES
        $vars = array();
        foreach ($args as $i => $arg) {
            $vars[] = self::generateVariableDebug($arg);
        }

        // ADD A DELIMITER BETWEEN EACH SESSION
        if (self::$log_session_first === false) {
            self::$log_session_first = true;
            global $project_id, $record;
            $header = "-------- " . date( 'Y-m-d H:i:s' ) . " --------";
            if (!empty($project_id)) $header .= " [PID:" . $project_id . "]";
            if (!empty($record)) $header .= " [RECORD:" . $record . "]";
            $header .= "\n";
        } else {
            $header = "";
        }

        // Output to plugin log if defined, else use error_log
        if (!empty($plugin_log_file)) {
            $result = file_put_contents(
                $plugin_log_file,
                $header .
                "[" . $type . "]\t" . implode("\n\t", $vars) .
                $trace . "\n"
                ,FILE_APPEND
            );

            if ($result === false) {
                // Output to error log since writing to the defined log file failed
                error_log("Error writing to log file: $plugin_log_file");
                error_log("\t" . implode("\n\t", $vars) . "\t" . implode("\n\t", $trace) );
            }
        }
    }

    private static function generateCallTrace()
    {
        $e = new \Exception();
        $trace = explode("\n", $e->getTraceAsString());
        // Take only the last three entries...
        // $trace = array_slice($trace,1,3);

        $length = count($trace);
        $result = array();
        for ($i = 0; $i < $length; $i++)
        {
            $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
        }
        return $result;
    }

    private static function generateVariableDebug($obj) {
        $parsed = print_r($obj,true);
        $parsed = str_replace("\n", "\n\t\t\t", trim($parsed));
        if (is_string($obj)) {
            $msg = "[str]:\t" . $obj;
        } elseif (is_array($obj)) {
            // $msg = "[arr]:\t" . print_r($obj,true);
            $msg = "[arr]:\t" . $parsed;
        } elseif (is_object($obj)) {
            // $msg = "[obj]:\t" . print_r($obj,true);
            $msg = "[obj]:\t" . $parsed;
        } elseif (is_numeric($obj)) {
            $msg = "[num]:\t" . $obj;
        } elseif (is_bool($obj)) {
            $msg = "[bool]:\t" . ($obj ? "true" : "false");
        } else {
            $msg = "[unk]:\t" . print_r($obj,true);
        }
        return $msg;
    }
}