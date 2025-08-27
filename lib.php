<?php
// theme/iomadremui/lib.php - ENHANCED WITH MULTITENANCY INTEGRATION

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/iomad/lib/company.php');

// Include multitenancy helper if available
if (file_exists($CFG->dirroot . '/local/multitenancy/lib.php')) {
    require_once($CFG->dirroot . '/local/multitenancy/lib.php');
}

/**
 * MISSING FUNCTION: Early company context initialization
 * Called from login.php to set up company context early
 */
function theme_iomadremui_init_company_context() {
    global $SESSION, $PAGE, $DB;
    
    // Use multitenancy helper if available
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        if ($company) {
            local_multitenancy_helper::set_company_session($company);
            return $company;
        }
    }
    
    // Fallback to original IOMAD detection
    $companyid = theme_iomadremui_get_company_from_iomad_url();
    if ($companyid) {
        // Set basic session data
        $company = $DB->get_record('company', ['id' => $companyid, 'suspended' => 0]);
        if ($company) {
            $SESSION->currenteditingcompany = $company->id;
            $SESSION->company = $company;
            $SESSION->theme = $company->theme;
            return $company;
        }
    }
    
    return null;
}

/**
 * MISSING FUNCTION: Handle company domain redirects
 * Called from login.php to redirect users to correct domain
 */
function theme_iomadremui_handle_company_domain_redirect() {
    // Use multitenancy helper if available
    if (class_exists('local_multitenancy_helper')) {
        return local_multitenancy_helper::handle_user_redirect();
    }
    
    // Fallback to basic redirect logic
    global $DB, $USER, $CFG, $PAGE;
    
    if (!isloggedin() || isguestuser() || defined('CLI_SCRIPT')) {
        return false;
    }
    
    if (defined('AJAX_SCRIPT') || $PAGE->pagetype === 'login-index') {
        return false;
    }
    
    $current_host = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($current_host)) {
        return false;
    }
    
    try {
        $sql = "SELECT c.hostname, c.id, c.name
                FROM {company} c
                JOIN {company_users} cu ON c.id = cu.companyid
                WHERE cu.userid = ? AND c.suspended = 0 
                AND c.hostname IS NOT NULL AND c.hostname != ''
                ORDER BY cu.id ASC 
                LIMIT 1";
        
        $user_company = $DB->get_record_sql($sql, [$USER->id]);
        
        if ($user_company && !empty($user_company->hostname)) {
            $user_hostname = $user_company->hostname;
            $current_hostname = strtolower(trim($current_host));
            
            if (strpos($current_hostname, ':') !== false) {
                $current_hostname = substr($current_hostname, 0, strpos($current_hostname, ':'));
            }
            
            $clean_user_hostname = strtolower(trim($user_hostname));
            
            if ($clean_user_hostname !== $current_hostname) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $current_path = $_SERVER['REQUEST_URI'] ?? '/';
                $redirect_url = $protocol . '://' . $user_hostname . $current_path;
                
                error_log("Theme Redirect: User {$USER->id} from {$current_hostname} to {$user_hostname}");
                redirect($redirect_url);
                return true;
            }
        }
        
    } catch (Exception $e) {
        error_log('Theme Company Redirect Error: ' . $e->getMessage());
    }
    
    return false;
}

/**
 * ENHANCED: Get company ID from IOMAD's actual URL patterns
 * This function is called from your login.php
 */
function theme_iomadremui_get_company_from_iomad_url() {
    global $DB, $SESSION;
    
    // Use multitenancy helper if available (preferred method)
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        if ($company) {
            $_SESSION['iomad_login_company'] = $company->id;
            return $company->id;
        }
    }
    
    // Method 1: Check IOMAD URL parameters (id + code)
    $company_id = optional_param('id', 0, PARAM_INT);
    $company_code = optional_param('code', '', PARAM_ALPHANUMEXT);
    
    if ($company_id && $company_code) {
        // Verify the ID and code match a real company
        $company = $DB->get_record('company', [
            'id' => $company_id,
            'shortname' => $company_code,
            'suspended' => 0
        ]);
        
        if ($company) {
            $_SESSION['iomad_login_company'] = $company_id;
            return $company_id;
        }
    }
    
    // Method 2: Check for custom domain/subdomain using hostname column
    $companyid = theme_iomadremui_get_company_from_hostname();
    if ($companyid) {
        $_SESSION['iomad_login_company'] = $companyid;
        return $companyid;
    }
    
    // Method 3: Check session from previous detection
    if (!empty($_SESSION['iomad_login_company'])) {
        // Verify company still exists and is active
        $company = $DB->get_record('company', [
            'id' => $_SESSION['iomad_login_company'],
            'suspended' => 0
        ]);
        
        if ($company) {
            return $_SESSION['iomad_login_company'];
        } else {
            // Clean up invalid session
            unset($_SESSION['iomad_login_company']);
        }
    }
    
    // Method 4: Check if user is already logged in and has company context
    if (isloggedin()) {
        try {
            $companyid = iomad::get_my_companyid(context_system::instance());
            if ($companyid) {
                return $companyid;
            }
        } catch (Exception $e) {
            // Continue to other methods
        }
    }
    
    return 0;
}

