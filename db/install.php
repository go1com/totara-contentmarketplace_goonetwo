<?php
/*
 * This file is part of Totara Learn
 *
 * Copyright (C) 2018 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Michael Dunstan <michael.dunstan@androgogic.com>
 * @package contentmarketplace_goonetwo
 */

/**
 * Totara workflow install hook.
 */
function xmldb_contentmarketplace_goonetwo_install() {
    // Enable GO1 course create workflow on install.
    $workflow = contentmarketplace_goonetwo\workflow\core_course\coursecreate\contentmarketplace::instance();
    $workflow->enable();

    // Enable goonetwo Explore marketplace workflow on install.
    $workflow = contentmarketplace_goonetwo\workflow\totara_contentmarketplace\exploremarketplace\goonetwo::instance();
    $workflow->enable();

    // Enable GO1 activity creation workflow on install.
    $workflow = contentmarketplace_goonetwo\workflow\mod_contentmarketplace\create_marketplace_activity\goonetwo::instance();
    $workflow->enable();

    // Manual course creation is off by default, the course creation flow redirects to content curation.
    set_config('create_course_manual', contentmarketplace_goonetwo\helper::CREATE_COURSE_MANUAL_DISABLED, 'contentmarketplace_goonetwo');
}
