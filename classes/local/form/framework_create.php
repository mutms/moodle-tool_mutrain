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

namespace tool_mutrain\local\form;

use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\datetime;
use tool_mulib\muform\element\editor;
use tool_mulib\muform\element\number;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;
use tool_mutrain\muform\autocomplete\framework_contextid;

/**
 * Create a new credit framework.
 *
 * @package    tool_mutrain
 * @copyright  2024 Open LMS (https://www.openlms.net/)
 * @copyright  2025 Petr Skoda
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class framework_create extends form {
    #[\Override]
    protected function definition(): void {
        $current = $this->get_current_data();

        $name = new text('name', get_string('framework_name', 'tool_mutrain'), ['maxlength' => 254]);
        $name->set_required(true);
        $this->add($name);

        $this->add(new text('idnumber', get_string('framework_idnumber', 'tool_mutrain'), ['type' => 'rawtext', 'maxlength' => 100]));

        $contextid = new autocomplete('contextid', get_string('category'), new framework_contextid((int)$current['contextid']));
        $contextid->set_required(true);
        $this->add($contextid);

        $this->add(new checkbox('publicaccess', get_string('publicaccess', 'tool_mutrain')));

        $this->add(new editor('description', get_string('description'), 0, false, ['rows' => 3]));

        $requiredcredits = new number('requiredcredits', get_string('requiredcredits', 'tool_mutrain'), ['decimals' => 2, 'min' => 0, 'width' => 'small']);
        $requiredcredits->set_required(true);
        $this->add($requiredcredits);

        $this->add(new checkbox('restrictcontext', get_string('restrictcontext', 'tool_mutrain')));

        $this->add(new datetime('restrictafter', get_string('restrictafter', 'tool_mutrain')));

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('framework_create', 'tool_mutrain')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        global $DB;
        if (trim($data['idnumber']) !== '') {
            $select = "LOWER(idnumber) = LOWER(?) AND id <> ?";
            if ($DB->record_exists_select('tool_mutrain_framework', $select, [$data['idnumber'], 0])) {
                $allerrors['idnumber'][] = get_string('error');
            }
        }
        if ($data['requiredcredits'] !== null && $data['requiredcredits'] <= 0) {
            $allerrors['requiredcredits'][] = get_string('error');
        }
    }
}
