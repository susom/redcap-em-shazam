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
    public static function log() {
        if (self::isDev()) {
            $args = func_get_args();
            call_user_func_array('\Stanford\Shazam\Util::log', $args);
        }
    }

    public function __construct()
    {
        self::log("Constructing SHAZAM");
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
        self::log("Config Loaded");
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
        self::log("Config Saved");
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
                    self::log("Shazam field $field_name is not present in this project!");
                }
               continue;
            }

            // Skip inactive shazam configurations
            if ($detail['status'] == 0) {
                self::log("Skipping $field_name - inactive");
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
        self::log("Setting Shazam Instruments");
    }


    function  hook_survey_page_top($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
		// self::log("Calling from hook_survey_page_top");
        $this->shazamIt($project_id,$instrument);
	}


    function  hook_data_entry_form_top($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        // self::log("Calling from hook_data_entry_form_top");
        $this->shazamIt($project_id,$instrument);
    }


    function hook_every_page_top($project_id = null) {


        // ONLY DO STUFF FOR THE ONLINE DESIGNER PAGE:
	    if (PAGE == "Design/online_designer.php") {
            self::log("Calling hook_every_page_top on " . PAGE);
            $instrument = $_GET['page'];

            // Apparently the config usn't loaded?  TODO: TEST THIS.
            $this->loadConfig();

            // Skip if this instrument doesn't have any shazam fields
            if (!isset($this->shazam_instruments[$instrument])) {
                self::log("$instrument not used");
                return;
            }

            //self::log($this);
            self::log("PAGE: " . PAGE);
            self::log("INSTRUMENT: ". $instrument);

            //return;

            // Highlight shazam fields on the page
            ?>
            <script src='<?php echo $this->getUrl('js/shazam.js'); ?>'></script>
            <script>
                Shazam.fields = <?php echo json_encode($this->shazam_instruments[$instrument]); ?>;
                Shazam.isDev = <?php echo self::isDev(); ?>;
                $(document).ready(function () {
                    Shazam.highlightFields();
                });
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
    function shazamIt($project_id, $instrument) {

        self::log("Evaluating ShazamIt for $instrument");

        // Determine if any of the current shazam-enabled fields are on the current instrument
        $this->loadConfig();

        if(isset($this->shazam_instruments[$instrument])) {
            // We are active!
			self::log("Shazam active fields:", $this->shazam_instruments[$instrument]);

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
            
            ?>
                <script type='text/javascript' src="<?php print $this->getUrl("js/shazam.js", true, true) ?>"></script>
                <script type='text/javascript'>
                    $(document).ready(function () {
                        Shazam.params       = <?php print json_encode($shazamParams); ?>;
                        Shazam.isDev        = <?php echo self::isDev(); ?>;
                        Shazam.displayIcons = <?php print json_encode($this->getProjectSetting("shazam-display-icons")); ?>;
                        Shazam.Transform();
                    });
                </script>
            <?php

        } else {
            // No shazam here
			self::log( "Nothing happening on this instrument $instrument");
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
	        $html .= "<li><a data-field-name='$field_name' href='#'>[$field_name] $label</a></li>";
        }
        $html .= "<li role='separator' class='divider'></li>";
		$html .= "<li><span class='shazam-descriptive'>Create a new descriptive field for it to appear here</span></li>";
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
                <ul class="dropdown-menu actions">
                    <li><a data-action="edit" href="#"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span> Edit</a></li>
                    <li><a data-action="delete" href="#"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Delete</a></li>';
        if ($status == 0) {
            $html .= '
                    <li><a data-action="activate" href="#"><span class="glyphicon glyphicon-chevron-up" aria-hidden="true"></span> Activate</a></li>';
        } else {
            $html .= '
                    <li><a data-action="deactivate" href="#"><span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span> Deactivate</a></li>';
        }
	    $html .= '        
            </div>';
        return $html;
    }


    public function getExampleConfig() {
        $file = $this->getModulePath() . "assets/ShazamExample_Instrument.json";
        if (file_exists($file)) {
            self::log("$file FOUND");
            return json_decode(file_get_contents($file),true);
        } else {
            self::log("Unable to find $file");
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