/**
 * Get company from hostname (for custom domains and subdomains)
 * Used by theme_iomadremui_get_company_from_iomad_url()
 */
function theme_iomadremui_get_company_from_hostname() {
    global $DB, $CFG;
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($host)) {
        return 0;
    }
    
    // Clean the hostname
    $host = strtolower(trim($host));
    if (strpos($host, ':') !== false) {
        $host = substr($host, 0, strpos($host, ':'));
    }
    
    // Remove www. prefix if present for alternative matching
    $host_without_www = $host;
    if (strpos($host, 'www.') === 0) {
        $host_without_www = substr($host, 4);
    }
    
    try {
        // Method 1: Direct hostname match
        $company = $DB->get_record('company', [
            'hostname' => $host,
            'suspended' => 0
        ]);
        
        if ($company) {
            return $company->id;
        }
        
        // Method 2: Try without www prefix
        if ($host_without_www !== $host) {
            $company = $DB->get_record('company', [
                'hostname' => $host_without_www,
                'suspended' => 0
            ]);
            
            if ($company) {
                return $company->id;
            }
        }
        
        // Method 3: Try matching patterns with wildcards or partial matches
        $sql = "SELECT id FROM {company} 
                WHERE suspended = 0 
                AND hostname IS NOT NULL 
                AND hostname != ''
                AND (hostname = ? OR hostname = ? OR ? LIKE CONCAT('%', hostname, '%'))
                LIMIT 1";
        
        $result = $DB->get_record_sql($sql, [$host, $host_without_www, $host]);
        if ($result) {
            return $result->id;
        }
        
        // Method 4: Extract subdomain and check if it matches any company's hostname pattern
        $host_parts = explode('.', $host);
        if (count($host_parts) >= 2) {
            // Try different combinations
            for ($i = 0; $i < count($host_parts) - 1; $i++) {
                $partial_host = implode('.', array_slice($host_parts, $i));
                
                $company = $DB->get_record('company', [
                    'hostname' => $partial_host,
                    'suspended' => 0
                ]);
                
                if ($company) {
                    return $company->id;
                }
            }
        }
        
    } catch (Exception $e) {
        // Log the error but don't break the site
        debugging('Error in theme_iomadremui_get_company_from_hostname: ' . $e->getMessage());
    }
    
    return 0;
}

/**
 * Get login background URL for company
 * Called from login.php template context
 */
function theme_iomadremui_get_login_background_url($companyid) {
    global $CFG;
    
    if (!$companyid) {
        return null;
    }
    
    $context = context_system::instance();
    $fs = get_file_storage();
    
    // Try multitenancy file storage first
    if (class_exists('local_multitenancy_helper')) {
        $files = $fs->get_area_files($context->id, 'local_multitenancy', 'companyloginbg', $companyid, 'filename', false);
        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $context->id,
                'local_multitenancy',
                'companyloginbg',
                $companyid,
                '/',
                $file->get_filename(),
                false
            )->out();
        }
    }
    
    // Fallback to theme file storage
    $files = $fs->get_area_files($context->id, 'theme_iomadremui', 'loginbackground_' . $companyid, 0, 'filename', false);
    if ($files) {
        $file = reset($files);
        return moodle_url::make_pluginfile_url(
            $context->id,
            'theme_iomadremui',
            'loginbackground_' . $companyid,
            0,
            '/',
            $file->get_filename(),
            false
        )->out();
    }
    
    return null;
}

