<?php
// layout/frontpage.php - Minimal override just for redirect
defined('MOODLE_INTERNAL') || die();

// FRONTPAGE REDIRECT: Always redirect logged-in users to dashboard
if (isloggedin() && !isguestuser()) {
    // Don't redirect if user is logging out or during specific actions
    if (!optional_param('logout', false, PARAM_BOOL) && 
        strpos($_SERVER['REQUEST_URI'], 'login') === false &&
        !optional_param('embedded', false, PARAM_BOOL)) {
        
        // Check for important parameters that should prevent redirect
        $allowedparams = ['redirect', 'sesskey', 'lang'];
        $hasotherparams = false;
        
        foreach ($_GET as $param => $value) {
            if (!in_array($param, $allowedparams)) {
                $hasotherparams = true;
                break;
            }
        }
        
        if (!$hasotherparams) {
            // Get dashboard URL (can be company-specific)
            $dashboardurl = new moodle_url('/my/');
            
            // Check for company-specific dashboard
            if (function_exists('iomad::get_my_companyid')) {
                try {
                    $companyid = iomad::get_my_companyid(context_system::instance());
                    if ($companyid) {
                        $tenantconfig = new \theme_iomadremui\tenant_config($companyid);
                        $customdashboard = $tenantconfig->get_config('custom_dashboard_url');
                        if ($customdashboard) {
                            $dashboardurl = new moodle_url($customdashboard);
                        }
                    }
                } catch (Exception $e) {
                    // Use default dashboard if there's any error
                }
            }
            
            redirect($dashboardurl);
        }
    }
}

// If we reach here, either user is not logged in or shouldn't be redirected
// Use RemUI's frontpage layout
$remui_frontpage_paths = [
    $CFG->dirroot . '/theme/remui/layout/frontpage.php',
    $CFG->dirroot . '/theme/remui/layout/columns2.php',
    $CFG->dirroot . '/theme/remui/layout/drawers.php',
    $CFG->dirroot . '/theme/remui/layout/default.php',
];

$layout_found = false;
foreach ($remui_frontpage_paths as $path) {
    if (file_exists($path)) {
        include($path);
        $layout_found = true;
        break;
    }
}

// Fallback to Boost if RemUI layout not found
if (!$layout_found) {
    $boost_paths = [
        $CFG->dirroot . '/theme/boost/layout/frontpage.php',
        $CFG->dirroot . '/theme/boost/layout/drawers.php',
        $CFG->dirroot . '/theme/boost/layout/columns2.php',
    ];
    
    foreach ($boost_paths as $path) {
        if (file_exists($path)) {
            include($path);
            break;
        }
    }
}