<?php
namespace Stanford\Shazam;

use \REDCap as REDCap;
require_once "emLoggerTrait.php";

class Shazam extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    public $config = array();
    public $shazam_instruments = array(); // An array with key = instrument and values = shazam fields
    public $available_descriptive_fields = array();

    public $backup_configs = array();

    const BACKUP_COPIES = 10;
    const KEY_CONFIG_METADATA = '__MISC__';
    const KEY_SHAZAM_MIRROR_VIZ_FIX = "smvf";

    //// A wrapper for logging
    //public static function sLog() {
    //    if (self::isDev()) {
    //        $args = func_get_args();
    //        call_user_func_array('\Stanford\Shazam\Util::log', $args);
    //    }
    //}

    public function __construct()
    {
        parent::__construct();
        $this->disableUserBasedSettingPermissions();
        // If we are in a 'project' setting, then load the config
        // global $project_id;
        // if ($project_id) $this->loadConfig();
    }

    /**
     * Currently the shazam config is stored as an array with keys equal to field_names
     * and then an array of properties for status, html, css, javascript.  To add future expandibility of Shazam
     * I made a new 'metadata' key that can be added to either the parent array for things like 'date saved'
     * or to each field's array to store version and other information.
     */
    public function loadConfig()
    {
        // Load the config
        $this->config = json_decode($this->getProjectSetting('shazam-config'), true);
        $this->emDebug("Config Loaded");
//        $this->emDebug($this->config, "LOADED CONFIG");

        // Migrate over 'shazam-mirror-visibility' in HTML to data-shazam-mirror-visibility
        $this->migrateShazamMirrorViz();

        // Set shazam instruments too
        $this->setShazamInstruments();

        // Get backup instruments
        $this->backup_configs = json_decode($this->getProjectSetting('shazam-config-backups'), true);
    }


    /**
     * Migrate any HTML using the attribute 'shazam-mirror-visibility' to 'data-shazam-mirror-visibility'
     * https://github.com/susom/redcap-em-shazam/issues/13
     */
    public function migrateShazamMirrorViz() {
        $config = $this->config;

        // Use a regex to replace shazam-mirror-visibility...
        $re = '/(?<!data-)shazam-mirror-visibility/m';
        $updates_made = false;
        foreach ($config as $field => $props) {
            // Only fix those that need fixin'
            if (isset($props['html']) && !isset($props[self::KEY_CONFIG_METADATA][self::KEY_SHAZAM_MIRROR_VIZ_FIX])) {
                $this->emDebug($field,$props);

                $this->emDebug("$field has HTML but doesn't have [" . self::KEY_CONFIG_METADATA . "][" . self::KEY_SHAZAM_MIRROR_VIZ_FIX . "]");

                // Fix any shazam-mirror-viz
                $count = 0;
                $html = preg_replace($re, "data-shazam-mirror-visibility", $props['html'], -1, $count);
                if ($count > 0) {
                    // We did a replacement
                    $this->emDebug("We did a SMV replacement: " . $count);
                    $props['html'] = $html;
                }

                // Create a misc array if not already there
                if (!isset($props[self::KEY_CONFIG_METADATA])) $props[self::KEY_CONFIG_METADATA] = [];

                // Record the fact that we applied this fix
                $props[self::KEY_CONFIG_METADATA][self::KEY_SHAZAM_MIRROR_VIZ_FIX] = $count;
                $config[$field] = $props;

                $this->emDebug("New Props", $props, $config[$field]);
                $this->emDebug("Updating " . self::KEY_SHAZAM_MIRROR_VIZ_FIX . " for $field with $count shazam-mirror-visibility changes");

                $updates_made = true;
            } else {
                // Already fixed
            }
        }

        // If we actually made a change here, let's re-save
        if ($updates_made) {
            // We fixed some stuff
            $this->emDebug("Updating config with SMVF");
            $this->saveConfig($config, "migration of shazam mirror visibility changes");
        }
    }


    /**
     * Save the config back to the external modules settings table
     * also saves latext BACKUP_COPIES to backup-config
     */
    public function saveConfig($newConfig, $saveComment = "") {

        // Set and save new Config
        $this->config = $newConfig;

        $this->emDebug("This is the newConfig", $newConfig, $this->config);

        // Add the metadata tag if it doesn't exist
        if (!isset($this->config[self::KEY_CONFIG_METADATA])) $this->config[self::KEY_CONFIG_METADATA] = [];

        // Save the _MISC_ info to the current version
        $ts = time();
        $this->config[self::KEY_CONFIG_METADATA]['last_modified']       = date('Y-m-d H:i:s', $ts);
        $this->config[self::KEY_CONFIG_METADATA]['last_modified_by']    = USERID;
        $this->config[self::KEY_CONFIG_METADATA]['save_comment']        = $saveComment;

        //self::log(json_encode($this->config), "DEBUG", "Saving this!");
        $this->setProjectSetting('shazam-config', json_encode($this->config));
        $this->emDebug("Config Saved");


        // Load backups and save current config as new backup
        $backup_configs = $this->backup_configs;
        if (empty($backup_configs)) $backup_configs = [];

        // Add current config to backups
        $backup_configs[$ts] = $this->config;
        krsort($backup_configs);

        $this->emDebug("Sorted Keys Before", array_keys($backup_configs));

        // Only keep so many copies of backups
        $this->backup_configs = array_slice($backup_configs,0,self::BACKUP_COPIES,true);

        $this->emDebug("Sorted Keys After", array_keys($this->backup_configs));

        $this->setProjectSetting('shazam-config-backups', json_encode($this->backup_configs));
        $this->emDebug(count($this->backup_configs) . " backup configs saved");
    }


    // Only display the Shazam Setup link if the user has design rights
    public function redcap_module_link_check_display($project_id, $link, $record = null, $instrument = null, $instance = null, $page = null) {
        $result = false;

        // Evaluate all links for now - in the future you might have different rules for different links...
        if (@$link['name'] == "Shazam Setup") {

            // Show link if design or superuser
            global $userid;
            $rights = REDCap::getUserRights($userid);
            if (@$rights[$userid]['design'] == 1 || SUPER_USER ) $result = $link;
        }

        return $result;
    }


    /**
     * Looks through the config to create an array of instruments => fields that are active for Shazam
     */
    function setShazamInstruments() {
        global $Proj;
        $this->shazam_instruments = array();

        foreach ($this->config as $field_name => $detail) {
            if ($field_name == self::KEY_CONFIG_METADATA) continue;

            // Skip invalid fields
            if (!isset($Proj->metadata[$field_name])) {
                if (!in_array($field_name, array('last_modified','last_modified_by'))) {
                    $this->emDebug("Shazam field $field_name is not present in this project!");
                }
               continue;
            }

            // Skip inactive shazam configurations
            if ($detail['status'] == 0) {
                $this->emDebug("Skipping $field_name - inactive");
                continue;
            }

            // Get the instrument for the field
            $instrument = $Proj->metadata[$field_name]['form_name'];

            // Initialize the instrument array
            if (!isset($this->shazam_instruments[$instrument])) $this->shazam_instruments[$instrument] = array();

            // Append to object array for later lookup
            array_push($this->shazam_instruments[$instrument], $field_name);
        }

        // self::log("Setting Shazam Instruments", $this->shazam_instruments, "DEBUG");
        $this->emDebug("Setting Shazam Instruments");
    }


    function  redcap_survey_page_top($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
		// self::log("Calling from hook_survey_page_top");
        $this->shazamIt($project_id,$instrument, true);
	}


    function  redcap_data_entry_form_top($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        // self::log("Calling from hook_data_entry_form_top");
        $this->shazamIt($project_id,$instrument);
    }


    function redcap_every_page_top($project_id = null) {

        // ONLY DO STUFF FOR THE ONLINE DESIGNER PAGE:
	    if (PAGE == "Design/online_designer.php") {
            $this->emDebug("Calling hook_every_page_top on " . PAGE);
            $instrument = $_GET['page'];

            // Apparently the config usn't loaded?  TODO: TEST THIS.
            $this->loadConfig();

            // Skip if this instrument doesn't have any shazam fields
            if (!isset($this->shazam_instruments[$instrument])) {
                $this->emDebug("$instrument not used");
                return;
            }

            //self::log($this);
            $this->emDebug("PAGE: " . PAGE);
            $this->emDebug("INSTRUMENT: ". $instrument);

            $jsUrl = $this->getUrl('js/shazam.js', false, true);
            // Highlight shazam fields on the page
            ?>
            <script src='<?php echo $jsUrl; ?>'></script>
            <script>
                if (typeof Shazam === "undefined") {
                    alert("This page uses an external module called 'Shazam' but due to a configuration error " +
                        "the module is not loading the required javascript library correctly.\n\n" +
                        "Please notify the project administrator."
                    );
                } else {
                    Shazam.fields = <?php echo json_encode($this->shazam_instruments[$instrument]); ?>;
                    Shazam.isDev = <?php echo self::isDev(); ?>;
                    $(document).ready(function () {
                        Shazam.highlightFields();
                    });
                }
            </script>
            <style>
                .shazam-label {
                    z-index: 1000;
                    float: right;
                    padding: 3px;
                    margin-right: 10px;
                }

                .shazam-label:hover {
                    cursor: pointer;
                }

            </style>
            <?php
        }
	}


    /**
     * When a new field is being converted to shazam - prepopulate the editors with some helper text
     * @param $field_name
     */
	public function addDefaultField($field_name) {
	    $this->config[$field_name] = array(
			'html'      => "<!-- Add your Shazam HTML Here -->\n",
			'css'       => "/* Customize your Shazam with CSS Below */\n",
			'javascript'=> "$(document).ready(function(){\n\t//Add javascript here...\n\t\n});",
	        'status'    => 1
        );
    }


    /**
     * See if we are going to do shazam on a survey or data entry form
     *
     * @param $project_id
     * @param $instrument
     */
    function shazamIt($project_id, $instrument, $isSurvey = false) {

        $this->emDebug("Evaluating ShazamIt for $instrument");

        // Determine if any of the current shazam-enabled fields are on the current instrument
        $this->loadConfig();

        if(isset($this->shazam_instruments[$instrument])) {



            // We are active!
			$this->emDebug("Shazam active fields:", $this->shazam_instruments[$instrument]);

			// Build the data to pass through to the javascript engine
			$shazamParams = array();
			foreach($this->shazam_instruments[$instrument] as $field_name) {
			    $params = $this->config[$field_name];

			    $html = isset($params['html']) ? $params['html'] : "<div>MISSING SHAZAM HTML</div>";
			    $shazamParams[] = array(
                    'field_name' => $field_name,
                    'html' => filter_tags($html)
                );

                if (!empty($params['css'])) {
                    print "<style type='text/css'>" . $params['css'] . "</style>";
                }
                if (!empty($params['javascript'])) {
                    print "<script type='text/javascript'>" . $params['javascript'] . "</script>";
                }
            }
            // self::log($shazamParams, $shazamParams);


            global $auth_meth;
			$is_above_843 = REDCap::versionCompare(REDCAP_VERSION, '8.4.3') >= 0;
			$this->emDebug($auth_meth, $is_above_843);

			$js_url = $is_above_843  ? $this->getUrl("js/shazam.js", true, true) : $this->getUrl("js/shazam.js");
			//$js_url = $this->getUrl("js/shazam.js", true, true);

            ?>
                <script type='text/javascript' src="<?php echo $js_url ?>"></script>
                <script type='text/javascript'>
                    if (typeof Shazam === "undefined") {
                        // There has been an error loading the js file.
                        alert("This page uses an external module called 'Shazam' but due to a configuration error " +
                            "the module is not loading the required javascript library correctly.\n\n" +
                            "Please notify the project administrator."
                        );
                    } else {
                        $(document).ready(function () {
                            Shazam.params       = <?php print json_encode($shazamParams); ?>;
                            Shazam.isDev        = <?php echo self::isDev(); ?>;
                            Shazam.displayIcons = <?php print json_encode($this->getProjectSetting("shazam-display-icons")); ?>;
                            Shazam.isSurvey     = <?php print json_encode($isSurvey); ?>;
                            Shazam.Transform();
                        });
                    }
                </script>
                <style type='text/css'>
                    #form {opacity: 0;}
                    .shazam-vanished { z-index: -9999; }
                </style>

            <?php

        } else {
            // No shazam here
			$this->emDebug( "Nothing happening on this instrument $instrument");
        }

    }


	// Get all descriptive fields except for those already used
    function getAvailableDescriptiveFields() {
	    global $Proj;
	    $fields = array();
	    foreach ($Proj->metadata as $field_name => $field_details) {
	        if ($field_details['element_type'] == 'descriptive' && !isset($this->config[$field_name])) {
	            $fields[$field_name] = $field_details['element_label'];
            }
        }
        $this->available_descriptive_fields = $fields;
    }

	/**
     * Generate the dropdown elements of unused descriptive fields required for the add-new shazam menu
	 * @return string
	 */
    public function getAddShazamOptions() {
	    if (empty($this->available_descriptive_fields)) $this->getAvailableDescriptiveFields();

	    $html = '';
	    foreach ($this->available_descriptive_fields as $field_name => $label) {
	        $html .= "<a class='dropdown-item' data-field-name='$field_name' href='#'>[$field_name] " . strip_tags2($label) . "</a>";
        }
        $html .= "<div class='dropdown-divider'></div>";
		$html .= "<div class='dropdown-header shazam-descriptive'>Create a new descriptive field for it to appear here</div>";
        return $html;
    }


    /**
     * Gets a list by timestamp of the previously saved config entries
     * @return string
     */
    public function getPreviousShazamOptions() {
        $html = '';

//        $this->emDebug("Backup configs", $this->backup_configs);
        foreach ($this->backup_configs as $ts => $config) {
            $html .= "<a class='dropdown-item' data-ts='$ts' href='#'>" . $this->getBackupName($config) . "</a>";
        };
        return $html;
    }

    public function getBackupName($config, $include_last_modified = true) {
        $last_modified = $config[self::KEY_CONFIG_METADATA]['last_modified'];
        $last_modified_by = $config[self::KEY_CONFIG_METADATA]['last_modified_by'];
        $comment = empty($config[self::KEY_CONFIG_METADATA]['save_comment']) ? "No comment" : $config[self::KEY_CONFIG_METADATA]['save_comment'];

        $result = "[$last_modified] " . strip_tags2($comment);
        if ($include_last_modified) $result .= " ($last_modified_by)";
        return $result;
    }

    public function restoreVersion($ts) {
        $config = $this->backup_configs[$ts];
        if (empty($config)) {
            $this->emError("Empty Config!");
            return false;
        }

        $this->saveConfig($config, "Restored " . $this->getBackupName($config, false));
    }


	/**
     * Generate the html for building the shazam table (could be moved to javascript in the future?)
	 * @return string
	 */
    public function getShazamTable() {
        global $Proj;

	    $id = "shazam";
		$header = array('Field Name', 'Instrument', 'Status', 'Action');
		$data = array();
		foreach ($this->config as $field_name => $details) {
		    if (in_array($field_name, array(self::KEY_CONFIG_METADATA, 'last_modified','last_modified_by'))) continue;

            $instrument = $Proj->metadata[$field_name]['form_name'];
            if (empty($instrument)) {
                if ($field_name == "shaz_ex_desc_field") {
                    // offer to download test instrument
                    global $project_id;
                    $designer_url = APP_PATH_WEBROOT . "Design/online_designer.php?pid=" . $project_id;
                    $instrument = "<span class='label label-danger'>FIELD MISSING</span> " .
                        "To test, upload this <a href='" . $this->getUrl("assets/ShazamExample_Instrument.zip") . "'>" .
                        "<button class='btn btn-xs btn-default'>Example Instrument.zip</button></a> using the <a href='" .
                        $designer_url . "'><button class='btn btn-xs btn-success'>online designer</button></a>";
                } else {
                    $instrument = "<span class='label label-danger'>MISSING</span>";
                }
            }

            $status = $details['status'];
            $data[] = array(
                $field_name,
                $instrument,
                ($status == 0 ? '<h6><span class="badge badge-secondary">Inactive</span></h6>' : '<h6><span class="badge badge-success">Active</span></h6>'),
                self::getActionButton($status)
            );
        }
        $table = self::renderTable($id, $header, $data);
		return $table;
	}


	private static function getActionButton($status) {
        $html = '
    		<div class="btn-group">
                <button type="button" class="btn btn-primaryrc btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> Action <span class="caret"></span>
                </button>
                <div class="dropdown-menu actions">
                    <a class="dropdown-item" data-action="edit" href="#">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a class="dropdown-item" data-action="delete" href="#">
                        <i class="fas fa-trash-alt"></i> Delete
                    </a>';
        if ($status == 0) {
            $html .= '
                    <a class="dropdown-item" data-action="activate" href="#">
                        <i class="fas fa-toggle-on"></i> Activate
                    </a>';
        } else {
            $html .= '
                    <a class="dropdown-item" data-action="deactivate" href="#">
                        <i class="fas fa-toggle-off"></i> Deactivate
                    </a>';
        }
        $html .= '
                    <a class="dropdown-item" data-action="copy-clipboard" href="#">
                        <i class="fas fa-trash-alt"></i> Copy to Clipboard
                    </a>';
	    $html .= '        
                </div>
            </div>';
        return $html;
    }


    public function getExampleConfig() {
        $file = $this->getModulePath() . "assets/ShazamExample_Instrument.json";
        if (file_exists($file)) {
            $this->emDebug("$file FOUND");
            return json_decode(file_get_contents($file),true);
        } else {
            $this->emDebug("Unable to find $file");
            return false;
        }
    }



    private static function renderTable($id, $header=array(), $table_data) {
		//Render table
		$grid =
			'<table id="'.$id.'" class="table table-striped table-bordered table-condensed" cellspacing="0" width="100%">';

		$grid .= self::renderHeaderRow($header, 'thead');
//        $grid .= self::renderHeaderRow($header, 'tfoot');
		$grid .= self::renderTableRows($table_data);
		$grid .= '</table>';

		return $grid;
	}

	private static function renderHeaderRow($header = array(), $tag) {
        $row = '<'.$tag.'><tr>';
        foreach ($header as $col_key => $this_col) {
        $row .=  '<th>'.$this_col.'</th>';
        }
        $row .= '</tr></'.$tag.'>';
        return $row;
    }

    private static function renderTableRows($row_data=array()) {
    	$rows = '';
        foreach ($row_data as $row_key=>$this_row) {
            $rows .= '<tr>';
            foreach ($this_row as $col_key=>$this_col) {
                $rows .= '<td>'.$this_col.'</td>';
    		}
            $rows .= '</tr>';
        }
        return $rows;
    }


    # defines criteria to judge someone is on a development box or not
    public static function isDev()
    {
        $is_localhost  = ( @$_SERVER['HTTP_HOST'] == 'localhost' );
        $is_dev_server = ( isset($GLOBALS['is_development_server']) && $GLOBALS['is_development_server'] == '1' );
        $is_dev = ( $is_localhost || $is_dev_server ) ? 1 : 0;
        return $is_dev;
    }

}
