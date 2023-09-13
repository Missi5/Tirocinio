<script>

</script>
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
 * graph module version information
 *
 * @package mod_graph
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/graph/lib.php');
require_once($CFG->dirroot.'/mod/graph/locallib.php');
require_once($CFG->libdir.'/completionlib.php');


$id      = optional_param('id', 0, PARAM_INT); // Course Module ID
$p       = optional_param('p', 0, PARAM_INT);  // graph instance ID
$inpopup = optional_param('inpopup', 0, PARAM_BOOL);

if ($p) {
    if (!$graph = $DB->get_record('graph', array('id'=>$p))) {
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('graph', $graph->id, $graph->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('graph', $id)) {
        print_error('invalidcoursemodule');
    }
    $graph = $DB->get_record('graph', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/graph:view', $context);

// Completion and trigger events.
graph_view($graph, $course, $cm, $context);

$PAGE->set_url('/mod/graph/view.php', array('id' => $cm->id));

$options = empty($graph->displayoptions) ? array() : unserialize($graph->displayoptions);

if ($inpopup and $graph->display == RESOURCELIB_DISPLAY_POPUP) {
    $PAGE->set_pagelayout('popup');
    $PAGE->set_title($course->shortname.': '.$graph->name);
    $PAGE->set_heading($course->fullname);
} else {
    $PAGE->set_title($course->shortname.': '.$graph->name);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_activity_record($graph);
}
echo $OUTPUT->header();
if (!isset($options['printheading']) || !empty($options['printheading'])) {
    echo $OUTPUT->heading(format_string($graph->name), 2);
}

if (!empty($options['printintro'])) {
    if (trim(strip_tags($graph->intro))) {
        echo $OUTPUT->box_start('mod_introbox', 'pageintro');
        echo format_module_intro('graph', $graph, $cm->id);
        echo $OUTPUT->box_end();
    }
}
$content="Ecco il grafo del corso, in rosso evidenziato il nodo che hai selezionato";
//$content = file_rewrite_pluginfile_urls($graph->content, 'pluginfile.php', $context->id, 'mod_graph', 'content', $graph->revision);
$formatoptions = new stdClass;
$formatoptions->noclean = true;
$formatoptions->overflowdiv = true;
$formatoptions->context = $context;
//$content = format_text($content, $graph->contentformat, $formatoptions);
//var_dump($content);

$attuale=$DB->get_record("course_modules",array("id"=>$_GET["id"]));
$moduloGrafo=$attuale->module;

$metadati=$DB->get_records("graph",array("course"=>$COURSE->id));
$nodes=array();
$edges=array();
$nodi="";
$cont=1;




echo '

<html lang="en">
  <head>
    <title>Network</title>
    <script
      type="text/javascript"
      src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"
    ></script>
    <style type="text/css">
      #mynetwork {
        width: 600px;
        height: 400px;
        border: 1px solid lightgray;
      }
    </style>
  </head>
  <body>
    <div id="mynetwork"></div>
    <script type="text/javascript">
	
	var nodi =[];
	var archi=[];';
	


	
	
	echo '
      // create an array with nodes
      var nodes = new vis.DataSet([';
	  foreach( $metadati as $m){
	
	
	if($attuale->instance==$m->id){
		if(!in_array($m->name, $nodes)){
		echo "{id: \"".$m->name."\", label:\"".$m->name."\", shape: \"diamond\", color: \"#FB7E81\"},";
		array_push($nodes, $m->name);
	}
	$attuale->nome=$m->name;
	

	}
	else{
	if(!in_array($m->name, $nodes)){
		$line = $DB->get_record('course_modules', array('module'=>$moduloGrafo, 'instance'=>$m->id));
		 if ($line->deletioninprogress==0) {
			echo "{id: \"".$m->name."\", label:\"".$m->name."\"},";
			array_push($nodes, $m->name);
			}
		}
	}
	
	$cont++;
}
	  
	  echo ']);

      // create an array with edges
      var edges = new vis.DataSet([';
	 
	  foreach( $metadati as $m){
	$arc=array();
	$pre="";
	$pre=$m->prerequisiti;
	$prerequisiti=explode(";",$pre);
	
	foreach($prerequisiti as $p){
		if($p!=""){
			//$archi=$archi."{from: \"".$p."\", to:\"".$m->name."\"},";
			echo "{from: \"".$p."\", to:\"".$m->name."\", arrows: \"to\",},";
			array_push($arc,$p);
		}
	$edges[$m->name]=$arc;
	}
	
}
	
	
	  echo ']);




      // create a network
      var container = document.getElementById("mynetwork");
      var data = {
        nodes: nodes,
        edges: edges,
      };
      var options = {};
      var network = new vis.Network(container, data, options);
	  
    </script>
  </body>
</html>
</script>';
/*
foreach($metadati as $m){
	$visitati=array();
	$trovato=false;	
	array_push($visitati,$m->name);
	$fatti= array();
	$ciclo=array();
	array_push($fatti,$m->name);
	var_dump($m->name);
	while (count($visitati)>0 and !$trovato){
		
		$attuale=array_pop($visitati);
		array_push($fatti,$attuale);
		if(is_null($edges[$attuale])){
			array_pop($visitati);
				continue;
		}
		else
		$attuali=$edges[$attuale];
		
		
		
		foreach($attuali as $v){
			
			if(in_array($v,$fatti)){
				array_push($visitati,$v);
				
				$trovato=true;
				//continue;
			}
			/*
			if($v==$m->name){
				array_push($visitati,$v);
				$trovato=true;
			}
			
			else{
				array_push($visitati,$v);
				
			}
				
		}
		
	}
	
	 var_dump($visitati);
	}
	
	*/
	
	//var_dump($edges);
	
	
//disegnare qui il grafo


//grafo nuovo







/*

//var_dump($nodes);
//var_dump($edges);


$script='

';
*/
/*
$script='<script
      type="text/javascript"
      src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"
    ></script>
    <style type="text/css">
      #mynetwork {
        width: 600px;
        height: 400px;
        border: 1px solid lightgray;
      }
    </style>
  </head>
  <body>
    <div id="mynetwork"></div>
	<script type="text/javascript">
      // create an array with nodes
      var nodes = new vis.DataSet(nodi);

      // create an array with edges
      var edges = new vis.DataSet(archi);

      // create a network
      var container = document.getElementById("mynetwork");
      var data = {
        nodes: nodes,
        edges: edges,
      };
      var options = {};
      var network = new vis.Network(container, data, options);
    </script>';

	*/
	//echo $script;
//fine grafo nuovo





//
echo $OUTPUT->box($content, "generalbox center clearfix");

if (!isset($options['printlastmodified']) || !empty($options['printlastmodified'])) {
    $strlastmodified = get_string("lastmodified");
    echo html_writer::div("$strlastmodified: " . userdate($graph->timemodified), 'modified');
}

echo $OUTPUT->footer();
