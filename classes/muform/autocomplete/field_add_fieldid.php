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

namespace tool_mutrain\muform\autocomplete;

use stdClass;
use tool_mutrain\local\framework;

/**
 * Training custom field that may be added to a framework.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class field_add_fieldid extends \tool_mulib\muform\autocomplete\base {
    /** @var stdClass framework record */
    private stdClass $framework;

    /**
     * Constructor.
     *
     * @param int $frameworkid
     */
    public function __construct(
        /** @var int framework id */
        private readonly int $frameworkid
    ) {
        global $DB;
        $this->framework = $DB->get_record('tool_mutrain_framework', ['id' => $frameworkid], '*', MUST_EXIST);
        require_capability('tool/mutrain:manageframeworks', \context::instance_by_id($this->framework->contextid));
    }

    #[\Override]
    public function get_args(): array {
        return [$this->frameworkid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        $query = \core_text::strtolower(trim($query));
        $result = [];
        foreach ($this->get_candidates() as $field) {
            if ($query !== '') {
                $name = \core_text::strtolower(format_string($field->name));
                if (!str_contains($name, $query) && !str_contains(\core_text::strtolower($field->shortname), $query)) {
                    continue;
                }
            }
            $result[(string)$field->id] = self::format_label($field);
        }
        if (count($result) > $maxitems) {
            return null;
        }
        return $result;
    }

    #[\Override]
    public function label(string $value): ?string {
        $candidates = $this->get_candidates();
        if (!isset($candidates[$value])) {
            return null;
        }
        return self::format_label($candidates[$value]);
    }

    /**
     * Training fields not added to the framework yet.
     *
     * @return stdClass[] indexed by field id
     */
    private function get_candidates(): array {
        global $DB;
        $current = $DB->get_records_menu('tool_mutrain_field', ['frameworkid' => $this->framework->id], '', 'fieldid, id');
        return array_diff_key(framework::get_all_training_fields(), $current);
    }

    /**
     * Field name with its component and area.
     *
     * @param stdClass $field
     * @return string label html
     */
    private static function format_label(stdClass $field): string {
        $name = format_string($field->name);
        return clean_text($name . ' <small>(' . s($field->component . '/' . $field->area) . ')</small>');
    }
}
