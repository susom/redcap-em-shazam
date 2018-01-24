<?php
// SHOW TABLE OF CONFIGURED SHAZAM OPTIONS
/** @var \Stanford\Shazam\Shazam $module */

$module->loadConfig();

use \REDCap;
use \Browser;

// Handle posts back to this script
if ($_SERVER['REQUEST_METHOD']=='POST') {
	$module::log($_POST, "DEBUG", "INCOMING POST");

	$field_name = !empty($_POST['field_name'])  ? $_POST['field_name']  : "";
	$action     = !empty($_POST['action'])      ? $_POST['action']      : "";

	// Get some instrument information as well:
    global $Proj;
    $instrument         = !empty($field_name) ? $Proj->metadata[$field_name]['form_name'] : "";
    $instrument_fields  = !empty($instrument) ? $Proj->forms[$instrument]['fields'] : "";

	if ($action == "create") {
        // Create a default entry for the new field and save it.  Then render the edit page.
        // print "Create $field_name";
        $module->addDefaultField($field_name);
        $action = "edit";
    }

    switch ($action) {
        case "edit":
			// Verify edit is valid and then render the 'edit' page.
			//print "Edit $field_name";
			// Plugin::log($module->config, "DEBUG", "this->config");
			// Render the editor
			require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

            $b = new Browser();
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
                    <img style="max-width: 90%;" src="<?php echo $module->getUrl("assets/example_table.png"); ?>"/>
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
                        <div class="editor2" id='editor_html' data-mode="html"></div>
                    </div>
                    <div class="tab-pane fade" id="panel_editor_css">
                        <div class="editor2" id='editor_css' data-mode="css"></div>
                    </div>
                    <div class="tab-pane fade" id="panel_editor_js">
                        <div class="editor2" id='editor_js' data-mode="javascript"></div>
                    </div>
                </div>
            </div>
            <div class="shazam-edit-buttons">
                <button class="btn btn-primary" name="save">SAVE (<?php echo $cmdKey; ?>-S)</button>
                <button class="btn btn-primary" name="save_and_close">SAVE AND CLOSE</button>
                <button class="btn btn-default" name="cancel">CANCEL</button>
            </div>

            <script src="<?php echo $module->getUrl('js/ace/ace.js'); ?>"></script>
            <script src="<?php echo $module->getUrl('js/config.js'); ?>"></script>
            <script src="<?php echo $module->getUrl('js/ace/ext-language_tools.js'); ?>"></script>

            <style>
                .shazam-editor { border-bottom: 1px solid #ddd; margin-bottom: 10px;}
            </style>

            <?php
			// $module->renderEditorTabs($field_name, $instrument);

			require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

			?>
			<script>
                Shazam.su = <?php echo SUPER_USER; ?>;
                Shazam.config = <?php echo json_encode($module->config[$field_name]); ?>;
                Shazam.fields = <?php print json_encode(array_keys($instrument_fields)); ?>;
                Shazam.prepareEditors();
            </script>
            <?php
			exit();
			break;
        case "save":
			// SAVE A CONFIGURATION
			// THIS IS AN AJAX METHOD
			$params = $_POST['params'];

			// If not a superuser, then you can't change the javascript...  Also prevent someone from trying to inject a change into the post
            if (SUPER_USER !== 1) {
                // Is there an existing js
                if (!empty($module->config[$field_name]['javascript'])) {
                    // $module::log("js is not empty - keeping original value since not a superuser");
                    $params['javascript'] = $module->config[$field_name]['javascript'];
                } else {
                    // $module::log("js IS empty");
                    $params['javascript'] = '';
                }
            }

            $update = array(
                $field_name => $params
            );

            // Add or update config
			$new_config = empty($module->config) ? $update : array_merge($module->config, $update);
            $module::log($update, "DEBUG", "UPDATE");
            $module::log($new_config, "DEBUG", "new_config");
			$module->config = $new_config;
			$return = $module->saveConfig();
			// $return = $module->setProjectSetting('shazam-config', json_encode($new_config));
			// Plugin::log($return, "DEBUG", "STAUTS of setProjectSetting");
			header('Content-Type: application/json');
			print json_encode($return);
			exit();
            break;
        case "delete":
			unset($module->config[$field_name]);
			$module->saveConfig();
			break;
        case "activate":
			$module->config[$field_name]['status'] = 1;
			$module->saveConfig();
			break;
        case "deactivate":
			$module->config[$field_name]['status'] = 0;
			$module->saveConfig();
			break;
        default:
			print "Unknown action";
	}
}



# Render Table Page
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<style>
    #shazam td { vertical-align: middle; }
    .shazam-descriptive { font-style: italic; font-size: smaller; margin: 0 20px; white-space: nowrap;}
</style>

<h3>Shazam Fields</h3>

<p>
    This is a table of descriptive fields in your project that have been configured with Shazam's powers.  Click on the add button below to get started.  More instructions are available when you are actually editing the Shazam configuration.
</p>

<div class="shazam-table">
	<?php echo $module->getShazamTable(); ?>
    <div class="btn-group">
        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></span>
            Add Shazam Field <span class="caret"></span>
        </button>
        <ul class="dropdown-menu add-shazam">
            <li><span style="padding-left: 10px;">Select an unsused descriptive field below:</span></li>
            <li class="divider"></li>
            <?php echo $module->getAddShazamOptions() ?>
        </ul>
    </div>
</div>

<form id="action-form" name="action" class="hidden" method="POST">
</form>

<script src="<?php echo $module->getUrl('js/config.js'); ?>"></script>
<script>
    Shazam.prepareTable();
</script>
