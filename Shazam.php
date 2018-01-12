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
        $args = func_get_args();
        call_user_func_array('\Stanford\Shazam\Util::log', $args);
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
                self::log("Shazam field $field_name is not present in this project!");
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
                    'html' => $html
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
                        Shazam.params = <?php print json_encode($shazamParams); ?>;
                        console.log("Shazam Params", Shazam.params);
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


    function renderEditorTabs($field_name, $instrument) {
		$b = new \Browser();
		$cmdKey = ( $b->getPlatform() == "Apple" ? "&#8984;" : "Ctrl" );

		?>
        <h3>Shazam Editor</h3>
        <table class="table table-bordered ">
            <tr><th class="info col-md-2"><strong>Instrument:</strong></th><td><b><?php echo $instrument ?></b></td></tr>
            <tr><th class="info col-md-2"><strong>Field:</strong></th><td><b><?php echo $field_name ?></b></td></tr>
        </table>

        <button class="btn btn-success btn-sm" data-toggle="collapse" data-target="#shazam-example">Toggle Instructions</button>
        <div id="shazam-example" class="collapse panel panel-body" style="border:2px solid #ccc; border-radius: 3px;">

                <p>Use the tabs below to create and edit your Shazam HTML block.  This block of HTML will be inserted into the instrument where
                    <?php echo $field_name ?> would normally be displayed.  As you are editing, you can save your progress with
                    <span class="label label-primary"><?php echo $cmdKey?>-S</span>.  It may be best to open up your <?php echo $instrument ?> form and refresh
                    after each save to see how you are progressing.</p>
                <p>
                    Most commonly, you will create a HTML table and insert REDCap fields into this table as illustrated below:
                </p>

                <div id="shazam-example-code" style="border: 1px solid #ccc; max-width: 95%;">
                    <table class='fy_summary'>
                        <tr>
                            <th></th>
                            <th>2012</th>
                            <th>2013</th>
                            <th>2014</th>
                            <th>2015</th>
                            <th>2016</th>
                        </tr>
                        <tr>
                            <th>Federal Grants</th>
                            <td class='shazam'>fed_grants_fy12</td>
                            <td class='shazam'>fed_grants_fy13</td>
                            <td class='shazam'>fed_grants_fy14</td>
                            <td class='shazam'>fed_grants_fy15</td>
                            <td class='shazam'>fed_grants_fy16</td>
                        </tr>
                        <tr>
                            <th class='shazam'>nf_grants:label</th> <!-- This will map the LABEL to the field nf_grants -->
                            <td class='shazam'>nf_grants_fy12</td>
                            <td class='shazam'>nf_grants_fy13</td>
                            <td class='shazam'>nf_grants_fy14</td>
                            <td class='shazam'>nf_grants_fy15</td>
                            <td class='shazam'>nf_grants_fy16</td>
                        </tr>
                        <tr><th>Research Agreements/Contracts</th>
                            <td class='shazam'>rsch_contract_fy12</td>
                            <td class='shazam'>rsch_contract_fy13</td>
                            <td class='shazam'>rsch_contract_fy14</td>
                            <td class='shazam'>rsch_contract_fy15</td>
                            <td class='shazam'>rsch_contract_fy16</td>
                        </tr>
                        <tr shazam-mirror-visibility="clinical_trials"> <!-- This will make this entire TR only visible when the field 'clinical_trials' is visible -->
                            <th>Clinical Trials</th>
                            <td class='shazam'>ct_fy12</td>
                            <td class='shazam'>ct_fy13</td>
                            <td class='shazam'>ct_fy14</td>
                            <td class='shazam'>ct_fy15</td>
                            <td class='shazam'>ct_fy16</td>
                        </tr>
                    </table>
                </div>
                <p>
                    And this is what it looks like:
                    <img style="max-width: 90%;" src="<?php echo $this->getUrl("assets/example_table.png"); ?>"/>
                </p>
                <p>
                    Notice how each element with a <code>class='shazam'</code> contains the name of a field as the text of the element.  This will move
                    that redcap field into the element with the class.
                </p>
                <p>
                    Also notice how the last row contains <code>shazam-mirror-visibility</code>.  This is a way to make an element in your html mimic
                    the branching-logic visiblity of a redcap element.  In this case, there is a field called 'clinical_trials'.  If it is visible, so will
                    the row in the table called Clinical Trials.
                </p>
                <p>
                    Lastly, notice how you can also MAP the label from another redcap question.  Look at the row header for "Non-Federal Grants" isn't spelled out,
                    but rather comes from the label of another question.  This can reduce the complexity of your Shazam HTML by relying more on the data dictionary.
                </p>
                <p>
                    Don't forget you can also add custom CSS to your page and even javascript to override non-css attributes on a page.
                </p>
            </div>
        <hr>
        <div class="shazam-editor" data-field-name="<?php echo $field_name ?>">

            <ul class="nav nav-tabs">
                <li class="active"><a data-toggle="tab" href="#panel_editor_html">HTML</a></li>
                <li><a data-toggle="tab"  href="#panel_editor_css">CSS</a></li>
                <li><a data-toggle="tab"  href="#panel_editor_js">JS</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade in active" id="panel_editor_html" >
                    <div class="editor2" id='editor_html' data-mode="html"><?php echo $this->config[$field_name]['html']; ?></div>
                </div>
                <div class="tab-pane fade" id="panel_editor_css">
                    <div class="editor2" id='editor_css' data-mode="css"><?php echo $this->config[$field_name]['css']; ?></div>
                </div>
                <div class="tab-pane fade" id="panel_editor_js">
                    <div class="editor2" id='editor_js' data-mode="javascript"><?php echo $this->config[$field_name]['javascript']; ?></div>
                </div>
            </div>
        </div>
        <div class="shazam-edit-buttons">
            <button class="btn btn-primary" name="save">SAVE (<?php echo $cmdKey; ?>-S)</button>
            <button class="btn btn-primary" name="save_and_close">SAVE AND CLOSE</button>
            <button class="btn btn-default" name="cancel">CANCEL</button>
        </div>

        <script src="<?php echo $this->getUrl('js/ace/ace.js'); ?>"></script>
        <script src="<?php echo $this->getUrl('js/config.js'); ?>"></script>
        <script src="<?php echo $this->getUrl('js/ace/ext-language_tools.js'); ?>"></script>

        <style>
            .shazam-editor { border-bottom: 1px solid #ddd; margin-bottom: 10px;}
        </style>

		<?php

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
}