/**
 * Serves any files associated with the theme settings.
 */
function theme_iomadremui_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG;

    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('iomadremui');
        
        // Enhanced company-specific file serving with multitenancy support
        if ($filearea === 'companylogo' || $filearea === 'companyfavicon' || $filearea === 'companyloginbg') {
            return theme_iomadremui_serve_company_file($theme, $context, $filearea, $args, $forcedownload, $options);
        }
        
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    
    return false;
}


/**
 * Enhanced company-specific file serving with multitenancy integration
 */
function theme_iomadremui_serve_company_file($theme, $context, $filearea, $args, $forcedownload, $options) {
    global $DB;
    
    // Get company ID using multitenancy helper if available
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        $companyid = $company ? $company->id : 0;
    } else {
        // Fallback to original method
        $companyid = optional_param('companyid', 0, PARAM_INT);
        if (!$companyid && isloggedin()) {
            $companyid = iomad::get_my_companyid(context_system::instance());
        }
    }
    
    if ($companyid) {
        $fs = get_file_storage();
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        
        // Try multitenancy file storage first
        if (class_exists('local_multitenancy_helper')) {
            $file = $fs->get_file($context->id, 'local_multitenancy', $filearea, $companyid, $filepath, $filename);
            if ($file) {
                send_stored_file($file, null, 0, $forcedownload, $options);
                return true;
            }
        }
        
        // Fallback to theme file storage
        $file = $fs->get_file($context->id, 'theme_iomadremui', $filearea . '_' . $companyid, 0, $filepath, $filename);
        if ($file) {
            send_stored_file($file, null, 0, $forcedownload, $options);
            return true;
        }
    }
    
    return false;
}

/**
 * Enhanced CSS processing with multitenancy support
 */
function theme_iomadremui_process_css($css, $theme) {
    global $PAGE;
    
    // Get company using multitenancy helper if available
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        $companyid = $company ? $company->id : 0;
    } else {
        // Fallback to original detection
        $companyid = theme_iomadremui_get_company_id_for_file_serving();
    }
    
    if ($companyid) {
        // Use multitenancy theme override if available
        if (class_exists('\local_multitenancy\theme_override')) {
            $css = \local_multitenancy\theme_override::process_css($css, $theme);
        } else {
            // Fallback to original method
            $css = theme_iomadremui_apply_company_css($css, $companyid);
        }
    }
    
    return $css;
}

/**
 * Fallback method for applying company CSS (if multitenancy plugin not available)
 */
function theme_iomadremui_apply_company_css($css, $companyid) {
    // Get company config using original method
    $companyconfig = theme_iomadremui_get_company_config($companyid);
    
    if (!$companyconfig) {
        return $css;
    }
    
    $css .= "\n/* Company {$companyid} Styles */\n";
    $css .= "body.company-{$companyid} {\n";
    
    if (isset($companyconfig['primarycolor'])) {
        $css .= "  --bs-primary: {$companyconfig['primarycolor']} !important;\n";
    }
    
    if (isset($companyconfig['secondarycolor'])) {
        $css .= "  --bs-secondary: {$companyconfig['secondarycolor']} !important;\n";
    }
    
    if (isset($companyconfig['fontfamily'])) {
        $css .= "  font-family: {$companyconfig['fontfamily']} !important;\n";
    }
    
    $css .= "}\n";
    
    if (isset($companyconfig['customcss'])) {
        $css .= "\n/* Company {$companyid} Custom CSS */\n";
        $css .= $companyconfig['customcss'];
    }
    
    return $css;
}

/**
 * Enhanced company configuration getter with multitenancy support
 */
