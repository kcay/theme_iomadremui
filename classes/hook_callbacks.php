<?php
namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for theme_iomadremui
 */
class hook_callbacks {
    
    /**
     * Callback for before footer HTML generation
     * Migrated from theme_iomadremui_before_footer()
     * 
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $CFG;
        
        $companyid = theme_iomadremui_get_company_id_for_file_serving();
        
        if ($companyid) {
            $tenantconfig = new \theme_iomadremui\tenant_config($companyid);
            $courselayout = $tenantconfig->get_config('course_layout', 'card');
            $loginstyle = $tenantconfig->get_config('login_style', 'default');
            
            $js = "
            // Add IOMAD company body classes via JavaScript
            document.addEventListener('DOMContentLoaded', function() {
                var body = document.body;
                if (body) {
                    body.classList.add('company-{$companyid}');
                    body.classList.add('course-layout-{$courselayout}');
                    
                    // Add login style class for login pages
                    if (body.classList.contains('pagelayout-login')) {
                        body.classList.add('login-style-{$loginstyle}');
                    }
                    
                    // Also add to containers that might need company styling
                    var containers = document.querySelectorAll('.frontpage-course-list-all, .course-content, .coursecat-content');
                    containers.forEach(function(container) {
                        container.classList.add('company-{$companyid}');
                    });
                    
                    // Special handling for IOMAD login detection
                    var urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('id') === '{$companyid}' || urlParams.get('companyid') === '{$companyid}') {
                        body.classList.add('iomad-company-{$companyid}');
                    }
                }
            });
            ";
            
            $PAGE->requires->js_init_code($js);
        }
    }
}
