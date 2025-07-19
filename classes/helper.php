<?php
namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for IOMAD RemUI theme
 */
class helper {
    
    /**
     * Get available layout options
     */
    /**
     * Get available layout options
     */
    public static function get_layout_options() {
        return [
            'grid' => get_string('grid', 'theme_iomadremui'),
            'list' => get_string('list', 'theme_iomadremui'),
            'cards' => get_string('cards', 'theme_iomadremui'),
        ];
    }
    
    /**
     * Get available sidebar positions
     */
    public static function get_sidebar_positions() {
        return [
            'left' => get_string('left', 'theme_iomadremui'),
            'right' => get_string('right', 'theme_iomadremui'),
            'none' => get_string('none', 'theme_iomadremui'),
        ];
    }
    
    /**
     * Get available header styles
     */
    public static function get_header_styles() {
        return [
            'default' => get_string('default', 'theme_iomadremui'),
            'banner' => get_string('banner', 'theme_iomadremui'),
            'minimal' => get_string('minimal', 'theme_iomadremui'),
        ];
    }
    
    /**
     * Get available login styles
     */
    public static function get_login_styles() {
        return [
            'default' => get_string('default', 'theme_iomadremui'),
            'centered' => get_string('centered', 'theme_iomadremui'),
            'split' => get_string('split', 'theme_iomadremui'),
        ];
    }
    
    /**
     * Validate color value
     */
    public static function validate_color($color) {
        return preg_match('/^#[a-fA-F0-9]{6}$/', $color);
    }
    
    /**
     * Sanitize CSS input
     */
    public static function sanitize_css($css) {
        // Basic CSS sanitization - remove potentially dangerous content
        $css = strip_tags($css);
        $css = preg_replace('/javascript:/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/import\s*\@/i', '', $css);
        
        return $css;
    }
    
    /**
     * Get font family options
     */
    public static function get_font_families() {
        return [
            '"Segoe UI", Roboto, sans-serif' => 'Segoe UI / Roboto',
            '"Helvetica Neue", Helvetica, Arial, sans-serif' => 'Helvetica',
            'Georgia, "Times New Roman", serif' => 'Georgia',
            '"Courier New", monospace' => 'Courier New',
            '"Open Sans", sans-serif' => 'Open Sans',
            '"Montserrat", sans-serif' => 'Montserrat',
            '"Poppins", sans-serif' => 'Poppins',
            '"Lato", sans-serif' => 'Lato',
        ];
    }
    
    /**
     * Generate CSS variables for a company
     */
    public static function generate_company_css_variables($companyconfig) {
        $css = ":root {\n";
        
        if (isset($companyconfig['primarycolor'])) {
            $css .= "  --company-primary: {$companyconfig['primarycolor']};\n";
            $css .= "  --bs-primary: {$companyconfig['primarycolor']};\n";
        }
        
        if (isset($companyconfig['secondarycolor'])) {
            $css .= "  --company-secondary: {$companyconfig['secondarycolor']};\n";
            $css .= "  --bs-secondary: {$companyconfig['secondarycolor']};\n";
        }
        
        if (isset($companyconfig['fontfamily'])) {
            $css .= "  --company-font-family: {$companyconfig['fontfamily']};\n";
        }
        
        if (isset($companyconfig['fontsize'])) {
            $css .= "  --company-font-size: {$companyconfig['fontsize']}rem;\n";
        }
        
        $css .= "}\n";
        
        return $css;
    }
    
    /**
     * Check if user has access to company settings
     */
    public static function can_edit_company_settings($companyid) {
        global $USER;
        
        if (!isloggedin()) {
            return false;
        }
        
        $context = \context_system::instance();
        
        // Site administrators can edit any company
        if (has_capability('moodle/site:config', $context)) {
            return true;
        }
        
        // Check IOMAD company management capabilities
        $iomadcaps = [
            'block/iomad_company_admin:company_add',
            'block/iomad_company_admin:company_edit', 
            'block/iomad_company_admin:company_view',
            'block/iomad_company_admin:companymanagement_view'
        ];
        
        foreach ($iomadcaps as $cap) {
            if (\iomad::has_capability($cap, $context)) {
                // Check if user belongs to this company
                $usercompanies = theme_iomadremui_get_user_companies();
                foreach ($usercompanies as $company) {
                    if ($company->id == $companyid) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get companies user can manage using correct IOMAD capabilities
     */
    public static function get_manageable_companies() {
        global $DB, $USER;
        
        if (!isloggedin()) {
            return [];
        }
        
        $context = \context_system::instance();
        
        // Site administrators can manage all companies
        if (has_capability('moodle/site:config', $context)) {
            return $DB->get_records('company', ['suspended' => 0], 'name ASC');
        }
        
        // Check IOMAD company management capabilities
        $iomadcaps = [
            'block/iomad_company_admin:company_add',
            'block/iomad_company_admin:company_edit', 
            'block/iomad_company_admin:company_view',
            'block/iomad_company_admin:companymanagement_view'
        ];
        
        $canmanage = false;
        foreach ($iomadcaps as $cap) {
            if (\iomad::has_capability($cap, $context)) {
                $canmanage = true;
                break;
            }
        }
        
        if ($canmanage) {
            // Return companies user belongs to
            return theme_iomadremui_get_user_companies();
        }
        
        return [];
    }
    
}