function theme_iomadremui_get_company_config($companyid = null, $configkey = null) {
    global $DB, $USER;
    static $configcache = [];
    
    if (!$companyid) {
        // Use multitenancy helper if available
        if (class_exists('local_multitenancy_helper')) {
            $company = local_multitenancy_helper::get_current_company();
            $companyid = $company ? $company->id : 0;
        } else {
            $companyid = iomad::get_my_companyid(context_system::instance());
        }
    }
    
    if (!$companyid) {
        return null;
    }
    
    // Use cache if available
    if (!isset($configcache[$companyid])) {
        // Try multitenancy config first
        if (class_exists('\local_multitenancy\config_manager')) {
            $configcache[$companyid] = \local_multitenancy\config_manager::get_all($companyid);
        } else {
            // Fallback to original method
            $params = ['companyid' => $companyid];
            
            // Check if multitenancy table exists
            if ($DB->get_manager()->table_exists('multitenancy_company_config')) {
                $sql = "SELECT configkey, configvalue FROM {multitenancy_company_config} WHERE companyid = :companyid";
            } else if ($DB->get_manager()->table_exists('iomadremui_company_config')) {
                $sql = "SELECT configkey, configvalue FROM {iomadremui_company_config} WHERE companyid = :companyid";
            } else {
                $configcache[$companyid] = [];
                return $configkey ? null : [];
            }
            
            try {
                $configs = $DB->get_records_sql($sql, $params);
                $configcache[$companyid] = [];
                foreach ($configs as $config) {
                    $configcache[$companyid][$config->configkey] = $config->configvalue;
                }
            } catch (Exception $e) {
                $configcache[$companyid] = [];
            }
        }
    }
    
    if ($configkey) {
        return isset($configcache[$companyid][$configkey]) ? $configcache[$companyid][$configkey] : null;
    }
    
    return $configcache[$companyid];
}

/**
 * Enhanced company ID detection with multitenancy support
 */
function theme_iomadremui_get_company_id_for_file_serving() {
    global $SESSION, $DB;
    
    // Use multitenancy helper if available
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        return $company ? $company->id : 0;
    }
    
    // Use the same method as login.php
    return theme_iomadremui_get_company_from_iomad_url();
}

/**
 * Initialize multitenancy context - safe wrapper function
 */
function theme_iomadremui_init_multitenancy() {
    global $PAGE, $SESSION;
    
    // Only initialize if multitenancy is available
    if (!class_exists('local_multitenancy_helper')) {
        return theme_iomadremui_init_company_context();
    }
    
    // Get current company
    $company = local_multitenancy_helper::get_current_company();
    
    if ($company) {
        // Set company session data
        local_multitenancy_helper::set_company_session($company);
        
        // Add company-specific body classes
        $PAGE->add_body_class('company-' . $company->id);
        $PAGE->add_body_class('multitenancy-active');
        
        return $company;
    }
    
    return false;
}

/**
 * Enhanced before footer hook with multitenancy support
 */
function theme_iomadremui_before_footer() {
    global $PAGE, $CFG;
    
    // Get company using multitenancy helper
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        $companyid = $company ? $company->id : 0;
    } else {
        $companyid = theme_iomadremui_get_company_id_for_file_serving();
    }
    
    if ($companyid) {
        $js = "
        // Enhanced IOMAD company body classes with multitenancy support
        document.addEventListener('DOMContentLoaded', function() {
            var body = document.body;
            if (body) {
                body.classList.add('company-{$companyid}');
                body.classList.add('multitenancy-active');
                
                // Add login style class for login pages
                if (body.classList.contains('pagelayout-login')) {
                    body.classList.add('login-style-default');
                }
                
                // Also add to containers that might need company styling
                var containers = document.querySelectorAll('.frontpage-course-list-all, .course-content, .coursecat-content');
                containers.forEach(function(container) {
                    container.classList.add('company-{$companyid}');
                });
                
                // Set global company context for other scripts
                window.IOMAD_COMPANY_ID = {$companyid};
            }
        });
        ";
        
        $PAGE->requires->js_init_code($js);
    }
}

/**
 * Enhanced navigation extension with company switcher
 */
function theme_iomadremui_extend_navigation(global_navigation $nav) {
    global $USER;
    
    if (isset($nav->home)) {
        unset($nav->home);
    }
    
    // Add company switcher if user has multiple companies and multitenancy is available
    if (isloggedin() && !isguestuser() && class_exists('local_multitenancy_helper')) {
        $user_companies = theme_iomadremui_get_user_companies();
        if (count($user_companies) > 1) {
            $company_node = $nav->add(
                get_string('switch_company', 'local_multitenancy'),
                null,
                navigation_node::TYPE_CUSTOM,
                null,
                'company_switcher'
            );
            
            foreach ($user_companies as $user_company) {
                if (!empty($user_company->hostname)) {
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                    $switch_url = $protocol . '://' . $user_company->hostname . '/';
                    
                    $company_node->add(
                        $user_company->name,
                        new moodle_url($switch_url),
                        navigation_node::TYPE_CUSTOM
                    );
                }
            }
        }
    }
}

