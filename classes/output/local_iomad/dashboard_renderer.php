<?php
namespace theme_iomadremui\output\local_iomad;

use moodle_url;
use html_writer;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/iomad/lib/company.php');

/**
 * IOMAD Dashboard renderer for RemUI theme
 */
class dashboard_renderer extends \plugin_renderer_base {
    
    /**
     * Get company context for current user
     */
     private function get_company_context() {
        static $companycontext = null;
        
        if ($companycontext === null) {
            $companycontext = [];
            
            if (isloggedin()) {
                // Use global namespace for iomad class
                $companyid = \iomad::get_my_companyid(\context_system::instance());
                if ($companyid) {
                    $tenantconfig = new \theme_iomadremui\tenant_config($companyid);
                    $companycontext = [
                        'companyid' => $companyid,
                        'config' => $tenantconfig,
                        'dashboard_layout' => $tenantconfig->get_config('dashboard_layout', 'grid'),
                        'show_analytics' => $tenantconfig->get_config('show_analytics', 1),
                        'show_quick_access' => $tenantconfig->get_config('show_quick_access', 1),
                        'dashboard_widgets' => $tenantconfig->get_config('dashboard_widgets', ''),
                    ];
                }
            }
        }
        
        return $companycontext;
    }
    
    /**
     * Render the main IOMAD dashboard
     *
     * @param array $dashboarddata
     * @return string HTML
     */
    public function render_dashboard($dashboarddata = []) {
        $companycontext = $this->get_company_context();
        
        $output = html_writer::start_div('iomad-dashboard');
        
        // Add company branding
        if (!empty($companycontext['companyid'])) {
            $output .= $this->render_company_header($companycontext);
        }
        
        // Dashboard content based on layout
        $layout = $companycontext['dashboard_layout'] ?? 'grid';
        $output .= $this->render_dashboard_content($dashboarddata, $layout, $companycontext);
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render company header for dashboard
     */
    private function render_company_header($companycontext) {
        global $DB;
        
        $company = $DB->get_record('company', ['id' => $companycontext['companyid']]);
        if (!$company) {
            return '';
        }
        
        $output = html_writer::start_div('dashboard-company-header');
        
        // Company logo
        $logo = $companycontext['config']->get_config('logo');
        if ($logo) {
            $output .= html_writer::img($logo, $company->name, [
                'class' => 'company-logo',
                'style' => 'max-height: 60px;'
            ]);
        }
        
        // Company name and info
        $output .= html_writer::start_div('company-info');
        $output .= html_writer::tag('h2', $company->name, ['class' => 'company-name']);
        if (!empty($company->shortname)) {
            $output .= html_writer::tag('p', $company->shortname, ['class' => 'company-shortname']);
        }
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render dashboard content based on layout
     */
    private function render_dashboard_content($data, $layout, $companycontext) {
        $output = '';
        
        // Add layout-specific CSS class
        $classes = ['dashboard-content', 'layout-' . $layout];
        if (!empty($companycontext['companyid'])) {
            $classes[] = 'company-' . $companycontext['companyid'];
        }
        
        $output .= html_writer::start_div(implode(' ', $classes));
        
        // Quick access panel
        if ($companycontext['show_quick_access']) {
            $output .= $this->render_quick_access_panel($companycontext);
        }
        
        // Analytics section
        if ($companycontext['show_analytics']) {
            $output .= $this->render_analytics_section($data, $companycontext);
        }
        
        // Custom widgets
        $widgets = $companycontext['dashboard_widgets'];
        if (!empty($widgets)) {
            $output .= $this->render_custom_widgets($widgets, $companycontext);
        }
        
        // Default IOMAD dashboard components
        $output .= $this->render_default_dashboard_components($data);
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render quick access panel
     */
    private function render_quick_access_panel($companycontext) {
        $output = html_writer::start_div('quick-access-panel');
        $output .= html_writer::tag('h3', get_string('quickaccess', 'theme_iomadremui'));
        
        $output .= html_writer::start_div('quick-access-items');
        
        $context = \context_system::instance();
        
        // Company management link - use correct capability and global namespace
        if (\iomad::has_capability('block/iomad_company_admin:company_edit', $context)) {
            $url = new moodle_url('/local/iomad/company_edit_form.php', ['companyid' => $companycontext['companyid']]);
            $output .= $this->render_quick_access_item(
                $url,
                get_string('editcompany', 'local_iomad'),
                'fa-building'
            );
        }
        
        // User management link - use correct capability and global namespace
        if (\iomad::has_capability('block/iomad_company_admin:editusers', $context)) {
            $url = new moodle_url('/local/iomad/company_users.php', ['companyid' => $companycontext['companyid']]);
            $output .= $this->render_quick_access_item(
                $url,
                get_string('manageusers', 'local_iomad'),
                'fa-users'
            );
        }
        
        // Course management link - use correct capability and global namespace
        if (\iomad::has_capability('block/iomad_company_admin:company_course_users', $context)) {
            $url = new moodle_url('/local/iomad/company_courses.php', ['companyid' => $companycontext['companyid']]);
            $output .= $this->render_quick_access_item(
                $url,
                get_string('managecourses', 'local_iomad'),
                'fa-book'
            );
        }
        
        // Theme settings link - use correct capability and global namespace
        if (\iomad::has_capability('block/iomad_company_admin:company_edit', $context)) {
            $url = new moodle_url('/theme/iomadremui/company_settings.php', ['companyid' => $companycontext['companyid']]);
            $output .= $this->render_quick_access_item(
                $url,
                get_string('themesettings', 'theme_iomadremui'),
                'fa-paint-brush'
            );
        }
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render a quick access item
     */
    private function render_quick_access_item($url, $title, $icon) {
        $output = html_writer::start_div('quick-access-item');
        $output .= html_writer::link($url, 
            html_writer::tag('i', '', ['class' => 'fa ' . $icon]) . ' ' . $title,
            ['class' => 'btn btn-outline-primary']
        );
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render analytics section
     */
    private function render_analytics_section($data, $companycontext) {
        global $DB;
        
        $output = html_writer::start_div('analytics-section');
        $output .= html_writer::tag('h3', get_string('analytics', 'theme_iomadremui'));
        
        $companyid = $companycontext['companyid'];
        
        // Get analytics data
        $usercount = $DB->count_records('company_users', ['companyid' => $companyid]);
        $coursecount = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {iomad_courses} WHERE companyid = ?", 
            [$companyid]
        );
        
        $output .= html_writer::start_div('analytics-cards');
        
        // Users card
        $output .= $this->render_analytics_card(
            get_string('totalusers', 'theme_iomadremui'),
            $usercount,
            'fa-users',
            'primary'
        );
        
        // Courses card
        $output .= $this->render_analytics_card(
            get_string('totalcourses', 'theme_iomadremui'),
            $coursecount,
            'fa-book',
            'success'
        );
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render an analytics card
     */
    private function render_analytics_card($title, $value, $icon, $color) {
        $output = html_writer::start_div('analytics-card card border-' . $color);
        $output .= html_writer::start_div('card-body');
        
        $output .= html_writer::start_div('d-flex align-items-center');
        $output .= html_writer::div(
            html_writer::tag('i', '', ['class' => 'fa ' . $icon . ' fa-2x text-' . $color]),
            'analytics-icon'
        );
        
        $output .= html_writer::start_div('analytics-content');
        $output .= html_writer::tag('h4', $value, ['class' => 'analytics-value']);
        $output .= html_writer::tag('p', $title, ['class' => 'analytics-title text-muted']);
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render custom widgets
     */
    private function render_custom_widgets($widgets, $companycontext) {
        if (empty($widgets)) {
            return '';
        }
        
        $output = html_writer::start_div('custom-widgets');
        $output .= html_writer::tag('h3', get_string('customwidgets', 'theme_iomadremui'));
        
        // Parse widgets (assuming JSON format)
        $widgetdata = json_decode($widgets, true);
        if ($widgetdata) {
            foreach ($widgetdata as $widget) {
                $output .= $this->render_custom_widget($widget);
            }
        } else {
            // Fallback: treat as HTML
            $output .= html_writer::div($widgets, 'widget-content');
        }
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render a single custom widget
     */
    private function render_custom_widget($widget) {
        $output = html_writer::start_div('custom-widget card');
        
        if (!empty($widget['title'])) {
            $output .= html_writer::div($widget['title'], 'card-header');
        }
        
        $output .= html_writer::start_div('card-body');
        if (!empty($widget['content'])) {
            $output .= $widget['content'];
        }
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render default IOMAD dashboard components
     */
    private function render_default_dashboard_components($data) {
        // This would integrate with existing IOMAD dashboard components
        // For now, just return a placeholder
        return html_writer::div(
            get_string('defaultdashboard', 'theme_iomadremui'),
            'default-dashboard-components'
        );
    }
}
