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
// phpcs:disable moodle.Files.LineLength.TooLong
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_mutrain\phpunit\mureport\system_report;

use tool_mutrain\mureport\system_report\frameworks;
use tool_mureport\mureport\request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Credit frameworks report tests.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(frameworks::class)]
#[CoversClass(\tool_mutrain\mureport\entity\framework::class)]
#[Group('MuTMS')]
final class frameworks_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns framework idnumbers of report rows.
     *
     * @param int $contextid
     * @param request $request
     * @return string[]
     */
    private function get_idnumbers(int $contextid, request $request = new request([])): array {
        $report = frameworks::make_instance($contextid);
        $report->require_access();
        $rows = $report->get_query()->get_page($request)->export_rows();
        return array_map(fn(array $row): string => $row['cells']['framework:idnumber'], $rows);
    }

    public function test_rows(): void {
        /** @var \tool_mutrain_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mutrain');
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category(['parent' => $category1->id]);
        $category3 = $this->getDataGenerator()->create_category();
        $catcontext1 = \context_coursecat::instance($category1->id);
        $catcontext2 = \context_coursecat::instance($category2->id);
        $syscontext = \context_system::instance();

        $fieldcategory = $this->getDataGenerator()->create_custom_field_category(['component' => 'core_course', 'area' => 'course']);
        $field1 = $this->getDataGenerator()->create_custom_field(
            ['categoryid' => $fieldcategory->get('id'), 'type' => 'mutrain', 'shortname' => 'field1', 'name' => 'F1']
        );
        $field2 = $this->getDataGenerator()->create_custom_field(
            ['categoryid' => $fieldcategory->get('id'), 'type' => 'mutrain', 'shortname' => 'field2', 'name' => 'F2']
        );

        $framework0 = $generator->create_framework(['idnumber' => 'F0', 'name' => 'Alpha', 'fields' => [$field1->get('id'), $field2->get('id')]]);
        $generator->create_framework(['idnumber' => 'F1', 'name' => 'Beta', 'contextid' => $catcontext1->id, 'publicaccess' => 0]);
        $generator->create_framework(['idnumber' => 'F2', 'name' => 'Gamma', 'contextid' => $catcontext2->id, 'requiredcredits' => 7.5]);
        $generator->create_framework(['idnumber' => 'F3', 'name' => 'Delta',
            'contextid' => \context_coursecat::instance($category3->id)->id]);
        $framework4 = $generator->create_framework(['idnumber' => 'F4', 'name' => 'Old']);
        \tool_mutrain\local\framework::archive($framework4->id);

        $this->setAdminUser();
        $this->assertSame(['F0', 'F1', 'F3', 'F2', 'F4'], $this->get_idnumbers($syscontext->id));
        $this->assertSame(['F0', 'F1', 'F3', 'F2'], $this->get_idnumbers($syscontext->id, new request(['framework:archived' => ['value' => 0]])));
        $this->assertSame(['F4'], $this->get_idnumbers($syscontext->id, new request(['framework:archived' => ['value' => 1]])));
        $this->assertSame(['F1'], $this->get_idnumbers($syscontext->id, new request(['framework:publicaccess' => ['value' => 0]])));
        $this->assertSame(['F1', 'F2'], $this->get_idnumbers($catcontext1->id));
        $this->assertSame(['F2'], $this->get_idnumbers($catcontext2->id));
        $this->assertSame(['F1'], $this->get_idnumbers($syscontext->id, new request([], null, 0, null, 'beta')));
        $this->assertSame(['F2'], $this->get_idnumbers($syscontext->id, new request([], null, 0, null, 'F2')));
        $category = new request(['framework:category' => ['values' => [(string)$catcontext1->id]]]);
        $this->assertSame(['F1'], $this->get_idnumbers($syscontext->id, $category));

        $report = frameworks::make_instance($syscontext->id);
        $rows = $report->get_query()->get_page(new request([]))->export_rows();
        $this->assertStringContainsString('/admin/tool/mutrain/management/framework.php?id=' . $framework0->id, $rows[0]['cells']['framework:name']);
        $this->assertStringContainsString('System', $rows[0]['cells']['framework:category']);
        $this->assertSame('2', strip_tags($rows[0]['cells']['framework:fieldcount']));
        $this->assertSame('Yes', $rows[0]['cells']['framework:publicaccess']);
        $this->assertSame('7.5', $rows[3]['cells']['framework:requiredcredits']);
        $this->assertSame('Gamma', strip_tags($rows[3]['cells']['framework:name']));
        $this->assertSame([false, false, false, false, true], array_column($rows, 'muted'));
        $labels = \tool_mutrain\mureport\entity\framework::get_category_labels([(string)$catcontext1->id, (string)$syscontext->id]);
        $this->assertSame($category1->name, $labels[$catcontext1->id]);
        $this->assertSame('System', $labels[$syscontext->id]);

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mutrain:viewframeworks', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $user->id, $catcontext1->id);
        assign_capability('tool/mutrain:viewframeworks', CAP_PROHIBIT, $roleid, $catcontext2);
        $this->setUser($user);
        $this->assertSame(['F1'], $this->get_idnumbers($catcontext1->id));
        try {
            $this->get_idnumbers($syscontext->id);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }
    }

    public function test_invalid_context(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        frameworks::make_instance(\context_course::instance($course->id)->id)->require_access();
    }
}
