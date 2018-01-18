<?php
// SHOW TABLE OF CONFIGURED SHAZAM OPTIONS
/** @var \Stanford\Shazam\Shazam $module */

$module->loadConfig();

use \REDCap;

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
			$module->renderEditorTabs($field_name, $instrument);
			require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

			?>
			<script>
                Shazam.su = <?php echo SUPER_USER; ?>;
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
			print "Unkown action";
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
