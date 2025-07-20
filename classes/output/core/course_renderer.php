<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * IOMAD RemUI Course Renderer Class with Multi-tenant Support
 *
 * @package   theme_iomadremui
 * @copyright Modified for IOMAD multi-tenancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_iomadremui\output\core;
defined('MOODLE_INTERNAL') || die();

use moodle_url;
use coursecat_helper;
use lang_string;
use core_course_category;
use context_system;
use html_writer;
use core_text;
use pix_icon;
use theme_remui\utility as utility;

require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot . '/local/iomad/lib/company.php');

/**
 * IOMAD RemUI Course Renderer Class with Multi-tenant Support
 *
 * @copyright Modified for IOMAD multi-tenancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
                try {
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
                            'show_company_logo' => $tenantconfig->get_config('show_company_logo_on_courses', 1),
                            'company_logo' => $tenantconfig->get_config('logo'),
                            'company_name' => $this->get_company_name($companyid),
                        ];
                    }
                } catch (Exception $e) {
                    debugging('Error getting company context: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }
        
        return $companycontext;
    }
    
    /**
     * Get company name
     */
    private function get_company_name($companyid) {
        global $DB;
        
        try {
            $company = $DB->get_record('company', ['id' => $companyid], 'name');
            return $company ? $company->name : '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    /**
     * Filter courses by company context
     */
    private function filter_courses_by_company($courses) {
        $companycontext = $this->get_company_context();
        
        if (empty($companycontext['companyid'])) {
            return $courses; // No company context, return all courses
        }
        
        // Filter courses to only show company courses
        $companycourses = [];
        foreach ($courses as $course) {
            if ($this->is_company_course($course->id, $companycontext['companyid'])) {
                // Add company branding to course data
                $course->company_logo = $companycontext['company_logo'];
                $course->company_name = $companycontext['company_name'];
                $course->show_company_logo = $companycontext['show_company_logo'];
                $companycourses[] = $course;
            }
        }
        
        return $companycourses;
    }
    
    /**
     * Check if course belongs to company
     */
    private function is_company_course($courseid, $companyid) {
        global $DB;
        
        try {
            // Check if course is assigned to company
            $exists = $DB->record_exists('iomad_courses', [
                'courseid' => $courseid,
                'companyid' => $companyid
            ]);
            
            return $exists;
        } catch (Exception $e) {
            debugging('Error checking company course: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true; // Default to showing course if error
        }
    }
    
    /**
     * Renders HTML to display particular course category - list of it's subcategories and courses
     * ENHANCED: With company context (NO PAGE MODIFICATIONS)
     *
     * @param int|stdClass|core_course_category $category
     */
    public function get_morebutton_pagetitle($category) {
        global $CFG;
        $output = '';
        $site = get_site();
        $companycontext = $this->get_company_context();
        
        // REMOVED: Page modifications that cause errors
        // Company styling will be handled through CSS processing in lib.php
        
        if ($category != 'all') {
            $usertop = core_course_category::user_top();
            if (empty($category)) {
                $coursecat = $usertop;
            } else if (is_object($category) && $category instanceof core_course_category) {
                $coursecat = $category;
            } else {
                $coursecat = core_course_category::get(is_object($category) ? $category->id : $category);
            }

            $actionbar = new \core_course\output\category_action_bar($this->page, $coursecat);
            $output = $this->render_from_template('core_course/category_actionbar', $actionbar->export_for_template($this));
            
            // Set page title with company context
            $pagetitle = $site->shortname;
            if (!empty($companycontext['company_name'])) {
                $pagetitle = $companycontext['company_name'] . ' - ' . $pagetitle;
            }
            
            if (core_course_category::is_simple_site()) {
                $strfulllistofcourses = get_string('fulllistofcourses');
                $this->page->set_title("$pagetitle: $strfulllistofcourses");
            } else if (!$coursecat->id || !$coursecat->is_uservisible()) {
                $strcategories = get_string('categories');
                $this->page->set_title("$pagetitle: $strcategories");
            } else {
                $strfulllistofcourses = get_string('fulllistofcourses');
                $this->page->set_title("$pagetitle: $strfulllistofcourses");
            }
        } else {
            $strcategories = get_string('categories');
            $pagetitle = $site->shortname;
            if (!empty($companycontext['company_name'])) {
                $pagetitle = $companycontext['company_name'] . ' - ' . $pagetitle;
            }
            $this->page->set_title("$pagetitle: $strcategories");
        }
        
        return $output;
    }

    /**
     * Renders html to display a course search form
     * ENHANCED: With company context
     *
     * @param string $value default value to populate the search field
     * @return string
     */
    public function course_search_form($value = '') {
        $companycontext = $this->get_company_context();
        
        $data = [
            'action' => \core_search\manager::get_course_search_url(),
            'btnclass' => 'btn-primary',
            'inputname' => 'q',
            'searchstring' => get_string('searchcourses'),
            'hiddenfields' => (object) ['name' => 'areaids', 'value' => 'core_course-course'],
            'query' => $value,
            'company_context' => $companycontext
        ];
        
        // Add company-specific search placeholder
        if (!empty($companycontext['company_name'])) {
            $data['searchstring'] = get_string('searchcourses') . ' - ' . $companycontext['company_name'];
        }
        
        return $this->render_from_template('theme_remui/course_archive_search_input', $data);
    }

    /**
     * Returns HTML to print list of available courses for the frontpage
     * ENHANCED: With company filtering and branding
     *
     * @return string
     */
    public function frontpage_available_courses() {
        global $CFG, $DB;
        $contenthtml = '';
        $companycontext = $this->get_company_context();
        
        $chelper = new coursecat_helper();
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED)->set_courses_display_options(array(
                    'recursive' => true,
                    'limit' => $CFG->frontpagecourselimit,
                    'viewmoreurl' => new moodle_url('/course/index.php'),
                    'viewmoretext' => new lang_string('fulllistofcourses')));

        // Add company-specific CSS class via attributes
        $cssclasses = 'frontpage-course-list-all';
        if (!empty($companycontext['companyid'])) {
            $cssclasses .= ' company-' . $companycontext['companyid'];
            $cssclasses .= ' course-layout-' . $companycontext['course_layout'];
        }
        $chelper->set_attributes(array('class' => $cssclasses));

        $courselength = $CFG->frontpagecourselimit;
        $totalcount = core_course_category::get(0)->get_courses_count($chelper->get_courses_display_options());
        
        if (!$totalcount &&
        !$this->page->user_is_editing() &&
        has_capability('moodle/course:create', \context_system::instance())
        ) {
            // Print link to create a new course, for the 1st available category.
            return $this->add_new_course_button();
        }
        
        // Get courses using RemUI's course handler
        $coursehandler = new \theme_remui_coursehandler();
        $courses = $coursehandler->get_courses(
            false,
            null,
            null,
            0,
            $courselength,
            null,
            null,
            [],
            false
        );
        
        // ENHANCED: Filter courses by company context
        if (!empty($companycontext['companyid'])) {
            $courses = $this->filter_courses_by_company($courses);
        }

        if (!empty($courses)) {
            // Add company header if configured
            if (!empty($companycontext['company_name']) && 
                $companycontext['config']->get_config('show_company_header_on_frontpage', 1)) {
                $contenthtml .= $this->render_company_header($companycontext);
            }
            
            // Add company context to container classes
            $containerclasses = 'slick-slide-container company-courses';
            if (!empty($companycontext['companyid'])) {
                $containerclasses .= ' company-' . $companycontext['companyid'];
            }
            
            $contenthtml .= "<div class='{$containerclasses}'>";
            foreach ($courses as $course) {
                // Add company branding to each course
                $course->company_branding = $this->get_course_company_branding($course, $companycontext);
                $contenthtml .= $this->render_from_template("theme_remui/frontpage_available_course", $course);
            }
            $contenthtml .= "</div>";
            
            // Navigation buttons with company styling
            $primarycolor = !empty($companycontext['config']) ? 
                           $companycontext['config']->get_config('primarycolor', '#007bff') : '#007bff';
            
            $contenthtml .= "<div class='available-courses button-container w-100 text-center mt-3'>
                            <button type='button' class='btn btn-floating btn-primary btn-prev btn-sm' style='background-color: {$primarycolor}'>
                            <span class='edw-icon edw-icon-Left-Arrow' aria-hidden='true'></span>
                            </button>
                            <button type='button' class='btn btn-floating btn-primary btn-next btn-sm' style='background-color: {$primarycolor}'>
                            <span class='edw-icon edw-icon-Right-Arrow' aria-hidden='true'></span>
                            </button>
                            </div>";

            $viewalltext = get_string('viewallcourses', 'core');
            if (!empty($companycontext['company_name'])) {
                $viewalltext = get_string('viewallcourses', 'core') . ' - ' . $companycontext['company_name'];
            }
            
            $contenthtml .= "<div class='row'>
                            <div class='col-12 text-right'>
                             <a href='{$CFG->wwwroot}/course/index.php' class='btn btn-primary mt-2' style='background-color: {$primarycolor}'>{$viewalltext}</a>
                            </div>
                            </div>";
        } else if (!empty($companycontext['companyid'])) {
            // No company courses found
            $contenthtml .= "<div class='alert alert-info text-center'>";
            $contenthtml .= "<h4>" . get_string('nocoursesavailable', 'theme_iomadremui') . "</h4>";
            if (!empty($companycontext['company_name'])) {
                $contenthtml .= "<p>" . get_string('nocompanycourses', 'theme_iomadremui', $companycontext['company_name']) . "</p>";
            }
            $contenthtml .= "</div>";
        }

        return $contenthtml;
    }
    
    /**
     * Render company header for frontpage
     */
    private function render_company_header($companycontext) {
        $output = '<div class="company-header text-center mb-4">';
        
        if (!empty($companycontext['company_logo'])) {
            $output .= '<div class="company-logo mb-2">';
            $output .= '<img src="' . $companycontext['company_logo'] . '" alt="' . $companycontext['company_name'] . '" class="img-fluid" style="max-height: 80px;">';
            $output .= '</div>';
        }
        
        if (!empty($companycontext['company_name'])) {
            $output .= '<h2 class="company-name">' . $companycontext['company_name'] . ' ' . get_string('courses', 'core') . '</h2>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get company branding for a course
     */
    private function get_course_company_branding($course, $companycontext) {
        $branding = new \stdClass();
        
        if (!empty($companycontext['show_company_logo']) && !empty($companycontext['company_logo'])) {
            $branding->logo = $companycontext['company_logo'];
            $branding->show_logo = true;
        } else {
            $branding->show_logo = false;
        }
        
        $branding->company_name = $companycontext['company_name'] ?? '';
        $branding->primary_color = $companycontext['config']->get_config('primarycolor', '#007bff') ?? '#007bff';
        $branding->company_class = 'company-' . ($companycontext['companyid'] ?? '0');
        
        return $branding;
    }
    
    /**
     * ENHANCED: Course summary with company branding
     */
    protected function course_summary(coursecat_helper $chelper, \core_course_list_element $course): string {
        $companycontext = $this->get_company_context();
        
        $content = parent::course_summary($chelper, $course);
        
        // Add company branding if configured
        if (!empty($companycontext['companyid']) && 
            !empty($companycontext['show_company_logo']) && 
            !empty($companycontext['company_logo'])) {
            $logo = html_writer::img($companycontext['company_logo'], $companycontext['company_name'], [
                'class' => 'company-logo-small',
                'style' => 'max-width: 30px; max-height: 30px; margin-right: 8px;'
            ]);
            $content = $logo . $content;
        }
        
        return $content;
    }
}