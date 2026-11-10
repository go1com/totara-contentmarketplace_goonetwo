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
 * @author Sergey Vidusov <sergey.vidusov@androgogic.com>
 * @author Kirill Astashov <kirill.astashov@androgogic.com>
 * @package contentmarketplace_goonetwo
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

// The admin tree has no settings page for a disabled marketplace, e.g. after a migration back to the core Go1 plugin.
if (!\totara_contentmarketplace\plugininfo\contentmarketplace::plugin('goonetwo')->is_enabled()) {
    require_login();
    require_capability('totara/contentmarketplace:config', context_system::instance());

    [$status_message, $status_type] = \contentmarketplace_goonetwo\migration::pop_status_message() ?? [null, null];
    redirect(new moodle_url('/totara/contentmarketplace/marketplaces.php'), $status_message, null, $status_type);
}

// admin_externalpage_setup('contentmarketplace_goonetwo_page');
\core\setting\page\externalpage::setup(null, 'contentmarketplace_goonetwo_page');

$context = context_system::instance();
require_capability('totara/contentmarketplace:config', $context);

$tab = optional_param('tab', 'general', PARAM_ALPHAEXT);

$tabs = array();
$basepage = '/totara/contentmarketplace/contentmarketplaces/goonetwo/config.php';
$pages = array(
    'general',
    'content_sync',
    'retired_content',
    'content_access',
    'webhook',
    'migration'
);

foreach ($pages as $page) {
    $tabs[] = new tabobject(
        $page,
        new moodle_url($basepage, array(
            'tab' => $page,
        )),
        get_string($page, 'contentmarketplace_goonetwo')
    );
}

$file = $CFG->dirroot . '/totara/contentmarketplace/contentmarketplaces/goonetwo/tabs/' . $tab . '.php';
if (!in_array($tab, $pages) || !is_file($file)) {
    redirect($basepage);
}

// The tab runs before the page header, so that it can redirect after it processes a POST.
ob_start();
include($file);
$tab_html = ob_get_clean();

echo $OUTPUT->header();
echo $OUTPUT->heading(new lang_string('goonetwosettings', 'contentmarketplace_goonetwo'));
echo $OUTPUT->tabtree($tabs, $tab);
echo $tab_html;
echo $OUTPUT->footer();