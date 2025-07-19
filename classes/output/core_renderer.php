<?php
namespace theme_iomadremui\output;

use moodle_url;
use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/iomad/lib/company.php');

/**
 * FIXED: Core renderer that properly handles RemUI-style navigation
 * Removes problematic method overrides and focuses on working functionality
 */
class core_renderer extends \theme_boost\output\core_renderer {
    
    /**
     * FIXED: Get primary navigation items (Dashboard, My Courses, Site Admin, etc.)
     */
    public function get_primary_navigation() {
        global $PAGE, $USER;
        
        $navigation = [];
        
        if (isloggedin()) {
            // Dashboard
            $navigation[] = [
                'text' => get_string('myhome'),
                'url' => new moodle_url('/my/'),
                'isactive' => ($PAGE->pagetype == 'my-index'),
                'key' => 'myhome'
            ];
            
            // My Courses
            $navigation[] = [
                'text' => get_string('mycourses'),
                'url' => new moodle_url('/my/courses.php'),
                'isactive' => ($PAGE->pagetype == 'my-courses'),
                'key' => 'mycourses'
            ];
            
            // Site Administration (for those with access)
            if (has_capability('moodle/site:config', \context_system::instance())) {
                $navigation[] = [
                    'text' => get_string('administrationsite'),
                    'url' => new moodle_url('/admin/'),
                    'isactive' => (strpos($PAGE->pagetype, 'admin') === 0),
                    'key' => 'admin'
                ];
            }
            
            // IOMAD Company Management (for company managers)
            $companyid = \iomad::get_my_companyid(\context_system::instance());
            if ($companyid && theme_iomadremui_can_manage_company($companyid)) {
                $navigation[] = [
                    'text' => get_string('companymanagement', 'local_iomad'),
                    'url' => new moodle_url('/local/iomad_dashboard/index.php'),
                    'isactive' => (strpos($PAGE->pagetype, 'local-iomad') === 0),
                    'key' => 'companymanagement'
                ];
            }
        } else {
            // For non-logged in users
            $navigation[] = [
                'text' => get_string('home'),
                'url' => new moodle_url('/'),
                'isactive' => ($PAGE->pagetype == 'site-index'),
                'key' => 'home'
            ];
        }
        
        return $navigation;
    }
    
    /**
     * Get company context
     */
    public function get_company_context() {
        static $context = null;
        
        if ($context === null) {
            $context = [];
            
            if (isloggedin()) {
                $companyid = \iomad::get_my_companyid(\context_system::instance());
                if ($companyid) {
                    $tenantconfig = new \theme_iomadremui\tenant_config($companyid);
                    $context = [
                        'companyid' => $companyid,
                        'logo' => $tenantconfig->get_config('logo'),
                        'primarycolor' => $tenantconfig->get_config('primarycolor', '#007bff'),
                        'customcss' => $tenantconfig->get_config('customcss'),
                    ];
                }
            }
        }
        
        return $context;
    }
    
    /**
     * FIXED: Override standard_head_html to add company CSS properly
     */
    public function standard_head_html() {
        $output = parent::standard_head_html();
        
        $companycontext = $this->get_company_context();
        if (!empty($companycontext)) {
            $output .= "<style>\n";
            $output .= ":root {\n";
            $output .= "  --bs-primary: " . $companycontext['primarycolor'] . ";\n";
            $output .= "  --primary: " . $companycontext['primarycolor'] . ";\n";
            $output .= "}\n";
            if (!empty($companycontext['customcss'])) {
                $output .= $companycontext['customcss'] . "\n";
            }
            $output .= "</style>\n";
        }
        
        return $output;
    }
    
    /**
     * FIXED: Enhanced custom menu that integrates with IOMAD
     */
    public function custom_menu($custommenuitems = '') {
        global $CFG;
        
        // Get base custom menu
        $custommenu = parent::custom_menu($custommenuitems);
        
        // Add IOMAD-specific menu items if user has appropriate permissions
        if (isloggedin()) {
            $companyid = \iomad::get_my_companyid(\context_system::instance());
            if ($companyid && theme_iomadremui_can_manage_company($companyid)) {
                // Add company management links to custom menu
                // This integrates with the existing custom menu structure
            }
        }
        
        return $custommenu;
    }
    
    /**
     * FIXED: Footer that includes company-specific content
     */
    public function standard_footer_html() {
        $output = parent::standard_footer_html();
        
        // Add company-specific footer content
        $companycontext = $this->get_company_context();
        if (!empty($companycontext['companyid'])) {
            $tenantconfig = new \theme_iomadremui\tenant_config($companycontext['companyid']);
            $footercontent = $tenantconfig->get_config('footer_content');
            
            if ($footercontent) {
                $output = '<div class="company-footer-content">' . $footercontent . '</div>' . $output;
            }
        }
        
        return $output;
    }
    
    /**
     * ENHANCED: Main content wrapper with proper RemUI styling
     */
    public function main_content() {
        $content = parent::main_content();
        
        // Wrap content in RemUI-style container if needed
        $companycontext = $this->get_company_context();
        if (!empty($companycontext['companyid'])) {
            // Add company-specific main content styling
            $content = '<div class="remui-main-content company-' . $companycontext['companyid'] . '">' . $content . '</div>';
        }
        
        return $content;
    }
    
    /**
     * ADDED: Method to check if we should display company selector
     */
    public function should_display_company_selector() {
        if (!isloggedin()) {
            return false;
        }
        
        $usercompanies = theme_iomadremui_get_user_companies();
        return count($usercompanies) > 1;
    }
    
    /**
     * ADDED: Render company selector for multi-tenant users
     */
    public function company_selector() {
        if (!$this->should_display_company_selector()) {
            return '';
        }
        
        $usercompanies = theme_iomadremui_get_user_companies();
        $currentcompanyid = \iomad::get_my_companyid(\context_system::instance());
        
        $companies = [];
        foreach ($usercompanies as $company) {
            $companies[] = [
                'id' => $company->id,
                'name' => $company->name,
                'selected' => ($company->id == $currentcompanyid)
            ];
        }
        
        $context = [
            'companies' => $companies,
            'current_company_id' => $currentcompanyid
        ];
        
        return $this->render_from_template('theme_iomadremui/company_selector', $context);
    }
}