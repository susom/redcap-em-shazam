<?php
namespace Stanford\Shazam;

if (!class_exists('Util')) require_once('classes/Util.php');

use \REDCap as REDCap;

// require_once ("classes/ActionTagHelper.php");


class Shazam extends \ExternalModules\AbstractExternalModule
{
    public $config = array();
    public $shazam_instruments = array(); // An array with key = instrument and values = shazam fields
    public $available_descriptive_fields = array();
    
    // A wrapper for logging
    public static function sLog() {
        if (self::isDev()) {
            $args = func_get_args();
            call_user_func_array('\Stanford\Shazam\Util::log', $args);
        }
    }

    public function __construct()
    {
        self::sLog("Constructing SHAZAM");
        parent::__construct();

        // If we are in a 'project' setting, then load the config
        // global $project_id;
        // if ($project_id) $this->loadConfig();
    }

    /**
     * Currently the shazam config is stored as an array with keys equal to field_names
     * and then an array of properties for status, html, css, javascript, date-saved.
     * @return array|mixed
     */
    public function loadConfig()
    {
        // Load the config
        $this->config = json_decode($this->getProjectSetting('shazam-config'), true);
        self::sLog("Config Loaded");
        //self::log($this->config, "LOADED CONFIG");

        // Set instruments too
        $this->setShazamInstruments();
    }

    /**
     * Save the config back to the external modules settings table
     */
    public function saveConfig() {
        //TODO: Add backup of configs...

        $this->config['last_modified'] = date('Y-m-d H:i:s');
        $this->config['last_modified_by'] = USERID;
        //self::log(json_encode($this->config), "DEBUG", "Saving this!");
        $this->setProjectSetting('shazam-config', json_encode($this->config));
        self::sLog("Config Saved");
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
            // Skip invalid fields
            if (!isset($Proj->metadata[$field_name])) {
                if (!in_array($field_name, array('last_modified','last_modified_by'))) {
                    self::sLog("Shazam field $field_name is not present in this project!");
                }
               continue;
            }

            // Skip inactive shazam configurations
            if ($detail['status'] == 0) {
                self::sLog("Skipping $field_name - inactive");
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
        self::sLog("Setting Shazam Instruments");
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
            self::sLog("Calling hook_every_page_top on " . PAGE);
            $instrument = $_GET['page'];

            // Apparently the config usn't loaded?  TODO: TEST THIS.
            $this->loadConfig();

            // Skip if this instrument doesn't have any shazam fields
            if (!isset($this->shazam_instruments[$instrument])) {
                self::sLog("$instrument not used");
                return;
            }

            //self::log($this);
            self::sLog("PAGE: " . PAGE);
            self::sLog("INSTRUMENT: ". $instrument);

            $jsUrl = $this->getUrl('js/shazam.js');
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

        self::sLog("Evaluating ShazamIt for $instrument");

        // Determine if any of the current shazam-enabled fields are on the current instrument
        $this->loadConfig();

        if(isset($this->shazam_instruments[$instrument])) {



            // We are active!
			self::sLog("Shazam active fields:", $this->shazam_instruments[$instrument]);

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
			self::sLog($auth_meth, $is_above_843);

			$js_url = ($auth_meth === "shibboleth" && $is_above_843)  ? $this->getUrl("js/shazam.js", true, true) : $this->getUrl("js/shazam.js");

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
                <style type='text/css'>#form {opacity: 0;}</style>

            <?php

        } else {
            // No shazam here
			self::sLog( "Nothing happening on this instrument $instrument");
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
	        $html .= "<a class='dropdown-item' data-field-name='$field_name' href='#'>[$field_name] $label</a>";
        }
        $html .= "<div class='dropdown-divider'></div>";
		$html .= "<div class='dropdown-header shazam-descriptive'>Create a new descriptive field for it to appear here</div>";
        return $html;
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
		    if (in_array($field_name, array('last_modified','last_modified_by'))) continue;

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
                ($status == 0 ? 'Inactive' : 'Active'),
                self::getActionButton($status)
            );
        }
        $table = self::renderTable($id, $header, $data);
		return $table;
	}


	private static function getActionButton($status) {
        $html = '
    		<div class="btn-group">
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> Action <span class="caret"></span>
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
                </div>
            </div>';
        return $html;
    }


    public function getExampleConfig() {
        $file = $this->getModulePath() . "assets/ShazamExample_Instrument.json";
        if (file_exists($file)) {
            self::sLog("$file FOUND");
            return json_decode(file_get_contents($file),true);
        } else {
            self::sLog("Unable to find $file");
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
