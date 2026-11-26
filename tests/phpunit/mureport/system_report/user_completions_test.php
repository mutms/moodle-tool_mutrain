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

use tool_mutrain\mureport\system_report\user_completions;
use tool_mureport\mureport\request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * User credit completions report tests.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(user_completions::class)]
#[CoversClass(\tool_mutrain\mureport\entity\completion::class)]
#[Group('MuTMS')]
final class user_completions_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns exported rows.
     *
     * @param int $userid
     * @param int $frameworkid
     * @param request $request
     * @return array
     */
    private function get_rows(int $userid, int $frameworkid, request $request = new request([])): array {
        $report = user_completions::make_instance($userid, $frameworkid);
        $report->require_access();
        return $report->get_query()->get_page($request)->export_rows();
    }

    /**
     * Returns completed instance names of report rows.
     *
     * @param int $userid
     * @param int $frameworkid
     * @param request $request
     * @return string[]
     */
    private function get_names(int $userid, int $frameworkid, request $request = new request([])): array {
        $rows = $this->get_rows($userid, $frameworkid, $request);
        return array_map(fn(array $row): string => strip_tags($row['cells']['completion:instance']), $rows);
    }

    /**
     * Mark course completed.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param int $time
     */
    private function complete_course(\stdClass $user, \stdClass $course, int $time): void {
        global $DB;
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $completion = new \completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $completion->mark_complete($time);
        $DB->set_field('tool_mutrain_completion', 'timecompleted', $time, ['userid' => $user->id, 'instanceid' => $course->id]);
    }

    public function test_rows(): void {
        global $DB;

        /** @var \tool_mutrain_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mutrain');
        $fieldcategory = $this->getDataGenerator()->create_custom_field_category(['component' => 'core_course', 'area' => 'course']);
        $this->getDataGenerator()->create_custom_field(
            ['categoryid' => $fieldcategory->get('id'), 'type' => 'mutrain', 'shortname' => 'field1', 'name' => 'Field 1']
        );
        $this->getDataGenerator()->create_custom_field(
            ['categoryid' => $fieldcategory->get('id'), 'type' => 'mutrain', 'shortname' => 'field2', 'name' => 'Field 2']
        );
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $framework1 = $generator->create_framework(['name' => 'Alpha', 'fields' => 'field1']);
        $framework2 = $generator->create_framework(['name' => 'Beta', 'fields' => 'field1,field2', 'contextid' => $catcontext->id, 'restrictcontext' => 1]);
        $framework3 = $generator->create_framework(['name' => 'Hidden', 'fields' => 'field1', 'publicaccess' => 0]);
        $framework4 = $generator->create_framework(['name' => 'Old', 'fields' => 'field1']);
        \tool_mutrain\local\framework::archive($framework4->id);

        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Course 1', 'customfield_field1' => 10, 'enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Course 2', 'customfield_field1' => 40, 'customfield_field2' => 2.5,
            'enablecompletion' => 1, 'category' => $category->id]);
        $course3 = $this->getDataGenerator()->create_course(['fullname' => 'Course 3', 'enablecompletion' => 1]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->complete_course($user1, $course1, 1000);
        $this->complete_course($user1, $course2, 2000);
        $this->complete_course($user1, $course3, 3000);
        $this->complete_course($user2, $course1, 1000);

        $this->setUser($user1);
        $this->assertSame(['Course 1', 'Course 2'], $this->get_names($user1->id, $framework1->id));
        $rows = $this->get_rows($user1->id, $framework1->id);
        $this->assertSame('Course completion', $rows[0]['cells']['completion:type']);
        $this->assertSame('10', $rows[0]['cells']['completion:credits']);
        $this->assertStringContainsString('/course/view.php?id=' . $course1->id, $rows[0]['cells']['completion:instance']);
        $this->assertSame(['Course 2'], $this->get_names($user1->id, $framework1->id, new request(['completion:timecompleted' => ['from' => 1500]])));
        $this->assertSame(['Course 2', 'Course 2'], $this->get_names($user1->id, $framework2->id));
        $rows = $this->get_rows($user1->id, $framework2->id);
        $this->assertSame(['40', '2.5'], array_map(fn(array $row): string => $row['cells']['completion:credits'], $rows));

        $DB->set_field('tool_mutrain_framework', 'restrictafter', 1500, ['id' => $framework1->id]);
        $this->assertSame(['Course 2'], $this->get_names($user1->id, $framework1->id));
        $this->assertSame(['Course 2'], $this->get_names($user1->id, $framework1->id, new request(['completion:type' => ['values' => ['course']]])));
        $this->assertSame([], $this->get_names($user1->id, $framework1->id, new request(['completion:type' => ['values' => ['program']]])));

        try {
            $this->get_rows($user1->id, $framework3->id);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }
        try {
            $this->get_rows($user1->id, $framework4->id);
            $this->fail('Exception expected');
        } catch (\core\exception\invalid_parameter_exception $ex) {
            $this->assertInstanceOf(\core\exception\invalid_parameter_exception::class, $ex);
        }
        try {
            $this->get_rows($user2->id, $framework1->id);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }

        $this->setAdminUser();
        $this->assertSame(['Course 1'], $this->get_names($user2->id, $framework3->id));
    }
}
