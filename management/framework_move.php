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

/**
 * Move credit framework to different context.
 *
 * @package    tool_mutrain
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\handler;
use tool_mutrain\local\framework;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();

$framework = $DB->get_record('tool_mutrain_framework', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($framework->contextid);
require_capability('tool/mutrain:manageframeworks', $context);

$currenturl = new core\url('/admin/tool/mutrain/management/framework_move.php', ['id' => $framework->id]);
$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$title = get_string('framework_move', 'tool_mutrain');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$returnurl = new core\url('/admin/tool/mutrain/management/framework.php', ['id' => $framework->id]);

$handler = handler::from_request();

$form = new \tool_mutrain\local\form\framework_move($currenturl, $framework);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    framework::move($framework->id, (int)$data->contextid, (int)$data->restrictcontext);
    $handler->submitted($returnurl);
}

$handler->render($form);
