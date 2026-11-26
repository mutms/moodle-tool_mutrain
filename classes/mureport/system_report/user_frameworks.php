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

use tool_mureport\mureport\join;
use tool_mureport\mureport\system_report;
use tool_mutrain\mureport\entity\credit;
use tool_mutrain\mureport\entity\framework;
use tool_mulib\local\sql;
use core\exception\invalid_parameter_exception;
use core\lang_string;

/**
 * Credits of a user in public frameworks that are not archived.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_frameworks extends system_report {
    /**
     * Make report instance.
     *
     * @param int $userid
     * @return self
     */
    public static function make_instance(int $userid): self {
        return self::from_constraints(['userid' => $userid]);
    }

    #[\Override]
    public static function get_title(): string {
        return get_string('userframeworks', 'tool_mutrain');
    }

    #[\Override]
    public function get_context(): \core\context {
        $user = $this->get_constraint_record('user', 'userid');
        if (!$user || $user->deleted || isguestuser($user)) {
            throw new invalid_parameter_exception('Invalid user');
        }
        return \core\context\user::instance($user->id);
    }

    #[\Override]
    protected function check_access(): void {
        global $USER;
        if ($this->get_context()->instanceid != $USER->id) {
            $this->require_capability('tool/mutrain:viewusercredits');
        }
    }

    #[\Override]
    protected function define(): void {
        $this->set_main_entity(new credit('credit'));
        $this->add_entity(new framework('framework'), new join('credit', 'framework.id = credit.frameworkid', inner: true));
        $this->add_restriction('framework.publicaccess = 1 AND framework.archived = 0', ['framework']);

        $this->set_default_columns([
            'framework:name',
            'framework:restrictcontext',
            'framework:restrictafter',
            'credit:credits',
            'framework:requiredcredits',
        ]);
        $this->set_default_filters([
            'framework:name' => ['multisearch' => true],
            'framework:idnumber' => ['multisearch' => true],
            'credit:timereached',
        ]);
        $this->remove_columns(['framework:category', 'framework:publicaccess', 'framework:fieldcount', 'framework:archived']);
        $this->remove_filters(['framework:category', 'framework:publicaccess', 'framework:archived']);
        $this->set_multisearch_label(new lang_string('frameworksearch', 'tool_mutrain'));
        $this->set_export_allowed(false);
        $this->set_default_sorting(['framework:name' => 'asc']);
    }

    #[\Override]
    protected function apply_constraints(): void {
        $this->add_constraint_restriction('userid', 'credit', 'userid');
        $usercontext = $this->get_context();
        if (\tool_mulib\local\mulib::is_mutenancy_active() && $usercontext->tenantid) {
            $this->add_restriction(new sql(
                "EXISTS (SELECT 'x'
                           FROM {context} fctx
                          WHERE fctx.id = framework.contextid
                                AND (fctx.tenantid IS NULL OR fctx.tenantid = :frameworktenantid))",
                ['frameworktenantid' => $usercontext->tenantid]
            ), ['framework']);
        }
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $columns = parent::define_columns($selector);
        if ($selector === 'framework:name') {
            $columns['framework:name']->set_default_formatter('name');
        }
        return $columns;
    }
}
