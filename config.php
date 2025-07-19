<?php
// =============================================================================
// FIXED FILE: config.php (Add missing defaultregion keys)
// =============================================================================
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'iomadremui';
$THEME->sheets = ['remui', 'iomad', 'tenant'];
$THEME->editor_sheets = ['editor'];
$THEME->usescourseindex = true;

// Parent theme (inherit from RemUI if available, otherwise Boost)
$THEME->parents = ['remui', 'boost'];

// Enable dock for blocks
$THEME->enable_dock = true;

// Page layouts - FIXED with defaultregion for all layouts
$THEME->layouts = [
    'base' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'standard' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'course' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'coursecategory' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'incourse' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'frontpage' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => true],
    ],
    'admin' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'mydashboard' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['nonavbar' => true, 'langmenu' => true],
    ],
    'mypublic' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'login' => [
        'file' => 'login.php',
        'regions' => [],
        'defaultregion' => '',
        'options' => ['langmenu' => true, 'nonavbar' => true],
    ],
    'popup' => [
        'file' => 'popup.php',
        'regions' => [],
        'defaultregion' => '',
        'options' => ['nofooter' => true, 'nonavbar' => true],
    ],
    'frametop' => [
        'file' => 'columns1.php',
        'regions' => [],
        'defaultregion' => '',
        'options' => ['nofooter' => true, 'nocoursefooter' => true],
    ],
    'embedded' => [
        'file' => 'embedded.php',
        'regions' => [],
        'defaultregion' => '',
    ],
    'maintenance' => [
        'file' => 'maintenance.php',
        'regions' => [],
        'defaultregion' => '',
    ],
    'print' => [
        'file' => 'columns1.php',
        'regions' => [],
        'defaultregion' => '',
        'options' => ['nofooter' => true, 'nonavbar' => false],
    ],
    'redirect' => [
        'file' => 'embedded.php',
        'regions' => [],
        'defaultregion' => '',
    ],
    'report' => [
        'file' => 'columns2.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'secure' => [
        'file' => 'secure.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
];

$THEME->csspostprocess = 'theme_iomadremui_process_css';
$THEME->extrascsscallback = 'theme_iomadremui_get_extra_scss';
$THEME->prescsscallback = 'theme_iomadremui_get_pre_scss';
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;

// Additional theme configuration to prevent warnings
$THEME->blockrtlmanipulations = [
    'side-pre' => 'side-post',
    'side-post' => 'side-pre'
];

// Set up supported layout types
$THEME->supportscssoptimisation = false;
$THEME->supportsflatnavigation = true;