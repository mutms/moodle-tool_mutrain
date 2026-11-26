<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon
// phpcs:disable moodle.Files.RequireLogin.Missing

/**
 * User completions for given framework.
 *
 * @package    tool_mutrain
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $USER */

require('../../../../config.php');

$frameworkid = required_param('frameworkid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$currenturl = new core\url('/admin/tool/mutrain/my/completions.php', ['frameworkid' => $frameworkid]);
if ($userid) {
    $currenturl->param('userid', $userid);
} else {
    require_login();
    if (isguestuser()) {
        redirect(new core\url('/'));
    }
    $userid = (int)$USER->id;
}

$report = \tool_mutrain\mureport\system_report\user_completions::make_instance($userid, $frameworkid);
$report->require_access();
$report->init_page($currenturl);
$user = $report->get_constraint_record('user', 'userid');
$framework = $report->get_framework();

if (!\tool_mulib\local\mulib::is_mutrain_active()) {
    redirect(new core\url('/'));
}

if ($userid != $USER->id) {
    $title = get_string('credits', 'tool_mutrain');
} else {
    $title = get_string('credits_my', 'tool_mutrain');
}

$PAGE->navigation->extend_for_user($user);
$PAGE->set_title($title);
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('profile'), new core\url('/user/profile.php', ['id' => $user->id]));
$PAGE->navbar->add($title, new core\url('/admin/tool/mutrain/my/index.php', ['userid' => $user->id]));
$PAGE->navbar->add(format_string($framework->name));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($framework->name));

echo $OUTPUT->render(new \tool_mureport\output\report_full($report));

echo $OUTPUT->footer();
