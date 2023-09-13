<?php


require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/graph/locallib.php');
require_once($CFG->libdir.'/filelib.php');
require_once("$CFG->libdir/resourcelib.php");
global $CFG, $DB;



$courseid=$_GET["courseid"];
echo $courseid;
$metadati=$DB->get_records("graph",array("course"=>$courseid));
var_dump($metadati);
foreach( $metadati as $m){
	
	echo $m["name"];
	
}
	



?>