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
 * graph external API
 *
 * @package    mod_graph
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * graph external functions
 *
 * @package    mod_graph
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_graph_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_graph_parameters() {
        return new external_function_parameters(
            array(
                'graphid' => new external_value(PARAM_INT, 'graph instance id')
            )
        );
    }

    /**
     * Simulate the graph/view.php web interface graph: trigger events, completion, etc...
     *
     * @param int $graphid the graph instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_graph($graphid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/graph/lib.php");

        $params = self::validate_parameters(self::view_graph_parameters(),
                                            array(
                                                'graphid' => $graphid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $graph = $DB->get_record('graph', array('id' => $params['graphid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($graph, 'graph');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/graph:view', $context);

        // Call the graph/lib API.
        graph_view($graph, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_graph_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_graphs_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_graphs_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of graphs in a provided list of courses.
     * If no list is provided all graphs that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and graphs
     * @since Moodle 3.3
     */
    public static function get_graphs_by_courses($courseids = array()) {

        $warnings = array();
        $returnedgraphs = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_graphs_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the graphs in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $graphs = get_all_instances_in_courses("graph", $courses);
            foreach ($graphs as $graph) {
                $context = context_module::instance($graph->coursemodule);
                // Entry to return.
                $graph->name = external_format_string($graph->name, $context->id);

                $options = array('noclean' => true);
                list($graph->intro, $graph->introformat) =
                    external_format_text($graph->intro, $graph->introformat, $context->id, 'mod_graph', 'intro', null, $options);
                $graph->introfiles = external_util::get_area_files($context->id, 'mod_graph', 'intro', false, false);

                $options = array('noclean' => true);
                list($graph->content, $graph->contentformat) = external_format_text($graph->content, $graph->contentformat,
                                                                $context->id, 'mod_graph', 'content', $graph->revision, $options);
                $graph->contentfiles = external_util::get_area_files($context->id, 'mod_graph', 'content');

                $returnedgraphs[] = $graph;
            }
        }

        $result = array(
            'graphs' => $returnedgraphs,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_graphs_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_graphs_by_courses_returns() {
        return new external_single_structure(
            array(
                'graphs' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Module id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_INT, 'Course id'),
                            'name' => new external_value(PARAM_RAW, 'graph name'),
                            'intro' => new external_value(PARAM_RAW, 'Summary'),
                            'introformat' => new external_format_value('intro', 'Summary format'),
                            'introfiles' => new external_files('Files in the introduction text'),
                            'content' => new external_value(PARAM_RAW, 'graph content'),
                            'contentformat' => new external_format_value('content', 'Content format'),
                            'contentfiles' => new external_files('Files in the content'),
                            'legacyfiles' => new external_value(PARAM_INT, 'Legacy files flag'),
                            'legacyfileslast' => new external_value(PARAM_INT, 'Legacy files last control flag'),
                            'display' => new external_value(PARAM_INT, 'How to display the graph'),
                            'displayoptions' => new external_value(PARAM_RAW, 'Display options (width, height)'),
                            'revision' => new external_value(PARAM_INT, 'Incremented when after each file changes, to avoid cache'),
                            'timemodified' => new external_value(PARAM_INT, 'Last time the graph was modified'),
                            'section' => new external_value(PARAM_INT, 'Course section id'),
                            'visible' => new external_value(PARAM_INT, 'Module visibility'),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode'),
                            'groupingid' => new external_value(PARAM_INT, 'Grouping id'),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
