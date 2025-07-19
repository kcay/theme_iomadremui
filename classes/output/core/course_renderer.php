<?php
namespace theme_iomadremui\output\core;

use moodle_url;
use html_writer;
use course_in_list;
use coursecat_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot . '/local/iomad/lib/company.php');

/**
 * Course renderer for IOMAD RemUI theme
 */
class course_renderer extends \core_course_renderer {
    
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
                        'course_layout' => $tenantconfig->get_config('course_layout', 'card'),
                        'show_progress' => $tenantconfig->get_config('show_progress', 1),
                        'course_sidebar' => $tenantconfig->get_config('course_sidebar', 'right'),
                        'course_header_style' => $tenantconfig->get_config('course_header_style', 'default'),
                    ];
                }
            }
        }
        
        return $companycontext;
    }
    
    /**
     * Renders HTML to display a list of course modules in a course section
     */
    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = []) {
        $companycontext = $this->get_company_context();
        
        // Add company-specific CSS classes
        if (!empty($companycontext['companyid'])) {
            $this->page->add_body_class('company-' . $companycontext['companyid']);
            $this->page->add_body_class('course-layout-' . $companycontext['course_layout']);
        }
        
        return parent::course_section_cm_list($course, $section, $sectionreturn, $displayoptions);
    }
    
    /**
     * Renders HTML to display one course module for display within a section.
     */
    public function course_section_cm($course, &$completioninfo, \cm_info $mod, $sectionreturn, $displayoptions = []) {
        $companycontext = $this->get_company_context();
        
        $output = '';
        
        // Add company-specific wrapper
        if (!empty($companycontext['companyid'])) {
            $classes = ['activity-wrapper', 'company-' . $companycontext['companyid']];
            
            // Add progress indicator if enabled
            if ($companycontext['show_progress'] && $completioninfo->is_enabled($mod)) {
                $classes[] = 'has-progress';
                $completion = $completioninfo->is_course_complete($mod->userid ?? 0);
                $classes[] = $completion ? 'completed' : 'incomplete';
            }
            
            $output .= html_writer::start_div(implode(' ', $classes));
        }
        
        $output .= parent::course_section_cm($course, $completioninfo, $mod, $sectionreturn, $displayoptions);
        
        // Add progress indicator
        if (!empty($companycontext['companyid']) && $companycontext['show_progress'] && $completioninfo->is_enabled($mod)) {
            $output .= $this->render_activity_progress($mod, $completioninfo);
        }
        
        if (!empty($companycontext['companyid'])) {
            $output .= html_writer::end_div();
        }
        
        return $output;
    }
    
    /**
     * Render activity progress indicator
     */
    protected function render_activity_progress($mod, $completioninfo) {
        global $USER;
        
        $completion = $completioninfo->get_data($mod, false, $USER->id);
        $completionstate = $completion->completionstate;
        
        $progressclass = '';
        $progresstext = '';
        
        switch ($completionstate) {
            case COMPLETION_COMPLETE:
                $progressclass = 'complete';
                $progresstext = get_string('completed', 'completion');
                break;
            case COMPLETION_COMPLETE_PASS:
                $progressclass = 'complete-pass';
                $progresstext = get_string('completedpass', 'completion');
                break;
            case COMPLETION_COMPLETE_FAIL:
                $progressclass = 'complete-fail';
                $progresstext = get_string('completedfail', 'completion');
                break;
            default:
                $progressclass = 'incomplete';
                $progresstext = get_string('notcompleted', 'completion');
                break;
        }
        
        $output = html_writer::start_div('activity-progress ' . $progressclass);
        $output .= html_writer::span($progresstext, 'progress-text sr-only');
        $output .= html_writer::span('', 'progress-indicator');
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Renders the list of courses
     *
     * @param array $courses the list of courses to display
     * @param int $totalcount total number of courses in the system
     * @param coursecat $chelper various display options
     * @return string
     */
    protected function coursecat_courses(coursecat_helper $chelper, $courses, $totalcount = null) {
        $companycontext = $this->get_company_context();
        
        if (!empty($companycontext['companyid'])) {
            // Apply company-specific course layout
            $layout = $companycontext['course_layout'];
            $chelper->set_courses_display_options([
                'layout' => $layout,
                'company_id' => $companycontext['companyid']
            ]);
        }
        
        return parent::coursecat_courses($chelper, $courses, $totalcount);
    }
    
    /**
     * Renders HTML to display particular course category - list of it's subcategories and courses
     *
     * @param int|stdClass|coursecat $category
     * @param int $depth depth of the category relative to the current page
     * @return string
     */
    protected function coursecat_category_content($category, $depth, $options) {
        $companycontext = $this->get_company_context();
        
        $content = parent::coursecat_category_content($category, $depth, $options);
        
        // Add company-specific styling wrapper
        if (!empty($companycontext['companyid'])) {
            $classes = ['coursecat-content', 'company-' . $companycontext['companyid']];
            $content = html_writer::div($content, implode(' ', $classes));
        }
        
        return $content;
    }
    
    /**
     * Returns HTML to display course summary with company branding
     *
     * @param course_in_list $course
     * @return string
     */
    protected function course_summary(course_in_list $course) {
        $companycontext = $this->get_company_context();
        
        $content = parent::course_summary($course);
        
        // Add company branding if configured
        if (!empty($companycontext['companyid'])) {
            $companylogo = $companycontext['config']->get_config('logo');
            if ($companylogo) {
                $logo = html_writer::img($companylogo, '', ['class' => 'company-logo-small']);
                $content = $logo . $content;
            }
        }
        
        return $content;
    }
}
