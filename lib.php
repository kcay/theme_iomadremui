<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/iomad/lib/company.php');

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_iomadremui_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG;

    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('iomadremui');
        
        // Serve company-specific files
        if ($filearea === 'companylogo' || $filearea === 'companyfavicon' || $filearea === 'companyloginbg') {
            return theme_iomadremui_serve_company_file($theme, $context, $filearea, $args, $forcedownload, $options);
        }
        
        // Default file serving
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    
    return false;
}

/**
 * Serve company-specific files
 */
function theme_iomadremui_serve_company_file($theme, $context, $filearea, $args, $forcedownload, $options) {
    global $DB;
    
    $companyid = optional_param('companyid', 0, PARAM_INT);
    if (!$companyid && isloggedin()) {
        $companyid = iomad::get_my_companyid($context);
    }
    
    if ($companyid) {
        $fs = get_file_storage();
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        
        $file = $fs->get_file($context->id, 'theme_iomadremui', $filearea . '_' . $companyid, 0, $filepath, $filename);
        if ($file) {
            send_stored_file($file, null, 0, $forcedownload, $options);
            return true;
        }
    }
    
    return false;
}

/**
 * FIXED: Enhanced navigation data retrieval for RemUI-style top navbar
 * This function replaces the problematic left drawer navigation with proper top navbar data
 */
function theme_iomadremui_get_navigation_data($PAGE, $OUTPUT) {
    $navigation = [
        'usermenu' => '',
        'langmenu' => '',
        'primarynav' => '',
        'mobilenav' => '',
    ];
    
    try {
        // FIXED: Get user menu properly
        if (method_exists($OUTPUT, 'user_menu')) {
            $navigation['usermenu'] = $OUTPUT->user_menu();
        }
        
        // FIXED: Get language menu properly  
        if (method_exists($OUTPUT, 'lang_menu')) {
            $navigation['langmenu'] = $OUTPUT->lang_menu();
        }
        
        // FIXED: Try to get navbar data from boost navbar if available
        if (class_exists('theme_boost\\boostnavbar')) {
            $boostnavbar = new theme_boost\boostnavbar($PAGE);
            if (method_exists($boostnavbar, 'export_for_template')) {
                $renderer = $PAGE->get_renderer('core');
                $navdata = $boostnavbar->export_for_template($renderer);
                
                // Extract the navigation components we need
                if (isset($navdata['user'])) {
                    $navigation['usermenu'] = $navdata['user'];
                }
                if (isset($navdata['lang'])) {
                    $navigation['langmenu'] = $navdata['lang'];
                }
                if (isset($navdata['mobileprimarynav'])) {
                    $navigation['mobilenav'] = $navdata['mobileprimarynav'];
                }
            }
        }
        
    } catch (Exception $e) {
        // Fallback to basic navigation if boost navbar fails
        debugging('Navigation data extraction failed: ' . $e->getMessage());
    }
    
    return $navigation;
}

/**
 * FIXED: Get company-specific theme configuration with enhanced caching
 */
function theme_iomadremui_get_company_config($companyid = null, $configkey = null) {
    global $DB, $USER;
    static $configcache = [];
    
    if (!$companyid) {
        $companyid = iomad::get_my_companyid(context_system::instance());
    }
    
    if (!$companyid) {
        return null;
    }
    
    // Use cache if available
    if (!isset($configcache[$companyid])) {
        $params = ['companyid' => $companyid];
        $sql = "SELECT configkey, configvalue, configtype FROM {iomadremui_company_config} WHERE companyid = :companyid";
        
        $configs = $DB->get_records_sql($sql, $params);
        $configcache[$companyid] = [];
        foreach ($configs as $config) {
            $configcache[$companyid][$config->configkey] = $config->configvalue;
        }
    }
    
    if ($configkey) {
        return isset($configcache[$companyid][$configkey]) ? $configcache[$companyid][$configkey] : null;
    }
    
    return $configcache[$companyid];
}

/**
 * FIXED: Set company-specific theme configuration with validation
 */
