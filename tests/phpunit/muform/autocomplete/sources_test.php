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

namespace tool_mutrain\phpunit\muform\autocomplete;

use tool_mutrain\local\framework;
use tool_mutrain\muform\autocomplete\field_add_fieldid;
use tool_mutrain\muform\autocomplete\framework_contextid;

/**
 * Training autocomplete source tests.
 *
 * @group       MuTMS
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_mutrain\muform\autocomplete\field_add_fieldid
 * @covers \tool_mutrain\muform\autocomplete\framework_contextid
 */
final class sources_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_field_add_fieldid(): void {
        /** @var \tool_mutrain_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mutrain');

        $fieldcategory = $this->getDataGenerator()->create_custom_field_category(
            ['component' => 'core_course', 'area' => 'course']
        );
        $fields = [];
        foreach (['mutrain', 'mutrain', 'mutrain', 'text'] as $i => $type) {
            $no = $i + 1;
            $fields[$no] = $this->getDataGenerator()->create_custom_field(
                ['categoryid' => $fieldcategory->get('id'), 'type' => $type, 'shortname' => 'field' . $no, 'name' => 'F' . $no]
            );
        }
        $id = fn(int $no): int => (int)$fields[$no]->get('id');
        $label = fn(int $no): string => 'F' . $no . ' <small>(core_course/course)</small>';

        $category = $this->getDataGenerator()->create_category([]);
        $catcontext = \context_coursecat::instance($category->id);
        $syscontext = \context_system::instance();

        $framework1 = $generator->create_framework();
        $framework2 = $generator->create_framework(['contextid' => $catcontext->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $managerroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mutrain:manageframeworks', CAP_ALLOW, $managerroleid, $syscontext);
        role_assign($managerroleid, $user1->id, $syscontext->id);
        role_assign($managerroleid, $user2->id, $catcontext->id);

        $this->setUser($user1);
        $source = new field_add_fieldid((int)$framework1->id);
        $this->assertSame([(int)$framework1->id], $source->get_args());
        $this->assertSame([$id(1) => $label(1), $id(2) => $label(2), $id(3) => $label(3)], $source->search('', 50));
        $this->assertSame([$id(2) => $label(2)], $source->search('F2', 50));
        $this->assertSame([$id(3) => $label(3)], $source->search('field3', 50));
        $this->assertNull($source->search('', 2));
        $this->assertSame($label(1), $source->label((string)$id(1)));
        $this->assertNull($source->label((string)$id(4)));
        $this->assertNull($source->label('-1'));

        framework::field_add($framework2->id, $id(2));
        $source = new field_add_fieldid((int)$framework2->id);
        $this->assertSame([$id(1) => $label(1), $id(3) => $label(3)], $source->search('', 50));
        $this->assertNull($source->label((string)$id(2)));

        $this->setUser($user2);
        $source = new field_add_fieldid((int)$framework2->id);
        $this->assertSame([$id(1) => $label(1), $id(3) => $label(3)], $source->search('', 50));

        $this->expectException(\required_capability_exception::class);
        new field_add_fieldid((int)$framework1->id);
    }

    public function test_framework_contextid(): void {
        $syscontext = \context_system::instance();
        $category = $this->getDataGenerator()->create_category(['name' => 'Kategorie']);
        $catcontext = \context_coursecat::instance($category->id);

        $user = $this->getDataGenerator()->create_user();
        $managerroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mutrain:manageframeworks', CAP_ALLOW, $managerroleid, $syscontext);
        role_assign($managerroleid, $user->id, $catcontext->id);

        $this->setUser($user);
        $source = new framework_contextid($catcontext->id);
        $this->assertSame([$catcontext->id], $source->get_args());
        $this->assertSame([$catcontext->id => 'Kategorie'], $source->search('', 50));
        $this->assertSame('Kategorie', $source->label((string)$catcontext->id));
        $this->assertNull($source->label((string)$syscontext->id));

        $this->setAdminUser();
        $source = new framework_contextid($syscontext->id);
        $this->assertSame('System', $source->label((string)$syscontext->id));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        new framework_contextid($catcontext->id);
    }
}