/**
 * Get user companies with hostname
 */
function theme_iomadremui_get_user_companies() {
    global $DB, $USER;
    
    if (!isloggedin() || isguestuser()) {
        return [];
    }
    
    try {
        $sql = "SELECT c.* 
                FROM {company} c
                JOIN {company_users} cu ON c.id = cu.companyid
                WHERE cu.userid = ? AND c.suspended = 0 
                AND c.hostname IS NOT NULL AND c.hostname != ''
                ORDER BY c.name";
        
        return $DB->get_records_sql($sql, [$USER->id]);
    } catch (Exception $e) {
        error_log('Error getting user companies: ' . $e->getMessage());
        return [];
    }
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
        $scss .= file_get_contents($CFG->dirroot . '/theme/iomadremui/scss/preset/default.scss');
    }

    return $scss;
}

/**
 * Get extra SCSS for the theme with company-specific variables
 */
function theme_iomadremui_get_extra_scss($theme) {
    $content = '';
    
    // Get company-specific SCSS using multitenancy if available
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        if ($company && class_exists('\local_multitenancy\config_manager')) {
            $config = \local_multitenancy\config_manager::get($company->id, 'theme_extrasass', '');
            if ($config) {
                $content .= $config;
            }
        }
    } else {
        // Fallback method
        if (isloggedin()) {
            $companyid = theme_iomadremui_get_company_id_for_file_serving();
            if ($companyid) {
                $companyconfig = theme_iomadremui_get_company_config($companyid);
                if (isset($companyconfig['extrasass'])) {
                    $content .= $companyconfig['extrasass'];
                }
            }
        }
    }
    
    return $content;
}

/**
 * Get pre SCSS with company-specific variables
 */
function theme_iomadremui_get_pre_scss($theme) {
    $scss = '';
    
    // Company-specific SCSS variables using multitenancy if available
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        if ($company && class_exists('\local_multitenancy\config_manager')) {
            $config = \local_multitenancy\config_manager::get_with_defaults($company->id);
            
            if (isset($config['theme_primarycolor'])) {
                $scss .= '$primary: ' . $config['theme_primarycolor'] . ";\n";
            }
            if (isset($config['theme_secondarycolor'])) {
                $scss .= '$secondary: ' . $config['theme_secondarycolor'] . ";\n";
            }
            if (isset($config['theme_fontsize'])) {
                $scss .= '$font-size-base: ' . $config['theme_fontsize'] . "rem;\n";
            }
        }
    } else {
        // Fallback method
        if (isloggedin()) {
            $companyid = theme_iomadremui_get_company_id_for_file_serving();
            if ($companyid) {
                $companyconfig = theme_iomadremui_get_company_config($companyid);
                
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
    }
    
    return $scss;
}


/**
 * Enhanced page initialization with multitenancy support
 * Call this function from your theme's layout files
 */
function theme_iomadremui_page_init(moodle_page $page) {
    global $CFG;
    
    // Get company using multitenancy helper
    if (class_exists('local_multitenancy_helper')) {
        $company = local_multitenancy_helper::get_current_company();
        $companyid = $company ? $company->id : 0;
    } else {
        $companyid = theme_iomadremui_get_company_id_for_file_serving();
    }
    
    if ($companyid) {
        // Add company-specific body classes
        $page->add_body_class('company-' . $companyid);
        $page->add_body_class('multitenancy-active');
        
        // Add JavaScript for immediate class application
        $page->requires->js_init_code("
            (function() {
                // Add company classes as early as possible
                if (document.body) {
                    document.body.classList.add('company-{$companyid}');
                    document.body.classList.add('multitenancy-active');
                } else {
                    document.addEventListener('DOMContentLoaded', function() {
                        document.body.classList.add('company-{$companyid}');
                        document.body.classList.add('multitenancy-active');
                    });
                }
            })();
        ");
    }
}


/**
 * Simple function to handle company redirects (fallback)
 */
function theme_iomadremui_handle_company_redirect() {
    // Use multitenancy helper if available
    if (class_exists('local_multitenancy_helper')) {
        return local_multitenancy_helper::handle_user_redirect();
    }
    
    // Basic fallback - can be removed if not needed
    return false;
}


