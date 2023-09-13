
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
 * graph configuration form
 *
 * @package mod_graph
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/graph/locallib.php');
require_once($CFG->libdir.'/filelib.php');

class mod_graph_mod_form extends moodleform_mod {
    function definition() {
        global $CFG, $DB, $USER, $COURSE;
	
		//var_dump($_GET["update"]);
		$attuale=$DB->get_record("course_modules",array("id"=>$_GET["update"],"course"=>$COURSE->id));
		$vero=$DB->get_record("graph",array("id"=>$attuale->instance, "course"=>$COURSE->id));
		
        $mform = $this->_form;

        $config = get_config('graph');
		//$mform->addElement('header', 'general', 'sezioni del corso');
		//$mform->addElement('static', 'listaSezioni', '1 2 3');
        //-------------------------------------------------------
		
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', "conoscienza acquisita", array('size'=>'48'));
        $mform->addElement('text', 'threshold', "soglia", array('size'=>'48'));
        $mform->addRule('threshold', 'Numeric', 'numeric', null, 'client');
        

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        

		
		$module=$DB->get_record("modules",array("name"=>"graph"));
		
		$metadati=$DB->get_records("course_modules",array("course"=>$COURSE->id, "module"=>$module->id));
		
		
		//$mform->addElement('static', 'description', $vero->name,get_string('descriptionofexercise', 'exercise', $COURSE->students));
		$radioarray=array();
		$default="";
		
		
		foreach($metadati as $m){
			$nodo=$DB->get_record("graph",array("id"=>$m->instance, "course"=>$COURSE->id));
			if(!is_null($nodo->name)){
				if($nodo->id==$attuale->instance)
					$default=$nodo->name;
				    $line = $DB->get_record('course_modules', array('module'=>$m->module, 'instance'=>$nodo->id));
                    if($line->deletioninprogress==0){
				        $radioarray[] = $mform->createElement('radio', 'aq', '', $nodo->name, $nodo->name, null);
				}
			}
			
		}
		
		$radioarray[] = $mform->createElement('radio', 'aq', '','nessuno', '', null);
		$mform->addGroup($radioarray, 'radioar', 'oppure selezionane una da quelle già definite', array(' '), false);
		$mform->setDefault('aq', '');
		$typeitem = array();
		
       
         
		
		foreach ($metadati as $m) {
			$nodo=$DB->get_record("graph",array("id"=>$m->instance, "course"=>$COURSE->id));
            $line = $DB->get_record('course_modules', array('module'=>$m->module, 'instance'=>$nodo->id));
            if($line->deletioninprogress==0){
			  $ce = array();
			  array_push($ce,1);
			  if(!is_null($nodo)and $nodo-> id!= $attuale->instance){
			     $typeitem[] = &$mform->createElement('advcheckbox',$nodo->name, '', $nodo->name, array('name' => $nodo->name,'group'=>1), array(0,1));
                }
			}
			
		}
		
       /* if($radioarray[0] == 1){
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        }*/

		$mform->addGroup($typeitem, 'gruppopre','Scegli i prerequisiti');
		//$mform->setDefault('gruppopre[cono 9]', 1);

		$prere=explode(";",$vero->prerequisiti);
		
		foreach($prere as $e){
		
			if($e!="")
			$mform->setDefault('gruppopre['.$e.']', 1);
			
		
		}
		
        $this->standard_intro_elements();
		
		
		
		
		
		
		//$mform->addElement('text', 'prerequisiti', "prerequisiti");
		$mform->addElement('text', 'tempo', 'tempo');
		
		$mform->addRule('tempo', 'Numeric', 'numeric', null, 'client');
		
		
		//Learning styles
		$mform->addElement('header', 'general', 'Learning Styles');
		
		
		$mform->addElement('text', 'ls1', 'ls1');
		$mform->addRule('ls1', 'Numeric', 'numeric', null, 'client');

		$mform->addElement('text', 'ls2', 'ls2');
		$mform->addRule('ls2', 'Numeric', 'numeric', null, 'client');
		
		$mform->addElement('text', 'ls3', 'ls3');
		$mform->addRule('ls3', 'Numeric', 'numeric', null, 'client');
		
		$mform->addElement('text', 'ls4', 'ls4');
		$mform->addRule('ls4', 'Numeric', 'numeric', null, 'client');
		
		
		

		
        //-------------------------------------------------------
        //$mform->addElement('header', 'contentsection', get_string('contentheader', 'graph'));
        //$mform->addElement('editor', 'graph', get_string('content', 'graph'), null, graph_get_editor_options($this->context));
		//$mform->addRule('graph', get_string('required'), 'required', null, 'client');
        //-------------------------------------------------------
        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        if ($this->current->instance) {
            $options = resourcelib_get_displayoptions(explode(',', $config->displayoptions), $this->current->display);
        } else {
            $options = resourcelib_get_displayoptions(explode(',', $config->displayoptions));
        }
        if (count($options) == 1) {
            $mform->addElement('hidden', 'display');
            $mform->setType('display', PARAM_INT);
            reset($options);
            $mform->setDefault('display', key($options));
        } else {
            $mform->addElement('select', 'display', get_string('displayselect', 'graph'), $options);
            $mform->setDefault('display', $config->display);
        }

        if (array_key_exists(RESOURCELIB_DISPLAY_POPUP, $options)) {
            $mform->addElement('text', 'popupwidth', get_string('popupwidth', 'graph'), array('size'=>3));
            if (count($options) > 1) {
                $mform->hideIf('popupwidth', 'display', 'noteq', RESOURCELIB_DISPLAY_POPUP);
            }
            $mform->setType('popupwidth', PARAM_INT);
            $mform->setDefault('popupwidth', $config->popupwidth);

            $mform->addElement('text', 'popupheight', get_string('popupheight', 'graph'), array('size'=>3));
            if (count($options) > 1) {
                $mform->hideIf('popupheight', 'display', 'noteq', RESOURCELIB_DISPLAY_POPUP);
            }
            $mform->setType('popupheight', PARAM_INT);
            $mform->setDefault('popupheight', $config->popupheight);
        }

        $mform->addElement('advcheckbox', 'printheading', get_string('printheading', 'graph'));
        $mform->setDefault('printheading', $config->printheading);
        $mform->addElement('advcheckbox', 'printintro', get_string('printintro', 'graph'));
        $mform->setDefault('printintro', $config->printintro);
        $mform->addElement('advcheckbox', 'printlastmodified', get_string('printlastmodified', 'graph'));
        $mform->setDefault('printlastmodified', $config->printlastmodified);

        // add legacy files flag only if used
        if (isset($this->current->legacyfiles) and $this->current->legacyfiles != RESOURCELIB_LEGACYFILES_NO) {
            $options = array(RESOURCELIB_LEGACYFILES_DONE   => get_string('legacyfilesdone', 'graph'),
                             RESOURCELIB_LEGACYFILES_ACTIVE => get_string('legacyfilesactive', 'graph'));
            $mform->addElement('select', 'legacyfiles', get_string('legacyfiles', 'graph'), $options);
            $mform->setAdvanced('legacyfiles', 1);
        }
		
        //-------------------------------------------------------
        $this->standard_coursemodule_elements();
		
        //-------------------------------------------------------
        $this->add_action_buttons();

        //-------------------------------------------------------
        $mform->addElement('hidden', 'revision');
        $mform->setType('revision', PARAM_INT);
        $mform->setDefault('revision', 1);

    }

