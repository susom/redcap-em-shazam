<?php
namespace Stanford\Shazam;
/** @var \Stanford\Shazam\Shazam $this */


/**
 * This trait can be use with External Modules to enable the Stanford emLogger module
 *
 * Version 1.2
 *
 * To use this on your project:
 * 1) simply add this trait to your EM project root
 * 2) change the NAMESPACE of this file to match that of your EM
 * 3) Before you declare your EM class, insert the command:  include "emLoggerTrait.php";
 * 4) Inside your class, before your first function, insert: use emLoggerTrait;
 * 5) (optional) Modify your config.json to include these two optional debug settings

(OPTIONAL) INSERT THESE OPTIONS INTO THE CONFIG.JSON

"system-settings": [

{
"key": "enable-system-debug-logging",
"name": "<b>Enable Debug Logging (system-wide)</b><i>(optional)</i> Requires installation and configuration of emLogger",
"required": false,
"type": "checkbox"
},

],

"project-settings": [

{
"key": "enable-project-debug-logging",
"name": "<b>Enable Debug Logging</b><br><i>(optional)</i> Requires installation and configuration of emLogger",
"required": false,
"type": "checkbox"
},

],

 */

trait emLoggerTrait
{
    private $emLoggerEnabled = null;    // Cache logger enabled
    private $emLoggerDebug   = null;    // Cache debug mode


    /**
     * Obtain an instance of emLogger or false if not installed / active
     * @return bool|mixed
     */
    function emLoggerInstance() {

        // This is the first time used, see if it is enabled on server
        if (is_null($this->emLoggerEnabled)) {
            $versions = \ExternalModules\ExternalModules::getEnabledModules();
            $this->emLoggerEnabled = isset($versions['em_logger']);
        }

        // Return instance if enabled
        if ($this->emLoggerEnabled) {
            // Try to return the instance of emLogger (which is cached by the EM framework)
            try {
                return \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            } catch (\Exception $e) {
                // Unable to initialize em_logger
                error_log("Exception caught - unable to initialize emLogger in " . __NAMESPACE__ . "/" . __FILE__ . "!");
                $this->emLoggerEnabled = false;
            }
        }
        return false;
    }


    /**
     * Determine if we are in debug mode either on system or project level and cache
     * @return bool
     */
    function emLoggerDebugMode() {
        // Set if debug mode once on the first log call
        if (is_null($this->emLoggerDebug)) {
            $systemDebug         = $this->getSystemSetting('enable-system-debug-logging');
            $projectDebug        = !empty($_GET['pid']) && $this->getProjectSetting('enable-project-debug-logging');
            $this->emLoggerDebug = $systemDebug || $projectDebug;
        }
        return $this->emLoggerDebug;
    }


    /**
     * Do the logging
     * The reason we broke it into three functions was to reduce complexity with backtrace and the calling function
     */
    function emLog() {
        if ($emLogger = $this->emLoggerInstance()) $emLogger->emLog($this->PREFIX, func_get_args(), "INFO");
    }


    /**
     * Wrapper for logging an error
     */
    function emError() {
        if ($emLogger = $this->emLoggerInstance()) $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }


    /**
     * Wrapper for logging debug statements
     */
    function emDebug() {
        if ( $this->emLoggerDebugMode() && ($emLogger = $this->emLoggerInstance()) ) $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
    }

}