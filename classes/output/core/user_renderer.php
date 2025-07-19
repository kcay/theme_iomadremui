<?php
namespace theme_iomadremui\output\core;

use moodle_url;
use html_writer;
use user_picture;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/renderer.php');
require_once($CFG->dirroot . '/local/iomad/lib/company.php');

/**
 * User renderer for IOMAD RemUI theme
 */
class user_renderer extends \core_user_renderer {
    
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
                        'show_company_info' => $tenantconfig->get_config('show_company_info', 1),
                        'user_display_style' => $tenantconfig->get_config('user_display_style', 'default'),
                    ];
                }
            }
        }
        
        return $companycontext;
    }
    
    /**
     * Get companies for a user using correct IOMAD method
     */
    private function get_user_companies($userid) {
        global $DB;
        
        $sql = "SELECT c.* FROM {company} c
                JOIN {company_users} cu ON c.id = cu.companyid
                WHERE cu.userid = ? AND c.suspended = 0
                ORDER BY c.name";
        
        return $DB->get_records_sql($sql, [$userid]);
    }
    
    /**
     * Renders user profile with company context
     */
    public function user_profile($user, $course = null, $accessallgroups = true, $showuseridentity = true) {
        global $DB;
        
        $companycontext = $this->get_company_context();
        $output = '';
        
        // Add company information if enabled
        if (!empty($companycontext['companyid']) && $companycontext['show_company_info']) {
            $usercompanies = $this->get_user_companies($user->id);
            if (!empty($usercompanies)) {
                $output .= $this->render_user_companies($usercompanies);
            }
        }
        
        $output .= parent::user_profile($user, $course, $accessallgroups, $showuseridentity);
        
        return $output;
    }
    
    /**
     * Render user companies
     */
    private function render_user_companies($companies) {
        $output = html_writer::start_div('user-companies');
        $output .= html_writer::tag('h4', get_string('companies', 'local_iomad'));
        
        $output .= html_writer::start_tag('ul', ['class' => 'company-list']);
        foreach ($companies as $company) {
            $output .= html_writer::tag('li', $company->name, ['class' => 'company-item']);
        }
        $output .= html_writer::end_tag('ul');
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Renders a user picture with company branding
     *
     * @param user_picture $userpicture
     * @return string HTML
     */
    public function render_user_picture(user_picture $userpicture) {
        $companycontext = $this->get_company_context();
        
        $output = parent::render_user_picture($userpicture);
        
        // Add company-specific styling
        if (!empty($companycontext['companyid'])) {
            $classes = ['user-picture-wrapper', 'company-' . $companycontext['companyid']];
            $style = $companycontext['user_display_style'];
            if ($style !== 'default') {
                $classes[] = 'style-' . $style;
            }
            
            $output = html_writer::div($output, implode(' ', $classes));
        }
        
        return $output;
    }
    
    /**
     * Renders user listing with company context
     *
     * @param stdClass $course
     * @param array $users
     * @param bool $showactive
     * @param bool $showuseridentity
     * @return string HTML
     */
    public function course_user_listing($course, $users, $showactive = true, $showuseridentity = true) {
        $companycontext = $this->get_company_context();
        
        $output = '';
        
        // Add company filter if multiple companies exist
        if (!empty($companycontext['companyid'])) {
            $usercompanies = $this->get_course_companies($course->id);
            if (count($usercompanies) > 1) {
                $output .= $this->render_company_filter($usercompanies);
            }
        }
        
        $output .= parent::course_user_listing($course, $users, $showactive, $showuseridentity);
        
        return $output;
    }
    
    /**
     * Get companies with users in a course
     */
    private function get_course_companies($courseid) {
        global $DB;
        
        $sql = "SELECT DISTINCT c.* FROM {company} c
                JOIN {company_users} cu ON c.id = cu.companyid
                JOIN {user_enrolments} ue ON cu.userid = ue.userid
                JOIN {enrol} e ON ue.enrolid = e.id
                WHERE e.courseid = ? AND c.suspended = 0
                ORDER BY c.name";
        
        return $DB->get_records_sql($sql, [$courseid]);
    }
    
    /**
     * Render company filter for user listings
     */
    private function render_company_filter($companies) {
        $output = html_writer::start_div('company-filter');
        $output .= html_writer::tag('label', get_string('filterbycompany', 'theme_iomadremui'));
        
        $options = ['' => get_string('allcompanies', 'theme_iomadremui')];
        foreach ($companies as $company) {
            $options[$company->id] = $company->name;
        }
        
        $select = html_writer::select($options, 'company_filter', '', false, [
            'id' => 'company-filter-select',
            'class' => 'form-control'
        ]);
        
        $output .= $select;
        $output .= html_writer::end_div();
        
        // Add JavaScript for filtering
        $this->page->requires->js_call_amd('theme_iomadremui/user_filter', 'init');
        
        return $output;
    }
}
