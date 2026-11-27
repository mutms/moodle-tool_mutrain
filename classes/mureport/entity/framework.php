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
 * Credit framework entity, frameworks in tool_mutrain_framework.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework extends entity {
    #[\Override]
    public function get_default_source(): sql {
        return new sql('{tool_mutrain_framework}');
    }

    #[\Override]
    protected function get_default_title(): string {
        return get_string('framework', 'tool_mutrain');
    }

    #[\Override]
    public function get_context_sql(): ?sql {
        return new sql("{$this->alias}.contextid");
    }

    #[\Override]
    public function get_context_for_key(int|string $key): ?\core\context {
        global $DB;
        $contextid = $DB->get_field('tool_mutrain_framework', 'contextid', ['id' => $key]);
        return $contextid ? (\core\context::instance_by_id($contextid, IGNORE_MISSING) ?: null) : null;
    }

    #[\Override]
    public function get_column_names(): array {
        return [
            'name', 'idnumber', 'id', 'category', 'publicaccess', 'fieldcount', 'requiredcredits', 'restrictcontext',
            'restrictafter', 'archived', 'timecreated',
        ];
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $alias = $this->alias;
        $column = match ($selector) {
            'id' => $this->create_id_column(),
            'name' => $this->define_name_column(),
            'idnumber' => $this->create_column('idnumber', new lang_string('framework_idnumber', 'tool_mutrain'), column::TYPE_TEXT)
                ->add_field('idnumber', "$alias.idnumber")
                ->set_sortable(true),
            'category' => $this->define_category_column(),
            'publicaccess', 'restrictcontext', 'archived' => $this->create_column(
                $selector,
                new lang_string($selector, 'tool_mutrain'),
                column::TYPE_BOOLEAN
            )
                ->add_field($selector, "$alias.$selector")
                ->set_sortable(true),
            'fieldcount' => $this->create_column('fieldcount', new lang_string('fields', 'tool_mutrain'), column::TYPE_INTEGER)
                ->add_field('fieldcount', "(SELECT COUNT('x')
                                              FROM {tool_mutrain_field} tf
                                             WHERE tf.frameworkid = $alias.id)")
                ->set_sortable(true),
            'requiredcredits' => $this->create_column(
                'requiredcredits',
                new lang_string('requiredcredits', 'tool_mutrain'),
                column::TYPE_DECIMAL
            )
                ->add_field('requiredcredits', "$alias.requiredcredits")
                ->set_sortable(true),
            'restrictafter' => $this->create_column(
                'restrictafter',
                new lang_string('restrictafter', 'tool_mutrain'),
                column::TYPE_TIMESTAMP
            )
                ->add_field('restrictafter', "$alias.restrictafter")
                ->set_sortable(true),
            'timecreated' => $this->create_column(
                'timecreated',
                new lang_string('timecreated', 'core_reportbuilder'),
                column::TYPE_TIMESTAMP
            )
                ->add_field('timecreated', "$alias.timecreated")
                ->set_sortable(true),
            default => null,
        };
        return $column ? [$column] : [];
    }

    #[\Override]
    public function get_filter_names(): array {
        return [
            'name', 'idnumber', 'id', 'category', 'publicaccess', 'requiredcredits', 'restrictcontext', 'restrictafter', 'archived',
        ];
    }

    #[\Override]
    protected function define_filters(string $selector): array {
        $alias = $this->alias;
        $filter = match ($selector) {
            'id' => $this->create_id_filter(),
            'name' => new filter\text($alias, 'name', new lang_string('framework_name', 'tool_mutrain'), "$alias.name"),
            'idnumber' => new filter\text(
                $alias,
                'idnumber',
                new lang_string('framework_idnumber', 'tool_mutrain'),
                "$alias.idnumber"
            ),
            'category' => (new filter\picker($alias, 'category', new lang_string('category'), "$alias.contextid"))
                ->set_integer_values()
                ->set_labels(fn(array $values): array => self::get_category_labels($values)),
            'publicaccess', 'restrictcontext', 'archived' => new filter\boolean(
                $alias,
                $selector,
                new lang_string($selector, 'tool_mutrain'),
                "$alias.$selector"
            ),
            'requiredcredits' => (new filter\number(
                $alias,
                'requiredcredits',
                new lang_string('requiredcredits', 'tool_mutrain'),
                "$alias.requiredcredits"
            ))
                ->set_decimal(),
            'restrictafter' => new filter\date(
                $alias,
                'restrictafter',
                new lang_string('restrictafter', 'tool_mutrain'),
                "$alias.restrictafter"
            ),
            default => null,
        };
        return $filter ? [$filter] : [];
    }

    /**
     * Define framework name column, linked to framework management when the viewer may view frameworks.
     *
     * @return column
     */
    private function define_name_column(): column {
        $alias = $this->alias;
        $name = fn(stdClass $fields, bool $escape): string => format_string(
            $fields->name,
            true,
            ['context' => \core\context::instance_by_id($fields->contextid), 'escape' => $escape]
        );
        $url = function (stdClass $fields): ?url {
            if (!has_capability('tool/mutrain:viewframeworks', \core\context::instance_by_id($fields->contextid))) {
                return null;
            }
            return new url('/admin/tool/mutrain/management/framework.php', ['id' => $fields->id]);
        };
        return $this->create_column('name', new lang_string('framework_name', 'tool_mutrain'), column::TYPE_TEXT)
            ->add_field('name', "$alias.name")
            ->add_field('id', "$alias.id")
            ->add_field('contextid', "$alias.contextid")
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

    /**
     * Define framework category column, linked to framework management of the category.
     *
     * @return column
     */
    private function define_category_column(): column {
        $alias = $this->alias;
        $name = fn(stdClass $fields): string
            => \core\context::instance_by_id($fields->contextid)->get_context_name(false, false, false);
        $url = fn(stdClass $fields): url
            => new url('/admin/tool/mutrain/management/index.php', ['contextid' => $fields->contextid]);
        return $this->create_column('category', new lang_string('category'), column::TYPE_TEXT)
            ->add_field('contextid', "$alias.contextid")
            ->add_formatter(
                'name',
                fn(cell $cell): string => s($name($cell->get_fields())),
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields()))
            )
            ->add_formatter(
                'link',
                function (cell $cell) use ($name, $url): string {
                    $fields = $cell->get_fields();
                    $context = \core\context::instance_by_id($fields->contextid);
                    if (!has_capability('tool/mutrain:viewframeworks', $context)) {
                        return s($name($fields));
                    }
                    return \html_writer::link($url($fields), s($name($fields)));
                },
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields()))
            )
            ->set_default_formatter('link');
    }

    /**
     * Returns names of framework contexts, the system context or course categories.
     *
     * @param string[] $contextids
     * @return array context id => plain text name
     */
    public static function get_category_labels(array $contextids): array {
        global $DB;
        if (!$contextids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $contextids), SQL_PARAMS_NAMED, 'ctx');
        $params['catlevel'] = CONTEXT_COURSECAT;
        $sql = "SELECT ctx.id, ctx.contextlevel, cc.name
                  FROM {context} ctx
             LEFT JOIN {course_categories} cc ON cc.id = ctx.instanceid AND ctx.contextlevel = :catlevel
                 WHERE ctx.id $insql";
        $result = [];
        $syscontext = \core\context\system::instance();
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            if ((int)$record->contextlevel === CONTEXT_SYSTEM) {
                $result[$record->id] = $syscontext->get_context_name(false, false, false);
            } else if ($record->name !== null) {
                $result[$record->id] = format_string($record->name, true, ['context' => $syscontext, 'escape' => false]);
            }
        }
        return $result;
    }
}
