<?php
// layout/remui_wrapper.php - Simple wrapper that uses RemUI's layout
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/iomad/lib/company.php');

// Add company context to the page for CSS processing
if (isloggedin()) {
    $companyid = iomad::get_my_companyid(context_system::instance());
    if ($companyid) {
        // Add company class to body
        $PAGE->add_body_class('company-' . $companyid);
        
        // Add company-specific CSS
        $tenantconfig = new \theme_iomadremui\tenant_config($companyid);
        $primarycolor = $tenantconfig->get_config('primarycolor', '#007bff');
        $customcss = $tenantconfig->get_config('customcss', '');
        
        if ($primarycolor || $customcss) {
            $css = ":root { --bs-primary: {$primarycolor}; --primary: {$primarycolor}; }\n{$customcss}";
            $PAGE->requires->css_theme(moodle_url::make_pluginfile_url(
                context_system::instance()->id, 'theme_iomadremui', 'css', 0, '/', 'company.css'
            ));
        }
    }
}

// Check if RemUI's layout files exist and use them
$remui_layout_paths = [
    $CFG->dirroot . '/theme/remui/layout/drawers.php',
    $CFG->dirroot . '/theme/remui/layout/columns2.php',
    $CFG->dirroot . '/theme/remui/layout/default.php',
];

$remui_layout_found = false;
foreach ($remui_layout_paths as $path) {
    if (file_exists($path)) {
        include($path);
        $remui_layout_found = true;
        break;
    }
}

// Fallback to Boost if RemUI layouts not found
if (!$remui_layout_found) {
    $boost_layout_paths = [
        $CFG->dirroot . '/theme/boost/layout/drawers.php',
        $CFG->dirroot . '/theme/boost/layout/columns2.php',
    ];
    
    foreach ($boost_layout_paths as $path) {
        if (file_exists($path)) {
            include($path);
            break;
        }
    }
}