function theme_iomadremui_set_company_config($companyid, $configkey, $configvalue, $configtype = 'text') {
    global $DB;
    
    // Validate input
    if (!$companyid || !$configkey) {
        return false;
    }
    
    // Validate config type
    $validtypes = ['text', 'textarea', 'color', 'number', 'select', 'checkbox', 'file', 'editor'];
    if (!in_array($configtype, $validtypes)) {
        $configtype = 'text';
    }
    
    // Validate color values
    if ($configtype === 'color' && !preg_match('/^#[a-fA-F0-9]{6}$/', $configvalue)) {
        return false;
    }
    
    $existing = $DB->get_record('iomadremui_company_config', [
        'companyid' => $companyid,
        'configkey' => $configkey
    ]);
    
    $data = (object)[
        'companyid' => $companyid,
        'configkey' => $configkey,
        'configvalue' => $configvalue,
        'configtype' => $configtype,
        'timemodified' => time()
    ];
    
    if ($existing) {
        $data->id = $existing->id;
        $DB->update_record('iomadremui_company_config', $data);
    } else {
        $data->timecreated = time();
        $DB->insert_record('iomadremui_company_config', $data);
    }
    
    // Clear theme cache
    theme_reset_all_caches();
    
    return true;
}

/**
 * FIXED: Process CSS with company-specific customizations - enhanced for performance
 */
function theme_iomadremui_process_css($css, $theme) {
    global $PAGE;
    
    // Get company ID
    $companyid = 0;
    if (isloggedin()) {
        $companyid = iomad::get_my_companyid(context_system::instance());
    }
    
    // Apply company-specific CSS customizations
    if ($companyid) {
        $companyconfig = theme_iomadremui_get_company_config($companyid);
        
        // Primary color
        if (isset($companyconfig['primarycolor'])) {
            $css = str_replace('[[setting:primarycolor]]', $companyconfig['primarycolor'], $css);
            $css = theme_iomadremui_set_color_variables($css, 'primary', $companyconfig['primarycolor']);
        }
        
        // Secondary color
        if (isset($companyconfig['secondarycolor'])) {
            $css = str_replace('[[setting:secondarycolor]]', $companyconfig['secondarycolor'], $css);
            $css = theme_iomadremui_set_color_variables($css, 'secondary', $companyconfig['secondarycolor']);
        }
        
        // Custom CSS
        if (isset($companyconfig['customcss'])) {
            $css .= "\n/* Company Custom CSS */\n" . $companyconfig['customcss'];
        }
        
        // Font family
        if (isset($companyconfig['fontfamily'])) {
            $css = str_replace('[[setting:fontfamily]]', $companyconfig['fontfamily'], $css);
        }
    }
    
    // Default fallbacks
    $css = str_replace('[[setting:primarycolor]]', '#007bff', $css);
    $css = str_replace('[[setting:secondarycolor]]', '#6c757d', $css);
    $css = str_replace('[[setting:fontfamily]]', '"Segoe UI", Roboto, sans-serif', $css);
    
    return $css;
}

/**
 * Set color variables in CSS
 */
function theme_iomadremui_set_color_variables($css, $type, $color) {
    // Convert hex to RGB
    $rgb = theme_iomadremui_hex_to_rgb($color);
    
    $replacements = [
        "--{$type}-rgb" => implode(', ', $rgb),
        "--{$type}" => $color,
        "--{$type}-hover" => theme_iomadremui_adjust_brightness($color, -10),
        "--{$type}-light" => theme_iomadremui_adjust_brightness($color, 20),
        "--{$type}-dark" => theme_iomadremui_adjust_brightness($color, -20),
    ];
    
    foreach ($replacements as $variable => $value) {
        $css = str_replace($variable, $value, $css);
    }
    
    return $css;
}

/**
 * Convert hex color to RGB array
 */
function theme_iomadremui_hex_to_rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

/**
 * Adjust color brightness
 */
function theme_iomadremui_adjust_brightness($hex, $percent) {
    $rgb = theme_iomadremui_hex_to_rgb($hex);
    
    foreach ($rgb as &$color) {
        $color = max(0, min(255, $color + ($color * $percent / 100)));
    }
    
    return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
}

/**
 * Get extra SCSS for the theme
 */
function theme_iomadremui_get_extra_scss($theme) {
    $content = '';
    
    // Get company-specific SCSS
    if (isloggedin()) {
        $companyid = iomad::get_my_companyid(context_system::instance());
        if ($companyid) {
            $companyconfig = theme_iomadremui_get_company_config($companyid);
            
            if (isset($companyconfig['extrasass'])) {
                $content .= $companyconfig['extrasass'];
            }
        }
    }
    
    return $content;
}

/**
 * Get pre SCSS for the theme
 */
