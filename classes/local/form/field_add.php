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
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_mutrain\muform\autocomplete\field_add_fieldid;

/**
 * Add field to training framework.
 *
 * @package    tool_mutrain
 * @copyright  2024 Open LMS (https://www.openlms.net/)
 * @copyright  2025 Petr Skoda
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class field_add extends form {
    #[\Override]
    protected function definition(): void {
        $frameworkid = (int)$this->get_extra_data()['framework']->id;

        $fieldid = new autocomplete('fieldid', get_string('field', 'tool_mutrain'), new field_add_fieldid($frameworkid));
        $fieldid->set_required(true);
        $this->add($fieldid);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('field_add', 'tool_mutrain')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
