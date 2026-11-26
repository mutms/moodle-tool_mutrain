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

namespace tool_mutrain\mureport\system_report;

use tool_mureport\mureport\system_report;
use tool_mutrain\mureport\entity\completion;
use tool_mulib\local\sql;
use core\exception\invalid_parameter_exception;
use core\lang_string;

/**
 * Completions of a user that count towards credits in a framework that is not archived.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_completions extends system_report {
    /**
     * Make report instance.
     *
     * @param int $userid
     * @param int $frameworkid
     * @return self
     */
    public static function make_instance(int $userid, int $frameworkid): self {
        return self::from_constraints(['userid' => $userid, 'frameworkid' => $frameworkid]);
    }

    #[\Override]
    public static function get_title(): string {
        return get_string('usercompletions', 'tool_mutrain');
    }

    #[\Override]
    public function get_context(): \core\context {
        $user = $this->get_constraint_record('user', 'userid');
        if (!$user || $user->deleted || isguestuser($user)) {
            throw new invalid_parameter_exception('Invalid user');
        }
        return \core\context\user::instance($user->id);
    }

    /**
     * Returns framework of the report, frameworks that are archived are not valid.
     *
     * @return \stdClass
     */
    public function get_framework(): \stdClass {
        $framework = $this->get_constraint_record('tool_mutrain_framework', 'frameworkid');
        if (!$framework || $framework->archived) {
            throw new invalid_parameter_exception('Invalid framework');
        }
        return $framework;
    }

    #[\Override]
    protected function check_access(): void {
        global $USER;
        $framework = $this->get_framework();
        if (!$framework->publicaccess) {
            require_capability('tool/mutrain:viewframeworks', \core\context::instance_by_id($framework->contextid));
        }
        if ($this->get_context()->instanceid != $USER->id) {
            $this->require_capability('tool/mutrain:viewusercredits');
        }
    }

    #[\Override]
    protected function define(): void {
        $this->set_main_entity(new completion('completion'));

        $this->set_default_columns([
            'completion:timecompleted',
            'completion:type',
            'completion:instance',
            'completion:credits',
        ]);
        $this->set_default_filters([
            'completion:type',
            'completion:timecompleted',
        ]);
        $this->set_export_allowed(false);
        $this->set_default_sorting(['completion:timecompleted' => 'asc']);
    }

    #[\Override]
    protected function apply_constraints(): void {
        $this->add_constraint_restriction('userid', 'completion', 'userid');
        $framework = $this->get_constraint_record('tool_mutrain_framework', 'frameworkid');
        if (!$framework || $framework->archived) {
            $this->set_always_empty();
            return;
        }
        $this->add_restriction(new sql(
            "EXISTS (SELECT 'x'
                       FROM {tool_mutrain_field} rf
                      WHERE rf.fieldid = completion.fieldid AND rf.frameworkid = :frameworkid)",
            ['frameworkid' => $framework->id]
        ), ['completion']);
        $this->add_restriction(
            "EXISTS (SELECT 'x'
                       FROM {customfield_data} rcd
                      WHERE rcd.fieldid = completion.fieldid AND rcd.instanceid = completion.instanceid
                            AND rcd.decvalue IS NOT NULL)",
            ['completion']
        );
        if ($framework->restrictcontext) {
            $context = \core\context::instance_by_id($framework->contextid, IGNORE_MISSING);
            if ($context && $context->contextlevel != CONTEXT_SYSTEM) {
                $this->add_context_restriction('completion', $context);
            }
        }
        if ($framework->restrictafter) {
            $this->add_restriction(new sql(
                'completion.timecompleted >= :restrictafter',
                ['restrictafter' => $framework->restrictafter]
            ), ['completion']);
        }
    }
}
