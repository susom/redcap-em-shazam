<?php
namespace Stanford\Shazam;

class Shazam extends \ExternalModules\AbstractExternalModule
{

    public $project_id;

    function  hook_survey_page_top($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash = NULL,
                                   $response_id = NULL, $repeat_instance = 1) {

    }

    function  hook_data_entry_form_top($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {

    }

}
