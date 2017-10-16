<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 10/16/17
 * Time: 11:33 AM
 */

//include_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";


use \Stanford\Shazam\Shazam;



//$shazam = new \Stanford\Shazam\Shazam();
$shazam = new Shazam();
$shazam->setProjectSetting('shazam', '{"foo":"bar"}');
$project_settings = $shazam->getProjectSetting('shazam');

var_dump($project_settings);

if ($_SERVER['REQUEST_METHOD']=='POST') {

    $field_name = $_POST['field_name'];
    $pid = $_POST['pid'];
    $type = PLUGIN_NAME . " [$field_name]";
    $ls = new LogStore($pid, $type);

    // SAVE A CONFIGURATION
    if ($_POST['action'] == 'save') {
        $params = $_POST['params'];
        $ls->data = $params;
        $ls->save($field_name);
        header('Content-Type: application/json');
        print json_encode($ls->id);
        exit();
    }

    // LOAD A CONFIGURATION
    if ($_POST['action'] == 'load') {
        if($ls->load()) {
            $payload = array(
                'field_name' => $ls->note,
                'params' => $ls->data
            );
        } else {
            // Nothing saved - return default
            $payload = array(
                'field_name' => $field_name,
                'params' => array(
                    'html' => '<!-- html here -->',
                    'css' => '/* css here */',
                    'javascript' => '// javascript here'
                )
            );
        }
        header('Content-Type: application/json');
        print json_encode($payload);
        exit();
    }
    print "Unkown action";
    exit();
}



# Render Page
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$active_field_name = '';

//$result = \ExternalModules\ExternalModules::getAdditionalFieldChoices(array('type'=>'field-list'), $project_id);
//var_dump($result['choices']);

$fields = REDCap::getFieldNames();
//var_dump(print_r($fields, true));

/**
 * @param $id
 * @param $fields
 * @return string
 */
function displayOptions($id, $fields) {
    $options = getOptions($fields);
    $str = '<select id="'.$id.'">';
    foreach($options as $val) {
        $str .= $val;
    }
    $str .= '</select>';
    return $str;
}

/**
 * @param $name_var
 * @param $options
 * @param null $current_val
 * @return array
 */
function getOptions($options, $current_val = null) {
    foreach($options as $k => $v) {
        $instrument_options[] = "<option value='$k'" .
            ($k == $current_val ? " selected='selected'":"") .
            ">$v</option>";

    }
    return $instrument_options;
}

// Scan metadata to find any fields with Shazam
$metadata = $Proj->metadata;
$shazam_fields = array();
foreach ($metadata as $field_name => $data) {
    if ($matches = parseActionTags($data['misc'], TAG_NAME)) {
        $shazam_fields[$field_name] = $matches[TAG_NAME]['params'];
    }
}

// Create dropdown of field to edit
$shazam_options = array();
foreach ($shazam_fields as $field_name => $params) {
    $shazam_options[] = "<option value='$field_name'" .
        ( $field_name == $active_field_name ? " selected='selected'" : "" ) .
        ">$field_name</option>";
}

?>
<h3><?php echo PLUGIN_NAME ?> Configuration</h3>
<p>First, add '@SHAZAM2' to the annotation of a given field - after this, it will appear in the following dropdown.  Select the field
    you wish to edit and then modify the HTML or CSS you wish to add.  Press CTRL-S or CMD-S to save the Editor.  Refresh your form/survey
    to confirm you have the desired effects.</p>
<div>Select a shazam field to edit:</div>
<?php echo displayOptions("active_field_name", $fields)?>


<div id="tabs" class="extra-nav">
    <ul>
        <li id="panel_nav_html" data-div="panel_editor_html"><a href="#panel_editor_html">HTML</a></li>
        <li id="panel_nav_css" data-div="panel_editor_css"><a href="#panel_editor_css">CSS</a></li>
        <li id="panel_nav_js" data-div="panel_editor_js"><a href="#panel_editor_js">JS</a></li>
    </ul>
</div>

<div class='tab-container' id="panel_editor_html">
    <div class="editor2" id='editor_html'/>A whole bunch of stuff goes here!</div>
</div>
<div class='tab-container' id="panel_editor_css">
    <div class="editor2" id='editor_css'/>.foo {background-color: red;}</div>
</div>
<div class='tab-container' id="panel_editor_js">
    <div class="editor2" id='editor_js'/>$(document).ready(function() {});</div>
</div>

<hr>

<button id="saveBtn" type="submit" name="saveParams">SAVE</button>

<style>
    .extra-nav li {float: left;}
    .tab-container {display:none}
    /*.editor {position:relative;height:200px;margin-bottom:5px; margin-right:10px;}*/
    /*.editor2 {height:300px; width: 500px;}*/
    /*.source {display:none;}*/
</style>

<script src="js/ace.js" ></script>
<script src="js/config.js" ></script>

