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

namespace tool_mutrain\mureport\entity;

use tool_mureport\mureport\cell;
use tool_mureport\mureport\column;
use tool_mureport\mureport\entity;
use tool_mureport\mureport\export_value;
use tool_mureport\mureport\filter;
use tool_mulib\local\sql;
use core\lang_string;
use core\url;
use stdClass;

/**
 * Completion entity, completions of courses and programs with training credits fields in tool_mutrain_completion.
 *
 * Helper joins: alias_field for the credits custom field and its category alias_fieldcat.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion extends entity {
    #[\Override]
    public function get_default_source(): sql {
        return new sql('{tool_mutrain_completion}');
    }

    #[\Override]
    protected function get_default_title(): string {
        return get_string('completion', 'tool_mutrain');
    }

    #[\Override]
    public function get_context_sql(): ?sql {
        return new sql("{$this->alias}.contextid");
    }

    #[\Override]
    public function get_context_for_key(int|string $key): ?\core\context {
        global $DB;
        $contextid = $DB->get_field('tool_mutrain_completion', 'contextid', ['id' => $key]);
        return $contextid ? (\core\context::instance_by_id($contextid, IGNORE_MISSING) ?: null) : null;
    }

    #[\Override]
    public function get_helper_join(string $helperalias): sql {
        if ($helperalias === "{$this->alias}_field") {
            $cat = "{$this->alias}_fieldcat";
            return new sql("LEFT JOIN {customfield_field} $helperalias ON $helperalias.id = {$this->alias}.fieldid
                            LEFT JOIN {customfield_category} $cat ON $cat.id = $helperalias.categoryid");
        }
        return parent::get_helper_join($helperalias);
    }

    #[\Override]
    public function get_column_names(): array {
        return ['type', 'field', 'instance', 'timecompleted', 'credits'];
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $alias = $this->alias;
        $column = match ($selector) {
            'type' => $this->create_column('type', new lang_string('completion_type', 'tool_mutrain'), column::TYPE_ENUMERATION)
                ->add_helper_join("{$alias}_field")
                ->add_field('type', $this->get_type_sql())
                ->set_options(fn(): array => self::get_type_options())
                ->set_sortable(true),
            'field' => $this->define_field_column(),
            'instance' => $this->define_instance_column(),
            'timecompleted' => $this->create_column('timecompleted', new lang_string('completed'), column::TYPE_TIMESTAMP)
                ->add_field('timecompleted', "$alias.timecompleted")
                ->set_sortable(true),
            'credits' => $this->create_column('credits', new lang_string('credits', 'tool_mutrain'), column::TYPE_DECIMAL)
                ->add_field('credits', $this->get_credits_sql())
                ->set_sortable(true),
            default => null,
        };
        return $column ? [$column] : [];
    }

    #[\Override]
    public function get_filter_names(): array {
        return ['type', 'timecompleted', 'credits'];
    }

    #[\Override]
    protected function define_filters(string $selector): array {
        $alias = $this->alias;
        $filter = match ($selector) {
            'type' => (new filter\checkboxes(
                $alias,
                'type',
                new lang_string('completion_type', 'tool_mutrain'),
                $this->get_type_sql()
            ))
                ->add_helper_join("{$alias}_field")
                ->set_options(fn(): array => self::get_type_options()),
            'timecompleted' => new filter\date($alias, 'timecompleted', new lang_string('completed'), "$alias.timecompleted"),
            'credits' => (new filter\number(
                $alias,
                'credits',
                new lang_string('credits', 'tool_mutrain'),
                $this->get_credits_sql()
            ))
                ->set_decimal(),
            default => null,
        };
        return $filter ? [$filter] : [];
    }

    /**
     * Returns SQL of completion type, 'course', 'program' or NULL for unknown areas.
     *
     * @return string
     */
    private function get_type_sql(): string {
        $cat = "{$this->alias}_fieldcat";
        return "CASE
                    WHEN $cat.component = 'core_course' AND $cat.area = 'course' THEN 'course'
                    WHEN $cat.component = 'tool_muprog' AND $cat.area = 'program' THEN 'program'
                    ELSE NULL
                END";
    }

    /**
     * Returns SQL of credits value of the completed instance.
     *
     * @return string
     */
    private function get_credits_sql(): string {
        $alias = $this->alias;
        return "(SELECT cd.decvalue
                   FROM {customfield_data} cd
                  WHERE cd.fieldid = $alias.fieldid AND cd.instanceid = $alias.instanceid)";
    }

    /**
     * Returns completion type names.
     *
     * @return array type => name
     */
    public static function get_type_options(): array {
        $options = ['course' => get_string('area_core_course_course', 'tool_mutrain')];
        if (\tool_mulib\local\mulib::is_muprog_available()) {
            $options['program'] = get_string('area_tool_muprog_program', 'tool_mutrain');
        }
        return $options;
    }

    /**
     * Define custom field name column.
     *
     * @return column
     */
    private function define_field_column(): column {
        $alias = $this->alias;
        $name = fn(stdClass $fields, bool $escape): string => format_string(
            (string)$fields->fieldname,
            true,
            ['context' => \core\context\system::instance(), 'escape' => $escape]
        );
        return $this->create_column('field', new lang_string('customfield', 'core_customfield'), column::TYPE_TEXT)
            ->add_helper_join("{$alias}_field")
            ->add_field('fieldname', "{$alias}_field.name")
            ->set_sortable(true)
            ->add_formatter(
                'name',
                fn(cell $cell): string => $name($cell->get_fields(), true),
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields(), false))
            )
            ->set_default_formatter('name');
    }

    /**
     * Define name of completed course or program, courses are linked when the viewer may view them,
     * programs are linked for the user who completed them.
     *
     * @return column
     */
    private function define_instance_column(): column {
        global $USER;

        $alias = $this->alias;
        $type = $this->get_type_sql();
        $program = "NULL";
        $programarchived = "NULL";
        if (\tool_mulib\local\mulib::is_muprog_available()) {
            $program = "(SELECT p.fullname FROM {tool_muprog_program} p WHERE p.id = $alias.instanceid)";
            $programarchived = "(SELECT p.archived FROM {tool_muprog_program} p WHERE p.id = $alias.instanceid)";
        }
        $namesql = "CASE
                        WHEN $type = 'course' THEN (SELECT c.fullname FROM {course} c WHERE c.id = $alias.instanceid)
                        WHEN $type = 'program' THEN $program
                        ELSE NULL
                    END";
        $name = fn(stdClass $fields, bool $escape): string => format_string(
            (string)$fields->instancename,
            true,
            ['context' => \core\context::instance_by_id($fields->contextid), 'escape' => $escape]
        );
        $url = function (stdClass $fields) use ($USER): ?url {
            if ($fields->instancename === null) {
                return null;
            }
            if ($fields->type === 'course') {
                $context = \core\context\course::instance($fields->instanceid, IGNORE_MISSING);
                if ($context && (has_capability('moodle/course:view', $context) || is_enrolled($context))) {
                    return new url('/course/view.php', ['id' => $fields->instanceid]);
                }
            } else if ($fields->type === 'program') {
                if (!$fields->programarchived && $fields->userid == $USER->id) {
                    return new url('/admin/tool/muprog/my/program.php', ['id' => $fields->instanceid]);
                }
            }
            return null;
        };
        return $this->create_column('instance', new lang_string('completion_instance', 'tool_mutrain'), column::TYPE_TEXT)
            ->add_helper_join("{$alias}_field")
            ->add_field('instancename', $namesql)
            ->add_field('type', $type)
            ->add_field('instanceid', "$alias.instanceid")
            ->add_field('contextid', "$alias.contextid")
            ->add_field('userid', "$alias.userid")
            ->add_field('programarchived', $programarchived)
            ->set_sortable(true)
            ->add_formatter(
                'name',
                fn(cell $cell): string => $name($cell->get_fields(), true),
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields(), false))
            )
            ->add_formatter(
                'link',
                function (cell $cell) use ($name, $url): string {
                    $fields = $cell->get_fields();
                    $link = $url($fields);
                    return $link ? \html_writer::link($link, $name($fields, true)) : $name($fields, true);
                },
                function (cell $cell) use ($name, $url): export_value {
                    $fields = $cell->get_fields();
                    $link = $url($fields);
                    return $link ? export_value::link($link, $name($fields, false)) : export_value::text($name($fields, false));
                }
            )
            ->set_default_formatter('link');
    }
}