    /**
     * Enforce defaults here.
     *
     * @param array $defaultvalues Form defaults
     * @return void
     **/
    public function data_preprocessing(&$defaultvalues) {


        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('graph');
            $defaultvalues['graph']['format'] = $defaultvalues['contentformat'];
            $defaultvalues['graph']['text']   = file_prepare_draft_area($draftitemid, $this->context->id, 'mod_graph',
                    'content', 0, graph_get_editor_options($this->context), $defaultvalues['content']);
            $defaultvalues['graph']['itemid'] = $draftitemid;
        }
        if (!empty($defaultvalues['displayoptions'])) {
            $displayoptions = unserialize($defaultvalues['displayoptions']);
            if (isset($displayoptions['printintro'])) {
                $defaultvalues['printintro'] = $displayoptions['printintro'];
            }
            if (isset($displayoptions['printheading'])) {
                $defaultvalues['printheading'] = $displayoptions['printheading'];
            }
            if (isset($displayoptions['printlastmodified'])) {
                $defaultvalues['printlastmodified'] = $displayoptions['printlastmodified'];
            }
            if (!empty($displayoptions['popupwidth'])) {
                $defaultvalues['popupwidth'] = $displayoptions['popupwidth'];
            }
            if (!empty($displayoptions['popupheight'])) {
                $defaultvalues['popupheight'] = $displayoptions['popupheight'];
            }
        }
    }
}

?>
