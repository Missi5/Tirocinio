<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package mod_graph
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * List of features supported in graph module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function graph_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function graph_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function graph_get_view_actions() {
    return array('view','view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function graph_get_post_actions() {
    return array('update', 'add');
}

/**
 * Add graph instance.
 * @param stdClass $data
 * @param mod_graph_mod_form $mform
 * @return int new graph instance id
 */
function graph_add_instance($data, $mform = null) {
    global $CFG, $DB, $COURSE;
    require_once("$CFG->libdir/resourcelib.php");
   

    $cmid = $data->coursemodule;

    $data->timemodified = time();
    $displayoptions = array();
    if ($data->display == RESOURCELIB_DISPLAY_POPUP) {
        $displayoptions['popupwidth']  = $data->popupwidth;
        $displayoptions['popupheight'] = $data->popupheight;
    }
    $displayoptions['printheading'] = $data->printheading;
    $displayoptions['printintro']   = $data->printintro;
    $displayoptions['printlastmodified'] = $data->printlastmodified;
    $data->displayoptions = serialize($displayoptions);

    if ($mform) {
        $data->content       = $data->graph['text'];
        $data->contentformat = $data->graph['format'];
    }
	$prerequisiti=array();
	$stringaPre="";
	
    //gestione prerequisiti
	if($data->gruppopre != null){

	foreach(array_keys($data->gruppopre) as $p){
		if($data->gruppopre[$p]== 1){
            
			array_push($prerequisiti,$p);
			$stringaPre=$stringaPre.$p.";";
        
		}
	}
    }

	$data->prerequisiti = $stringaPre;

    if($data->aq != ''){
        $data->name = $data->aq;
    }

	$data->contentformat=1;
    $data->id = $DB->insert_record('graph', $data);

    //Aggiungi una categoria alla banca dati delle domande
    $identificativi = $DB->get_fieldset_select("question_categories", 'id', null);//tutte le chiavi della tabella delle categorie delle domande
    if(empty($identificativi)){
                $nuovoId = 1;
            }
            else{
                $idMax = max($identificativi);
                $nuovoId = $idMax+1; //il nuovo id della riga da aggiungere 
            }

    $time = new DateTime("now", core_date::get_user_timezone_object());
    $timestamp = $time->getTimestamp();
    $stamp = "localhost+".$timestamp;
    $cor = $COURSE->id;
    $context=$DB->get_field("context",'id',array('contextlevel'=>50,'instanceid'=>$cor));
    //$context= $DB->get_field("context", 'id', array('contextlevel'=>50, 'instanceid'=>$cor));
    var_dump($context);   
    $ins = (object)array('id' => $nuovoId, 'name' => $data->name, 'contextid' => $context, 'info' => '', 'infoformat' => 0, 'stamp' => $stamp , 'parent' => '', 'sortorder' => 999, 'idnumber' => null);
    $DB->insert_record('question_categories', $ins);

    // we need to use context now, so we need to make sure all needed info is already in db
    $DB->set_field('course_modules', 'instance', $data->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    if ($mform and !empty($data->graph['itemid'])) {
        $draftitemid = $data->graph['itemid'];
        $data->content = file_save_draft_area_files($draftitemid, $context->id, 'mod_graph', 'content', 0, graph_get_editor_options($context), $data->content);
        $DB->update_record('graph', $data);
    }


    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'graph', $data->id, $completiontimeexpected);



    return $data->id;
    }

/**
 * Update graph instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function graph_update_instance($data, $mform) {
    global $CFG, $DB;
	
	
    require_once("$CFG->libdir/resourcelib.php");

    $cmid        = $data->coursemodule;
    $draftitemid = $data->graph['itemid'];

    $data->timemodified = time();
    $data->id           = $data->instance;
    $data->revision++;

    $displayoptions = array();
    if ($data->display == RESOURCELIB_DISPLAY_POPUP) {
        $displayoptions['popupwidth']  = $data->popupwidth;
        $displayoptions['popupheight'] = $data->popupheight;
    }
    $displayoptions['printheading'] = $data->printheading;
    $displayoptions['printintro']   = $data->printintro;
    $displayoptions['printlastmodified'] = $data->printlastmodified;
    $data->displayoptions = serialize($displayoptions);

    $data->content       = $data->graph['text'];
    $data->contentformat = $data->graph['format'];
	$prerequisiti=array();
	$stringaPre="";
	//gestione prerequisiti
	//var_dump($data);
	foreach(array_keys($data->gruppopre) as $p){
		if($data->gruppopre[$p]==1){
           
			array_push($prerequisiti,$p);
			$stringaPre=$stringaPre.$p.";";
        
		}
	}

	$data->prerequisiti = $stringaPre;
	$data->contentformat=1;
	
	if($data->aq!="")
		$data->name = $data->aq;
	
    $DB->update_record('graph', $data);

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $data->content = file_save_draft_area_files($draftitemid, $context->id, 'mod_graph', 'content', 0, graph_get_editor_options($context), $data->content);
        $DB->update_record('graph', $data);
    }

 
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'graph', $data->id, $completiontimeexpected);

    return true;
}

/**
 * Delete graph instance.
 * @param int $id
 * @return bool true
 */
function graph_delete_instance($id){
    global $DB;

    if (!$line = $DB->get_record('graph', array('id'=>$id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('graph', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'graph', $id, null);

    // note: all context files are deleted automatically

    $DB->delete_records('graph', array('id'=>$line->id));
    

    return true; 
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info Info to customise main graph display
 */
function graph_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if (!$graph = $DB->get_record('graph', array('id'=>$coursemodule->instance),
            'id, name, display, displayoptions, intro, introformat')) {
        return NULL;
    }

    $info = new cached_cm_info();
    $info->name = $graph->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('graph', $graph, $coursemodule->id, false);
    }

    if ($graph->display != RESOURCELIB_DISPLAY_POPUP) {
        return $info;
    }

    $fullurl = "$CFG->wwwroot/mod/graph/view.php?id=$coursemodule->id&amp;inpopup=1";
    $options = empty($graph->displayoptions) ? array() : unserialize($graph->displayoptions);
    $width  = empty($options['popupwidth'])  ? 620 : $options['popupwidth'];
    $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
    $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
    $info->onclick = "window.open('$fullurl', '', '$wh'); return false;";

    return $info;
}


/**
 * Lists all browsable file areas
 *
 * @package  mod_graph
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function graph_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['content'] = get_string('content', 'graph');
    return $areas;
}

/**
 * File browsing support for graph module content area.
 *
 * @package  mod_graph
 * @category files
 * @param stdClass $browser file browser instance
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function graph_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
        // students can not peak here!
        return null;
    }

    $fs = get_file_storage();

    if ($filearea === 'content') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_graph', 'content', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_graph', 'content', 0);
            } else {
                // not found
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/graph/locallib.php");
        return new graph_content_file_info($browser, $context, $storedfile, $urlbase, $areas[$filearea], true, true, true, false);
    }

    // note: graph_intro handled in file_browser automatically

    return null;
}

/**
 * Serves the graph files.
 *
 * @package  mod_graph
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function graph_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    if (!has_capability('mod/graph:view', $context)) {
        return false;
    }

    if ($filearea !== 'content') {
        // intro is handled automatically in pluginfile.php
        return false;
    }

    // $arg could be revision number or index.html
    $arg = array_shift($args);
    if ($arg == 'index.html' || $arg == 'index.htm') {
        // serve graph content
        $filename = $arg;

        if (!$graph = $DB->get_record('graph', array('id'=>$cm->instance), '*', MUST_EXIST)) {
            return false;
        }

        // We need to rewrite the pluginfile URLs so the media filters can work.
        $content = file_rewrite_pluginfile_urls($graph->content, 'webservice/pluginfile.php', $context->id, 'mod_graph', 'content',
                                                $graph->revision);
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->overflowdiv = true;
        $formatoptions->context = $context;
        $content = format_text($content, $graph->contentformat, $formatoptions);

        // Remove @@PLUGINFILE@@/.
        $options = array('reverse' => true);
        $content = file_rewrite_pluginfile_urls($content, 'webservice/pluginfile.php', $context->id, 'mod_graph', 'content',
                                                $graph->revision, $options);
        $content = str_replace('@@PLUGINFILE@@/', '', $content);

        send_file($content, $filename, 0, 0, true, true);
    } else {
        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_graph/$filearea/0/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            $graph = $DB->get_record('graph', array('id'=>$cm->instance), 'id, legacyfiles', MUST_EXIST);
            if ($graph->legacyfiles != RESOURCELIB_LEGACYFILES_ACTIVE) {
                return false;
            }
            if (!$file = resourcelib_try_file_migration('/'.$relativepath, $cm->id, $cm->course, 'mod_graph', 'content', 0)) {
                return false;
            }
            //file migrate - update flag
            $graph->legacyfileslast = time();
            $DB->update_record('graph', $graph);
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);
    }
}

/**
 * Return a list of graph types
 * @param string $graphtype current graph type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function graph_page_type_list($graphtype, $parentcontext, $currentcontext) {
    $module_graphtype = array('mod-graph-*'=>get_string('graph-mod-page-x', 'graph'));
    return $module_graphtype;
}

/**
 * Export graph resource contents
 *
 * @return array of file content
 */
function graph_export_contents($cm, $baseurl) {
    global $CFG, $DB;
    $contents = array();
    $context = context_module::instance($cm->id);

    $graph = $DB->get_record('graph', array('id'=>$cm->instance), '*', MUST_EXIST);

    // graph contents
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_graph', 'content', 0, 'sortorder DESC, id ASC', false);
    foreach ($files as $fileinfo) {
        $file = array();
        $file['type']         = 'file';
        $file['filename']     = $fileinfo->get_filename();
        $file['filepath']     = $fileinfo->get_filepath();
        $file['filesize']     = $fileinfo->get_filesize();
        $file['fileurl']      = file_encode_url("$CFG->wwwroot/" . $baseurl, '/'.$context->id.'/mod_graph/content/'.$graph->revision.$fileinfo->get_filepath().$fileinfo->get_filename(), true);
        $file['timecreated']  = $fileinfo->get_timecreated();
        $file['timemodified'] = $fileinfo->get_timemodified();
        $file['sortorder']    = $fileinfo->get_sortorder();
        $file['userid']       = $fileinfo->get_userid();
        $file['author']       = $fileinfo->get_author();
        $file['license']      = $fileinfo->get_license();
        $file['mimetype']     = $fileinfo->get_mimetype();
        $file['isexternalfile'] = $fileinfo->is_external_file();
        if ($file['isexternalfile']) {
            $file['repositorytype'] = $fileinfo->get_repository_type();
        }
        $contents[] = $file;
    }

    // graph html conent
    $filename = 'index.html';
    $graphfile = array();
    $graphfile['type']         = 'file';
    $graphfile['filename']     = $filename;
    $graphfile['filepath']     = '/';
    $graphfile['filesize']     = 0;
    $graphfile['fileurl']      = file_encode_url("$CFG->wwwroot/" . $baseurl, '/'.$context->id.'/mod_graph/content/' . $filename, true);
    $graphfile['timecreated']  = null;
    $graphfile['timemodified'] = $graph->timemodified;
    // make this file as main file
    $graphfile['sortorder']    = 1;
    $graphfile['userid']       = null;
    $graphfile['author']       = null;
    $graphfile['license']      = null;
    $contents[] = $graphfile;

    return $contents;
}

/**
 * Register the ability to handle drag and drop file uploads
 * @return array containing details of the files / types the mod can handle
 */
function graph_dndupload_register() {
    return array('types' => array(
                     array('identifier' => 'text/html', 'message' => get_string('creategraph', 'graph')),
                     array('identifier' => 'text', 'message' => get_string('creategraph', 'graph'))
                 ));
}

/**
 * Handle a file that has been uploaded
 * @param object $uploadinfo details of the file / content that has been uploaded
 * @return int instance id of the newly created mod
 */
function graph_dndupload_handle($uploadinfo) {
    // Gather the required info.
    $data = new stdClass();
    $data->course = $uploadinfo->course->id;

    if($data->radioarray['nessuno'] == 1){
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        }
        
    $data->name = $uploadinfo->displayname;
    $data->intro = '<p>'.$uploadinfo->displayname.'</p>';
    $data->introformat = FORMAT_HTML;
    if ($uploadinfo->type == 'text/html') {
        $data->contentformat = FORMAT_HTML;
        $data->content = clean_param($uploadinfo->content, PARAM_CLEANHTML);
    } else {
        $data->contentformat = FORMAT_PLAIN;
        $data->content = clean_param($uploadinfo->content, PARAM_TEXT);
    }
    $data->coursemodule = $uploadinfo->coursemodule;

    // Set the display options to the site defaults.
    $config = get_config('graph');
    $data->display = $config->display;
    $data->popupheight = $config->popupheight;
    $data->popupwidth = $config->popupwidth;
    $data->printheading = $config->printheading;
    $data->printintro = $config->printintro;
    $data->printlastmodified = $config->printlastmodified;

    return graph_add_instance($data, null);
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $graph       graph object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function graph_view($graph, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $graph->id
    );

    $event = \mod_graph\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('graph', $graph);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function graph_check_updates_since(cm_info $cm, $from, $filter = array()) {
    $updates = course_check_module_updates_since($cm, $from, array('content'), $filter);
    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_graph_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory, $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['graph'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/graph/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Given an array with a file path, it returns the itemid and the filepath for the defined filearea.
 *
 * @param  string $filearea The filearea.
 * @param  array  $args The path (the part after the filearea and before the filename).
 * @return array The itemid and the filepath inside the $args path, for the defined filearea.
 */
function mod_graph_get_path_from_pluginfile(string $filearea, array $args) : array {
    // graph never has an itemid (the number represents the revision but it's not stored in database).
    array_shift($args);

    // Get the filepath.
    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    return [
        'itemid' => 0,
        'filepath' => $filepath,
    ];
}
