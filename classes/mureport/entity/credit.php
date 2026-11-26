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

/**
 * Credit entity, current credits of users in frameworks in tool_mutrain_credit.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit extends entity {
    #[\Override]
    public function get_default_source(): sql {
        return new sql('{tool_mutrain_credit}');
    }

    #[\Override]
    protected function get_default_title(): string {
        return get_string('credit', 'tool_mutrain');
    }

    #[\Override]
    public function get_column_names(): array {
        return ['credits', 'timereached'];
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $alias = $this->alias;
        $column = match ($selector) {
            'credits' => $this->create_column('credits', new lang_string('credits_current', 'tool_mutrain'), column::TYPE_DECIMAL)
                ->add_field('credits', "COALESCE($alias.credits, 0)")
                ->add_field('frameworkid', "$alias.frameworkid")
                ->add_field('userid', "$alias.userid")
                ->set_sortable(true)
                ->add_formatter('link', function (cell $cell): string {
                    $fields = $cell->get_fields();
                    $url = new url('/admin/tool/mutrain/my/completions.php', [
                        'frameworkid' => $fields->frameworkid,
                        'userid' => $fields->userid,
                    ]);
                    return \html_writer::link($url, format_float((float)$fields->credits, 2, true, true));
                }, fn(cell $cell): export_value => export_value::number(round((float)$cell->get_value(), 2)))
                ->set_default_formatter('link'),
            'timereached' => $this->create_column(
                'timereached',
                new lang_string('credits_timereached', 'tool_mutrain'),
                column::TYPE_TIMESTAMP
            )
                ->add_field('timereached', "$alias.timereached")
                ->set_sortable(true),
            default => null,
        };
        return $column ? [$column] : [];
    }

    #[\Override]
    public function get_filter_names(): array {
        return ['credits', 'timereached'];
    }

    #[\Override]
    protected function define_filters(string $selector): array {
        $alias = $this->alias;
        $filter = match ($selector) {
            'credits' => (new filter\number(
                $alias,
                'credits',
                new lang_string('credits_current', 'tool_mutrain'),
                "COALESCE($alias.credits, 0)"
            ))
                ->set_decimal(),
            'timereached' => new filter\date(
                $alias,
                'timereached',
                new lang_string('credits_timereached', 'tool_mutrain'),
                "$alias.timereached"
            ),
            default => null,
        };
        return $filter ? [$filter] : [];
    }
}
