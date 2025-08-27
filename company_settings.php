<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/iomad/lib/company.php');

$companyid = required_param('companyid', PARAM_INT);
$tab = optional_param('tab', 'basic', PARAM_ALPHA);

// Security checks
require_login();
$context = context_system::instance();

// Use correct IOMAD capability check - check multiple possible capabilities
$canmanage = false;

// Check if user is site admin
if (has_capability('moodle/site:config', $context)) {
    $canmanage = true;
}

// Check IOMAD company management capabilities
if (!$canmanage) {
    $iomadcaps = [
        'block/iomad_company_admin:company_add',
        'block/iomad_company_admin:company_edit', 
        'block/iomad_company_admin:company_view',
        'block/iomad_company_admin:companymanagement_view'
    ];
    
    foreach ($iomadcaps as $cap) {
        if (iomad::has_capability($cap, $context)) {
            $canmanage = true;
            break;
        }
    }
}

if (!$canmanage) {
    print_error('noaccess', 'theme_iomadremui');
}

// Verify company access using correct IOMAD method
$usercompanies = theme_iomadremui_get_user_companies();
$hasaccess = false;

// Site admins can access any company
if (has_capability('moodle/site:config', $context)) {
    $hasaccess = true;
} else {
    // Check if user belongs to this company
    foreach ($usercompanies as $usercompany) {
        if ($usercompany->id == $companyid) {
            $hasaccess = true;
            break;
        }
    }
}

if (!$hasaccess) {
    print_error('noaccess', 'theme_iomadremui');
}

$company = $DB->get_record('company', ['id' => $companyid], '*', MUST_EXIST);

// Set up page
$PAGE->set_url('/theme/iomadremui/company_settings.php', ['companyid' => $companyid, 'tab' => $tab]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('editcompanysettings', 'theme_iomadremui'));
$PAGE->set_heading(get_string('editcompanysettings', 'theme_iomadremui'));
$PAGE->navbar->add($company->name);
$PAGE->navbar->add(get_string('editcompanysettings', 'theme_iomadremui'));

// Handle form submission
if (data_submitted() && confirm_sesskey()) {
    $data = data_submitted();
    \theme_iomadremui\settings_manager::process_company_settings($companyid, (array)$data);
    redirect($PAGE->url, get_string('settingssaved', 'core'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Render company settings form
echo \theme_iomadremui\settings_manager::render_company_settings_form($companyid, $tab);

echo $OUTPUT->footer();