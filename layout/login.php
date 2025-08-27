<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/iomad/lib/company.php');

// Simple tenant config class for backward compatibility
if (!class_exists('theme_iomadremui_tenant_config')) {
    class theme_iomadremui_tenant_config {
        private $companyid;
        private $config;
        
        public function __construct($companyid) {
            $this->companyid = $companyid;
            $this->config = theme_iomadremui_get_company_config($companyid);
        }
        
        public function get_config($key, $default = null) {
            return isset($this->config[$key]) ? $this->config[$key] : $default;
        }
    }
}

// IOMAD: Early company context initialization - SAFE CALL
if (function_exists('theme_iomadremui_init_company_context')) {
    theme_iomadremui_init_company_context();
}

// IOMAD: Handle company domain redirects - SAFE CALL
if (function_exists('theme_iomadremui_handle_company_domain_redirect')) {
    theme_iomadremui_handle_company_domain_redirect();
}

// Get company context using IOMAD's URL patterns
$companyid = 0;
if (function_exists('theme_iomadremui_get_company_from_iomad_url')) {
    $companyid = theme_iomadremui_get_company_from_iomad_url();
}

$companyconfig = null;
if ($companyid) {
    $companyconfig = new theme_iomadremui_tenant_config($companyid);
}

// Set up RemUI-style extra classes
$extraclasses = array();

// Add RemUI utility classes if available
if (class_exists('\theme_remui\utility')) {
    $extraclasses[] = \theme_remui\utility::get_main_bg_class();
}

// Add login layout from RemUI or fallback
$loginlayout = get_config('theme_remui', 'loginpagelayout') ?: 'logindefault';
$extraclasses[] = $loginlayout;

// Add company-specific classes
if ($companyid) {
    $extraclasses[] = 'company-' . $companyid;
    
    // Add company-specific login style if configured
    $companylayoutstyle = $companyconfig->get_config('login_style', 'default');
    if ($companylayoutstyle !== 'default') {
        $extraclasses[] = 'login-style-' . $companylayoutstyle;
    }
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

// OPTION 1: Disable RemUI fonts on login page to avoid issues
$fonts_html = '';

// OPTION 2: Alternative - only load fonts if explicitly configured for company
if ($companyconfig) {
    $custom_font = $companyconfig->get_config('fontfamily');
    if (!empty($custom_font) && $custom_font !== 'inherit') {
        $fonts_html = '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $fonts_html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        $fonts_html .= '<link href="https://fonts.googleapis.com/css2?family=' . urlencode($custom_font) . ':wght@300;400;500;600;700&display=swap" rel="stylesheet">' . "\n";
    }
}

// Base template context (RemUI compatible)
$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'fonts' => $fonts_html, // FIXED: Pass as string instead of array
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes
];

// Get branding context (RemUI style)
if (method_exists($OUTPUT, 'get_branding_context')) {
    $templatecontext['logocontext'] = $OUTPUT->get_branding_context();
} else {
    // Fallback branding context
    $templatecontext['logocontext'] = [
        'logourl' => $OUTPUT->get_logo_url(),
        'sitename' => $SITE->shortname,
        'incontainer' => true
    ];
}

// Add company-specific login customizations
if ($companyconfig) {
    $login_style = $companyconfig->get_config('login_style', 'default');
    
    $companydata = [
        'id' => $companyid,
        'logo' => $companyconfig->get_config('login_logo') ?: $companyconfig->get_config('logo'),
        'background' => function_exists('theme_iomadremui_get_login_background_url') ? 
                       theme_iomadremui_get_login_background_url($companyid) : null,
        'welcome_message' => $companyconfig->get_config('welcome_message'),
        'login_tagline' => $companyconfig->get_config('login_tagline'),
        'login_style' => $login_style,
        'primarycolor' => $companyconfig->get_config('primarycolor', '#007bff'),
        'signuptextcolor' => $companyconfig->get_config('signuptextcolor', '#ffffff'),
        'has_background' => function_exists('theme_iomadremui_get_login_background_url') ? 
                           !empty(theme_iomadremui_get_login_background_url($companyid)) : false,
        
        // FIXED: Add boolean flags for Mustache template
        'is_split_style' => ($login_style === 'split'),
        'is_centered_style' => ($login_style === 'centered'),
        'is_default_style' => ($login_style === 'default' || empty($login_style)),
    ];
    
    $templatecontext['company'] = $companydata;
    
    // Override default branding with company branding
    if (!empty($companydata['logo'])) {
        $templatecontext['logocontext']['logourl'] = $companydata['logo'];
        $templatecontext['logocontext']['incontainer'] = true;
    }
    
    // Set signup text color for company
    $templatecontext['signuptextcolor'] = $companydata['signuptextcolor'];
    
    // Company description/tagline logic (RemUI style)
    if ($loginlayout != 'logincenter') {
        $templatecontext['canshowdesc'] = true;
        
        // Use company tagline if available, otherwise fall back to RemUI setting
        $brandlogotext = $companyconfig->get_config('login_tagline');
        if (empty($brandlogotext)) {
            $brandlogotext = get_config('theme_remui', 'brandlogotext');
        }
        
        if (!empty($brandlogotext)) {
            $templatecontext['brandlogotext'] = format_text($brandlogotext, FORMAT_HTML, array("noclean" => true));
        }
    }
    
} else {
    // Use default RemUI settings when no company context
    $templatecontext['signuptextcolor'] = get_config('theme_remui', 'signuptextcolor');
    
    if ($loginlayout != 'logincenter') {
        $templatecontext['canshowdesc'] = true;
        $brandlogotext = get_config('theme_remui', 'brandlogotext');
        if (!empty($brandlogotext)) {
            $templatecontext['brandlogotext'] = format_text($brandlogotext, FORMAT_HTML, array("noclean" => true));
        }
    }
}

// Enable accessibility widgets if RemUI utility is available
if (class_exists('\theme_remui\utility')) {
    \theme_remui\utility::enable_edw_aw_menu();
}

// Render the login template with RemUI compatibility
echo $OUTPUT->render_from_template('theme_iomadremui/login', $templatecontext);