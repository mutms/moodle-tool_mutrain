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

use tool_mutrain\mureport\system_report\user_frameworks;
use tool_mureport\mureport\request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * User credit frameworks report tests.
 *
 * @package     tool_mutrain
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(user_frameworks::class)]
#[CoversClass(\tool_mutrain\mureport\entity\credit::class)]
#[Group('MuTMS')]
final class user_frameworks_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns exported rows.
     *
     * @param int $userid
     * @param request $request
     * @return array
     */
    private function get_rows(int $userid, request $request = new request([])): array {
        $report = user_frameworks::make_instance($userid);
        $report->require_access();
        return $report->get_query()->get_page($request)->export_rows();
    }

    /**
     * Returns framework names of report rows.
     *
     * @param int $userid
     * @param request $request
     * @return string[]
     */
    private function get_names(int $userid, request $request = new request([])): array {
        return array_map(fn(array $row): string => $row['cells']['framework:name'], $this->get_rows($userid, $request));
    }

    public function test_rows(): void {
        /** @var \tool_mutrain_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_mutrain');
        $fieldcategory = $this->getDataGenerator()->create_custom_field_category(['component' => 'core_course', 'area' => 'course']);
        $this->getDataGenerator()->create_custom_field(
            ['categoryid' => $fieldcategory->get('id'), 'type' => 'mutrain', 'shortname' => 'field1', 'name' => 'F1']
        );
        $this->getDataGenerator()->create_custom_field(
            ['categoryid' => $fieldcategory->get('id'), 'type' => 'mutrain', 'shortname' => 'field2', 'name' => 'F2']
        );
        $framework1 = $generator->create_framework(['name' => 'Alpha', 'idnumber' => 'FA', 'fields' => 'field1', 'requiredcredits' => 30]);
        $generator->create_framework(['name' => 'Beta', 'fields' => 'field2', 'requiredcredits' => 5]);
        $generator->create_framework(['name' => 'Hidden', 'fields' => 'field1', 'publicaccess' => 0]);
        $framework4 = $generator->create_framework(['name' => 'Old', 'fields' => 'field1']);

        $course1 = $this->getDataGenerator()->create_course(['customfield_field1' => 10, 'customfield_field2' => 1, 'enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['customfield_field1' => 40, 'enablecompletion' => 1]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        foreach ([[$user1, $course1], [$user1, $course2], [$user2, $course1]] as [$user, $course]) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
            $completion = new \completion_completion(['course' => $course->id, 'userid' => $user->id]);
            $completion->mark_complete();
        }
        \tool_mutrain\local\framework::archive($framework4->id);
        \tool_mutrain\local\framework::sync_credits(null, null);

        $this->setUser($user1);
        $this->assertSame(['Alpha', 'Beta'], $this->get_names($user1->id));
        $rows = $this->get_rows($user1->id);
        $this->assertSame('50', strip_tags($rows[0]['cells']['credit:credits']));
        $url = '/admin/tool/mutrain/my/completions.php?frameworkid=' . $framework1->id . '&amp;userid=' . $user1->id;
        $this->assertStringContainsString($url, $rows[0]['cells']['credit:credits']);
        $this->assertSame('30', $rows[0]['cells']['framework:requiredcredits']);
        $this->assertSame('1', strip_tags($rows[1]['cells']['credit:credits']));
        $this->assertSame(['Alpha'], $this->get_names($user1->id, new request([], null, 0, null, 'FA')));
        $this->assertSame(['Alpha'], $this->get_names($user1->id, new request(['credit:timereached' => ['from' => 1]])));

        $this->setUser($user2);
        $this->assertSame(['Alpha', 'Beta'], $this->get_names($user2->id));
        try {
            $this->get_rows($user1->id);
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/mutrain:viewusercredits', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user2->id, \context_user::instance($user1->id)->id);
        $this->assertSame(['Alpha', 'Beta'], $this->get_names($user1->id));
    }

    public function test_invalid_user(): void {
        $this->setAdminUser();
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        user_frameworks::make_instance((int)guest_user()->id)->require_access();
    }
}
