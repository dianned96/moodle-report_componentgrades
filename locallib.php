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
 * Spreadsheet export report for assignments marked with advanced grading methods
 *
 * @package    report_componentgrades
 * @copyright  2014 Paul Nicholls updates 2018 by Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
define("HEADINGSROW", 4);

/**
 * Get all students given a course id
 *
 * @param integer $courseid
 * @return void
 */
function report_componentgrades_get_students($courseid) {
    global $DB;
    return $DB->get_records_sql('SELECT stu.id AS userid, stu.idnumber AS idnumber,
        stu.firstname, stu.lastname, stu.username AS student
        FROM {user} stu
        JOIN {user_enrolments} ue ON ue.userid = stu.id
        JOIN {enrol} enr ON ue.enrolid = enr.id
       WHERE enr.courseid = ?
    ORDER BY lastname ASC, firstname ASC, userid ASC', array($courseid));
}
/**
 * Add header text to report, name of course etc
 * TODO: Why is there method and methodname?
 *
 * @param MoodleExcelWorkbook $workbook
 * @param  MoodleExcelWorksheet $sheet
 * @param string $coursename
 * @param string $modname
 * @param string $method
 * @param string $methodname
 * @return void
 */
function report_componentgrades_add_header(MoodleExcelWorkbook  $workbook, MoodleExcelWorksheet  $sheet,
 $coursename, $modname, $method, $methodname) {
    // Course, assignment, marking guide / rubric names.
    $format = $workbook->add_format(array('size' => 18, 'bold' => 1));
    $sheet->write_string(0, 0, $coursename, $format);
    $sheet->set_row(0, 24, $format);
    $format = $workbook->add_format(array('size' => 16, 'bold' => 1));
    $sheet->write_string(1, 0, $modname, $format);
    $sheet->set_row(1, 21, $format);
    $methodname = ($method == 'rubric' ? 'Rubric: ' : 'Marking guide: ') . $methodname;
    $sheet->write_string(2, 0, $methodname, $format);
    $sheet->set_row(2, 21, $format);

    // Column headers - two rows for grouping.
    $format = $workbook->add_format(array('size' => 12, 'bold' => 1));
    $format2 = $workbook->add_format(array('bold' => 1));
    $sheet->write_string(HEADINGSROW, 0, get_string('student', 'report_componentgrades'), $format);
    $sheet->merge_cells(HEADINGSROW, 0, HEADINGSROW, 2, $format);
    $col = 0;
    $sheet->write_string(5, $col++, get_string('firstname', 'report_componentgrades'), $format2);
    $sheet->write_string(5, $col++, get_string('lastname', 'report_componentgrades'), $format2);
    $sheet->write_string(5, $col++, get_string('username', 'report_componentgrades'), $format2);
    if (get_config('report_componentgrades', 'showstudentid')) {
        $sheet->write_string(5, $col, get_string('studentid', 'report_componentgrades'), $format2);
        $col++;
    }
    $sheet->set_column(0, $col, 10); // Set column widths to 10.
    /* TODO returning an arbitrary number needs fixing */
    return $col;

}
/**
 * Column headers after data, e.g. who graded it and when
 *
 * @param MoodleExcelWorkbook $workbook
 * @param MoodleExcelWorksheet $sheet
 * @param integer $pos
 * @return void
 */
function report_componentgrades_finish_colheaders($workbook, $sheet, $pos) {
    // Grading info columns.
    $format = $workbook->add_format(array('size' => 12, 'bold' => 1));
    $format2 = $workbook->add_format(array('bold' => 1));
    $sheet->write_string(4, $pos, get_string('gradinginfo', 'report_componentgrades'), $format);
    $sheet->write_string(5, $pos, get_string('gradedby', 'report_componentgrades'), $format2);
    $sheet->set_column($pos, $pos++, 10); // Set column width to 10.
    $sheet->write_string(5, $pos, get_string('timegraded', 'report_componentgrades'), $format2);
    $sheet->set_column($pos, $pos, 17.5); // Set column width to 17.5.
    $sheet->merge_cells(4, $pos - 1, 4, $pos);

    $sheet->set_row(4, 15, $format);
    $sheet->set_row(5, null, $format2);

    // Merge header cells.
    $sheet->merge_cells(0, 0, 0, $pos);
    $sheet->merge_cells(1, 0, 1, $pos);
    $sheet->merge_cells(2, 0, 2, $pos);
}
/**
 * Get data for each student
 *
 * @param array $students
 * @param array $data array of objects
 * @return void
 */
function report_componentgrades_process_data(array $students, array $data) {
    foreach ($students as $student) {
        $student->data = array();
        foreach ($data as $key => $line) {
            if ($line->userid == $student->userid) {
                $student->data[$key] = $line;
                unset($data[$key]);
            }
        }
    }
    return $students;
}
/**
 * The actual student grading data
 *
 * @param MoodleExcelWorksheet $sheet
 * @param array $students
 * @param integer $gradinginfopos
 * @param string $method
 * @return void
 */
function report_componentgrades_add_data(MoodleExcelWorksheet $sheet, array $students, $gradinginfopos, $method) {
    // Actual data.
    $lastuser = 0;
    $row = 5;
    foreach ($students as $student) {
        $col = 0;
        $row++;
        $sheet->write_string($row, $col++, $student->firstname);
        $sheet->write_string($row, $col++, $student->lastname);
        $sheet->write_string($row, $col++, $student->student);
        if (get_config('report_componentgrades', 'showstudentid')) {
             $sheet->write_string($row, $col++, $student->idnumber);
        }

        foreach ($student->data as $line) {
            if (is_numeric($line->score)) {
                $sheet->write_number($row, $col++, $line->score);
            } else {
                /* if BTEC 0=N and 1=Y */
                $sheet->write_string($row, $col++, $line->score);
            }
            if ($method == 'rubric') {
                // Only rubrics have a "definition".
                $sheet->write_string($row, $col++, $line->definition);
            }
            $sheet->write_string($row, $col++, $line->remark);
            if ($col === $gradinginfopos) {
                if ($method == 'btec') {
                    /* Add the overall assignment grade converted to R,P,M,D
                     * and the feedback given for the overal assignment
                     */
                    $sheet->set_column($col, $col, 12);
                    $sheet->write_string($row, $col++, $line->grade);
                    /*add the per-criteria feedback */
                    $sheet->set_column($col, $col, 50);
                    $sheet->write_string($row, $col++, $line->commenttext);
                }
                $sheet->set_column($col, $col, 15);
                $sheet->write_string($row, $col++, $line->grader);
                $sheet->set_column($col, $col, 35);
                $sheet->write_string($row, $col, userdate($line->modified));
            }
        }
    }
}