function theme_iomadremui_get_pre_scss($theme) {
    $scss = '';
    
    // Company-specific variables
    if (isloggedin()) {
        $companyid = iomad::get_my_companyid(context_system::instance());
        if ($companyid) {
            $companyconfig = theme_iomadremui_get_company_config($companyid);
            
            // SCSS Variables
            if (isset($companyconfig['primarycolor'])) {
                $scss .= '$primary: ' . $companyconfig['primarycolor'] . ";\n";
            }
            if (isset($companyconfig['secondarycolor'])) {
                $scss .= '$secondary: ' . $companyconfig['secondarycolor'] . ";\n";
            }
            if (isset($companyconfig['fontsize'])) {
                $scss .= '$font-size-base: ' . $companyconfig['fontsize'] . "rem;\n";
            }
        }
    }
    
    return $scss;
}

/**
 * Get the company logo URL
 */
function theme_iomadremui_get_company_logo_url($companyid = null) {
    global $OUTPUT;
    
    if (!$companyid) {
        $companyid = iomad::get_my_companyid(context_system::instance());
    }
    
    if (!$companyid) {
        return null;
    }
    
    $theme = theme_config::load('iomadremui');
    $context = context_system::instance();
    
    return moodle_url::make_pluginfile_url(
        $context->id,
        'theme_iomadremui',
        'companylogo_' . $companyid,
        0,
        '/',
        'logo.png'
    );
}

/**
 * Returns the main SCSS content.
 */
function theme_iomadremui_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/iomadremui/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/iomadremui/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_iomadremui', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Safety fallback - don't assume may not have a preset.
        $scss .= file_get_contents($CFG->dirroot . '/theme/iomadremui/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Inject HTML before the footer
 */
function theme_iomadremui_before_footer_html_injection() {
    global $PAGE;
    
    $html = '';
    
    // Add company-specific footer content
    if (isloggedin()) {
        $companyid = iomad::get_my_companyid(context_system::instance());
        if ($companyid) {
            $companyconfig = theme_iomadremui_get_company_config($companyid);
            
            if (isset($companyconfig['footercontent'])) {
                $html .= '<div class="company-footer-content">' . $companyconfig['footercontent'] . '</div>';
            }
        }
    }
    
    return $html;
}

/**
 * FIXED: Get user companies using correct IOMAD method
 */
function theme_iomadremui_get_user_companies() {
    global $DB, $USER;
    
    if (!isloggedin()) {
        return [];
    }
    
    // Use correct IOMAD method to get user companies
    $sql = "SELECT c.* FROM {company} c
            JOIN {company_users} cu ON c.id = cu.companyid
            WHERE cu.userid = ? AND c.suspended = 0
            ORDER BY c.name";
    
    return $DB->get_records_sql($sql, [$USER->id]);
}

/**
 * Check if user can manage company settings using correct IOMAD API
 */
function theme_iomadremui_can_manage_company($companyid) {
    global $USER;
    
    if (!isloggedin()) {
        return false;
    }
    
    $context = context_system::instance();
    
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
        if (iomad::has_capability($cap, $context)) {
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
 * ENHANCED: Company configuration management functions
 */

/**
 * Save company setting with validation
 */
function theme_iomadremui_save_company_setting($companyid, $key, $value, $type = 'text') {
    global $DB;
    
    // Validate inputs
    if (!$companyid || empty($key)) {
        return false;
    }
    
    // Type-specific validation
    switch ($type) {
        case 'color':
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                return false;
            }
            break;
        case 'number':
            if (!is_numeric($value)) {
                return false;
            }
            break;
        case 'checkbox':
            $value = $value ? 1 : 0;
            break;
    }
    
    return theme_iomadremui_set_company_config($companyid, $key, $value, $type);
}

/**
 * Get company setting with default value
 */
function theme_iomadremui_get_company_setting($companyid, $key, $default = null) {
    $value = theme_iomadremui_get_company_config($companyid, $key);
    return $value !== null ? $value : $default;
}

/**
 * Delete company setting
 */
function theme_iomadremui_delete_company_setting($companyid, $key) {
    global $DB;
    
    return $DB->delete_records('iomadremui_company_config', [
        'companyid' => $companyid,
        'configkey' => $key
    ]);
}

/**
 * Get all company settings as associative array
 */
function theme_iomadremui_get_all_company_settings($companyid) {
    return theme_iomadremui_get_company_config($companyid);
}

/**
 * Copy settings from one company to another
 */
function theme_iomadremui_copy_company_settings($fromcompanyid, $tocompanyid) {
    global $DB;
    
    $settings = theme_iomadremui_get_all_company_settings($fromcompanyid);
    
    foreach ($settings as $key => $value) {
        $record = $DB->get_record('iomadremui_company_config', [
            'companyid' => $fromcompanyid,
            'configkey' => $key
        ]);
        
        if ($record) {
            theme_iomadremui_set_company_config($tocompanyid, $key, $value, $record->configtype);
        }
    }
    
    return true;
}