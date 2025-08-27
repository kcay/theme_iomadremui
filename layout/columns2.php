<?php
// FIXED layout/columns2.php - Show error badge instead of redirecting
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/iomad/lib/company.php');

// Handle company switching ONLY if explicitly requested via POST
if (data_submitted() && optional_param('switch_company', false, PARAM_BOOL) && confirm_sesskey()) {
    $newcompanyid = required_param('companyid', PARAM_INT);
    
    // Validate user has access to this company
    $usercompanies = theme_iomadremui_get_user_companies();
    $hasaccess = false;
    foreach ($usercompanies as $company) {
        if ($company->id == $newcompanyid) {
            $hasaccess = true;
            break;
        }
    }
    
    if ($hasaccess) {
        // Set company in session and redirect to same page
        $_SESSION['currenteditingcompany'] = $newcompanyid;
        redirect($PAGE->url);
    }
}

// FIXED: Check company selection status SAFELY
$companyid = 0;
$companyconfig = null;
$company_selection_required = false;
$company_selection_message = '';

if (isloggedin()) {
    try {
        // Check session first
        if (!empty($_SESSION['currenteditingcompany'])) {
            $companyid = $_SESSION['currenteditingcompany'];
        } else {
            // Get user's companies to determine if selection is required
            $usercompanies = theme_iomadremui_get_user_companies();
            
            if (count($usercompanies) > 1) {
                // User has multiple companies - selection required
                $company_selection_required = true;
                $company_selection_message = get_string('pleaseselect', 'block_iomad_company_admin');
            } else if (count($usercompanies) == 1) {
                // User has only one company - auto-select it
                $company = reset($usercompanies);
                $companyid = $company->id;
                $_SESSION['currenteditingcompany'] = $companyid;
            }
            // If count($usercompanies) == 0, user has no companies - continue without company context
        }
        
        if ($companyid) {
            $companyconfig = new \theme_iomadremui\tenant_config($companyid);
        }
    } catch (Exception $e) {
        debugging('Error getting company context: ' . $e->getMessage());
        $companyid = 0;
        $companyconfig = null;
    }
}

// Rest of layout code...
$extraclasses = [];
if ($companyid) {
    $extraclasses[] = 'company-' . $companyid;
}

if (isloggedin()) {
    $rightdraweropen = (get_user_preferences('drawer-open-blocks', 'false') == 'true');
} else {
    $rightdraweropen = false;
}

if ($rightdraweropen) {
    $extraclasses[] = 'drawer-open-right';
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = strpos($blockshtml, 'data-block=') !== false;
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$containerclass = 'container';
if ($companyconfig) {
    $containerclass = $companyconfig->get_config('container_class', 'container');
}

$navigationdata = theme_iomadremui_get_navigation_data($PAGE, $OUTPUT);

// Primary navigation - no problematic capability checks
$primarynavigation = [];
if (isloggedin()) {
    $primarynavigation = [
        [
            'text' => get_string('myhome'),
            'url' => new moodle_url('/my/'),
            'isactive' => ($PAGE->pagetype == 'my-index'),
            'key' => 'myhome'
        ],
        [
            'text' => get_string('home'),
            'url' => new moodle_url('/'),
            'isactive' => ($PAGE->pagetype == 'site-index'),
            'key' => 'home'
        ],
        [
            'text' => get_string('mycourses'),
            'url' => new moodle_url('/my/courses.php'),
            'isactive' => ($PAGE->pagetype == 'my-courses'),
            'key' => 'mycourses'
        ]
    ];
    
    // Site admin link (safe)
    if (has_capability('moodle/site:config', context_system::instance())) {
        $primarynavigation[] = [
            'text' => get_string('administrationsite'),
            'url' => new moodle_url('/admin/'),
            'isactive' => (strpos($PAGE->pagetype, 'admin') === 0),
            'key' => 'admin'
        ];
    }
    
    // IOMAD Dashboard link (only if company is selected)
    if ($companyid) {
        $primarynavigation[] = [
            'text' => get_string('dashboard', 'local_iomad'),
            'url' => new moodle_url('/local/iomad_dashboard/index.php'),
            'isactive' => (strpos($PAGE->pagetype, 'local-iomad') === 0),
            'key' => 'iomaddashboard'
        ];
    }
}

// Secondary navigation
$secondarynavigation = null;
$overflow = null;
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

// Company-specific data
$companydata = [];
if ($companyconfig || $company_selection_required) {
    $usercompanies = theme_iomadremui_get_user_companies();
    $companies = [];
    
    if (!empty($usercompanies)) {
        foreach ($usercompanies as $company) {
            $companies[] = [
                'id' => $company->id,
                'name' => $company->name,
                'selected' => ($company->id == $companyid)
            ];
        }
    }
    
    $companydata = [
        'id' => $companyid,
        'logo' => $companyconfig ? $companyconfig->get_config('logo') : '',
        'primarycolor' => $companyconfig ? $companyconfig->get_config('primarycolor', '#007bff') : '#007bff',
        'secondarycolor' => $companyconfig ? $companyconfig->get_config('secondarycolor', '#6c757d') : '#6c757d',
        'customcss' => $companyconfig ? $companyconfig->get_config('customcss') : '',
        'companies' => $companies,
        'show_company_selector' => count($companies) > 1,
        'footer_content' => $companyconfig ? $companyconfig->get_config('footer_content') : '',
        // CRITICAL: Add company selection status
        'selection_required' => $company_selection_required,
        'selection_message' => $company_selection_message,
    ];
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'rightdraweropen' => $rightdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'primarynavigation' => $primarynavigation,
    'containerclass' => $containerclass,
    'hassecondarynav' => !empty($secondarynavigation),
    'secondarynavigation' => $secondarynavigation,
    'company' => $companydata,
    'usermenu' => $navigationdata['usermenu'],
    'langmenu' => $navigationdata['langmenu'],
    'mobileprimarynav' => $navigationdata['mobilenav'],
    'sesskey' => sesskey(),
];

echo $OUTPUT->render_from_template('theme_iomadremui/columns2', $templatecontext);


