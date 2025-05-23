<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @copyright  2022 onwards Vitaly Potenko <potenkov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivequiz\local\repository;

use advanced_testcase;
use coding_exception;
use context_course;
use core_question_generator;
use core_tag_tag;

final class tags_repository_test extends advanced_testcase {
    /**
     * @test
     */
    public function it_gets_question_level_to_tag_id_mapping_by_tag_names(): void {
        $this->resetAfterTest();

        $this->assertEquals(
            [],
            tags_repository::get_question_level_to_tag_id_mapping_by_tag_names(
                ['adpq_5','adpq_6',]
            )
        );

        $generator = $this->getDataGenerator();
        /** @var  core_question_generator $questionsgenerator */
        $questionsgenerator = $generator->get_plugin_generator('core_question');

        $course = $generator->create_course();
        $questionscat = $questionsgenerator->create_question_category(
            ['contextid' => context_course::instance($course->id)->id,]
        );

        $question1 = $questionsgenerator->create_question('shortanswer', null, ['category' => $questionscat->id]);
        core_tag_tag::add_item_tag('core_question', 'question', $question1->id, context_course::instance($course->id), 'adpq_5');

        $question2 = $questionsgenerator->create_question('shortanswer', null, ['category' => $questionscat->id]);
        core_tag_tag::add_item_tag('core_question', 'question', $question2->id, context_course::instance($course->id), 'adpq_6');

        $question3 = $questionsgenerator->create_question('shortanswer', null, ['category' => $questionscat->id]);
        core_tag_tag::add_item_tag('core_question', 'question', $question3->id, context_course::instance($course->id), 'adpq_7');

        core_tag_tag::add_item_tag('core', 'course', $course->id, context_course::instance($course->id), 'adpq_5');

        $map = tags_repository::get_question_level_to_tag_id_mapping_by_tag_names(
            ['adpq_5','adpq_6',]
        );

        $questionstags = core_tag_tag::get_items_tags('core_question', 'question', [$question1->id, $question2->id]);

        $tagidlist = array_map(function(array $itemtags): int {
            reset($itemtags);

            return $itemtags[key($itemtags)]->id;
        }, $questionstags);

        $this->assertEquals(2, count($map));
        $this->assertEquals(
            [5 => array_shift($tagidlist), 6 => array_shift($tagidlist),],
            $map
        );
    }

    /**
     * @test
     */
    public function it_fails_to_get_question_level_to_tag_id_mapping_for_an_empty_array_of_tag_names(): void {
        $this->expectException(coding_exception::class);
        tags_repository::get_question_level_to_tag_id_mapping_by_tag_names([]);
    }

    /**
     * @test
     */
    public function it_gets_tag_id_list_by_tag_names(): void {
        $this->resetAfterTest();

        $this->assertEquals(
            [],
            tags_repository::get_tag_id_list_by_tag_names(
                ['adpq_10','adpq_11',]
            )
        );

        $generator = $this->getDataGenerator();
        /** @var  core_question_generator $questionsgenerator */
        $questionsgenerator = $generator->get_plugin_generator('core_question');

        $course = $generator->create_course();
        $questionscat = $questionsgenerator->create_question_category(
            ['contextid' => context_course::instance($course->id)->id,]
        );

        $question1 = $questionsgenerator->create_question('truefalse', null, ['category' => $questionscat->id]);
        core_tag_tag::add_item_tag('core_question', 'question', $question1->id, context_course::instance($course->id), 'adpq_10');

        $question2 = $questionsgenerator->create_question('shortanswer', null, ['category' => $questionscat->id]);
        core_tag_tag::add_item_tag('core_question', 'question', $question2->id, context_course::instance($course->id), 'adpq_11');

        $question3 = $questionsgenerator->create_question('truefalse', null, ['category' => $questionscat->id]);
        core_tag_tag::add_item_tag('core_question', 'question', $question3->id, context_course::instance($course->id), 'adpq_12');

        core_tag_tag::add_item_tag('core', 'course', $course->id, context_course::instance($course->id), 'adpq_10');

        $tagidlist = tags_repository::get_tag_id_list_by_tag_names(
            ['adpq_10','adpq_11',]
        );

        $questionstags = core_tag_tag::get_items_tags('core_question', 'question', [$question1->id, $question2->id]);

        $itemtagidlist = array_map(function(array $itemtags): int {
            reset($itemtags);

            return $itemtags[key($itemtags)]->id;
        }, $questionstags);

        $this->assertEquals(2, count($tagidlist));
        $this->assertEquals(
            [0 => array_shift($itemtagidlist), 1 => array_shift($itemtagidlist),],
            $tagidlist
        );
    }

    /**
     * @test
     */
    public function it_fails_to_get_a_list_of_tag_id_for_an_empty_array_of_tag_names(): void {
        $this->expectException(coding_exception::class);
        tags_repository::get_tag_id_list_by_tag_names([]);
    }
}
