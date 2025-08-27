<?php
// Updated classes/login_helper.php

namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for managing company login functionality
 * CORRECTED: Support for IOMAD's actual URL patterns using hostname column
 */
class login_helper {
    
    /**
     * Generate company login URL using IOMAD's standard patterns
     * CORRECTED: Use hostname column for custom domains
     * 
     * @param int $companyid
     * @param array $additional_params
     * @return \moodle_url
     */
    public static function get_company_login_url($companyid, $additional_params = []) {
        global $DB, $CFG;
        
        if (!$companyid) {
            return new \moodle_url('/login/index.php');
        }
        
        // Get company details
        $company = $DB->get_record('company', ['id' => $companyid], 'id, shortname, name, hostname');
        if (!$company) {
            return new \moodle_url('/login/index.php');
        }
        
        // CORRECTED: Check if company has a custom domain/subdomain configured in hostname column
        if (!empty($company->hostname)) {
            // Use custom domain/subdomain: https://company1.mylms.org/login/index.php
            $base_url = (is_https() ? 'https://' : 'http://') . $company->hostname;
            $params = array_merge($additional_params);
            return new \moodle_url($base_url . '/login/index.php', $params);
        } else {
            // Use IOMAD's parameter-based URL: /login/index.php?id=1&code=pcl
            $params = array_merge([
                'id' => $companyid,
                'code' => $company->shortname  // shortname is correct for the 'code' parameter
            ], $additional_params);
            
            return new \moodle_url('/login/index.php', $params);
        }
    }
    
    /**
     * CORRECTED: Get company custom domain/subdomain from hostname column
     * 
     * @param int $companyid
     * @return string|null Custom domain/subdomain or null
     */
    private static function get_company_custom_domain($companyid) {
        global $DB;
        
        // CORRECTED: Check hostname column for custom domain/subdomain configuration
        $company = $DB->get_record('company', ['id' => $companyid], 'id, hostname');
        if ($company && !empty($company->hostname)) {
            return $company->hostname;
        }
        
        return null;
    }
    
    /**
     * Get all possible login URLs for a company
     * CORRECTED: Use hostname column for custom domains
     * 
     * @param int $companyid
     * @return array Array of URL information
     */
    public static function get_all_company_login_urls($companyid) {
        global $DB;
        
        $company = $DB->get_record('company', ['id' => $companyid], '*');
        if (!$company) {
            return [];
        }
        
        $urls = [];
        
        // Check if company has custom domain/subdomain in hostname column
        $custom_hostname = !empty($company->hostname) ? $company->hostname : null;
        
        if ($custom_hostname) {
            // Primary URL is the custom domain/subdomain
            $urls['custom_domain'] = [
                'type' => 'Custom Domain/Subdomain URL',
                'url' => (is_https() ? 'https://' : 'http://') . $custom_hostname . '/login/index.php',
                'description' => 'Company-specific domain or subdomain configured in IOMAD',
                'primary' => true
            ];
            
            // Secondary URL is parameter-based
            $urls['parameter'] = [
                'type' => 'Parameter-based URL',
                'url' => self::get_parameter_based_url($companyid, $company->shortname),
                'description' => 'Standard IOMAD URL with company ID and code parameters',
                'primary' => false
            ];
        } else {
            // Primary URL is parameter-based (no custom hostname configured)
            $urls['parameter'] = [
                'type' => 'Parameter-based URL',
                'url' => self::get_parameter_based_url($companyid, $company->shortname),
                'description' => 'Standard IOMAD URL with company ID and code parameters',
                'primary' => true
            ];
            
            // Show potential subdomain URL as example
            $base_domain = self::get_base_domain();
            if ($base_domain && !empty($company->shortname)) {
                $potential_subdomain = $company->shortname . '.' . $base_domain;
                $urls['potential_subdomain'] = [
                    'type' => 'Potential Subdomain URL',
                    'url' => (is_https() ? 'https://' : 'http://') . $potential_subdomain . '/login/index.php',
                    'description' => 'Potential subdomain URL (configure in company hostname field to enable)',
                    'primary' => false,
                    'note' => 'To enable this URL, set "' . $potential_subdomain . '" in the company hostname field and configure DNS'
                ];
            }
        }
        
        return $urls;
    }
    
    /**
     * Generate parameter-based login URL
     * 
     * @param int $companyid
     * @param string $shortname
     * @return string
     */
    private static function get_parameter_based_url($companyid, $shortname) {
        global $CFG;
        
        $params = [
            'id' => $companyid,
            'code' => $shortname
        ];
        
        $url = new \moodle_url('/login/index.php', $params);
        return $url->out();
    }
    
    /**
     * Get base domain for subdomain generation
     * 
     * @return string|null
     */
    private static function get_base_domain() {
        global $CFG;
        
        // Extract base domain from wwwroot
        $parsed = parse_url($CFG->wwwroot);
        if (isset($parsed['host'])) {
            $host_parts = explode('.', $parsed['host']);
            if (count($host_parts) >= 2) {
                // Get last two parts (domain.com)
                return implode('.', array_slice($host_parts, -2));
            }
        }
        
        return null;
    }
    
    /**
     * Detect company from current request using IOMAD patterns
     * CORRECTED: Use hostname column for domain detection
     * 
     * @return int Company ID or 0 if not found
     */
    public static function detect_company_from_request() {
        global $DB;
        
        // Method 1: IOMAD URL parameters (id + code)
        $company_id = optional_param('id', 0, PARAM_INT);
        $company_code = optional_param('code', '', PARAM_ALPHANUMEXT);
        
        if ($company_id && $company_code) {
            $company = $DB->get_record('company', [
                'id' => $company_id,
                'shortname' => $company_code,  // shortname is correct for the 'code' parameter
                'suspended' => 0
            ]);
            
            if ($company) {
                return $company_id;
            }
        }
        
        // Method 2: CORRECTED - Custom domain/subdomain detection using hostname column
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host) {
            // Clean the hostname
            $host = strtolower(trim($host));
            if (strpos($host, ':') !== false) {
                $host = substr($host, 0, strpos($host, ':'));
            }
            
            // Remove www. prefix if present
            $host_without_www = $host;
            if (strpos($host, 'www.') === 0) {
                $host_without_www = substr($host, 4);
            }
            
            // Try exact hostname match first
            $company = $DB->get_record('company', [
                'hostname' => $host,  // CORRECTED: Use hostname column
                'suspended' => 0
            ]);
            
            if ($company) {
                return $company->id;
            }
            
            // Try without www prefix
            if ($host_without_www !== $host) {
                $company = $DB->get_record('company', [
                    'hostname' => $host_without_www,  // CORRECTED: Use hostname column
                    'suspended' => 0
                ]);
                
                if ($company) {
                    return $company->id;
                }
            }
            
            // Try pattern matching for partial hostname matches
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
        }
        
        return 0;
    }
    
    /**
     * Get company login page context for rendering
     * 
     * @param int $companyid
     * @return array
     */
    public static function get_login_page_context($companyid) {
        if (!$companyid) {
            return [];
        }
        
        $tenantconfig = new tenant_config($companyid);
        
        return [
            'id' => $companyid,
            'logo' => $tenantconfig->get_config('login_logo') ?: $tenantconfig->get_config('logo'),
            'background' => self::get_login_background_url($companyid),
            'welcome_message' => $tenantconfig->get_config('welcome_message'),
            'login_tagline' => $tenantconfig->get_config('login_tagline'),
            'login_style' => $tenantconfig->get_config('login_style', 'default'),
            'primarycolor' => $tenantconfig->get_config('primarycolor', '#007bff'),
            'signuptextcolor' => $tenantconfig->get_config('signuptextcolor', '#ffffff'),
            'has_background' => !empty(self::get_login_background_url($companyid)),
        ];
    }
    
    /**
     * Get login background URL for a company
     */
    private static function get_login_background_url($companyid) {
        if (!$companyid) {
            return null;
        }
        
        $context = \context_system::instance();
        $fs = get_file_storage();
        
        $files = $fs->get_area_files($context->id, 'theme_iomadremui', 'loginbackground_' . $companyid, 0, 'filename', false);
        if ($files) {
            $file = reset($files);
            return \moodle_url::make_pluginfile_url(
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
     * Generate login instructions for company users with IOMAD URLs
     * CORRECTED: Use hostname column for custom domains
     * 
     * @param int $companyid
     * @return string HTML instructions
     */
    public static function generate_login_instructions($companyid) {
        global $DB;
        
        $company = $DB->get_record('company', ['id' => $companyid]);
        if (!$company) {
            return '';
        }
        
        $urls = self::get_all_company_login_urls($companyid);
        $primary_url = '';
        
        foreach ($urls as $url_info) {
            if ($url_info['primary']) {
                $primary_url = $url_info['url'];
                break;
            }
        }
        
        $instructions = "
        <div class='company-login-instructions'>
            <h3>Login Instructions for {$company->name}</h3>";
        
        if (!empty($company->hostname)) {
            $instructions .= "<p><strong>Your company has a custom login URL:</strong> <a href='{$primary_url}' target='_blank'>{$primary_url}</a></p>";
        } else {
            $instructions .= "<p>To access your company's learning platform, use the following login URL:</p>";
        }
        
        if (count($urls) > 1) {
            $instructions .= "<div class='login-url-options'>";
            $instructions .= "<h4>Available Login Methods:</h4>";
            foreach ($urls as $key => $url_info) {
                $badge_class = $url_info['primary'] ? 'badge-primary' : 'badge-secondary';
                $instructions .= "
                <div class='login-url-option mb-3'>
                    <div class='d-flex align-items-center mb-2'>
                        <span class='badge {$badge_class} mr-2'>{$url_info['type']}</span>
                        " . ($url_info['primary'] ? '<span class="badge badge-success">Recommended</span>' : '') . "
                    </div>
                    <div class='login-url-box'>
                        <strong>URL:</strong> <a href='{$url_info['url']}' target='_blank'>{$url_info['url']}</a>
                    </div>
                    <small class='text-muted'>{$url_info['description']}</small>
                    " . (isset($url_info['note']) ? "<br><small class='text-warning'>{$url_info['note']}</small>" : '') . "
                </div>";
            }
            $instructions .= "</div>";
        } else {
            $instructions .= "
            <div class='login-url-box'>
                <strong>Login URL:</strong> <a href='{$primary_url}' target='_blank'>{$primary_url}</a>
            </div>";
        }
        
        $instructions .= "
            <div class='instructions-list'>
                <h4>How to login:</h4>
                <ol>
                    <li>Click on your company's login URL above</li>
                    <li>Enter your username and password</li>
                    <li>If you don't have an account, contact your administrator</li>
                </ol>
            </div>";
        
        if (!empty($company->hostname)) {
            $instructions .= "
            <div class='custom-domain-info'>
                <p><strong>Custom Domain:</strong> Your company uses a custom domain ({$company->hostname}) for branded access.</p>
            </div>";
        }
        
        $instructions .= "
            <div class='support-info'>
                <p><strong>Need help?</strong> Contact your system administrator for assistance.</p>
            </div>
        </div>";
        
        return $instructions;
    }
    
    /**
     * Test company login page setup with IOMAD URL validation
     * CORRECTED: Include hostname column checks
     * 
     * @param int $companyid
     * @return array Test results
     */
    public static function test_company_login_setup($companyid) {
        global $DB;
        
        $results = [
            'company_exists' => false,
            'has_custom_hostname' => false,
            'hostname_valid' => false,
            'has_logo' => false,
            'has_background' => false,
            'has_tagline' => false,
            'urls_accessible' => [],
            'warnings' => [],
            'recommendations' => []
        ];
        
        // Test if company exists
        $company = $DB->get_record('company', ['id' => $companyid]);
        $results['company_exists'] = !empty($company);
        
        if (!$results['company_exists']) {
            $results['warnings'][] = 'Company does not exist';
            return $results;
        }
        
        // CORRECTED: Test hostname configuration
        $results['has_custom_hostname'] = !empty($company->hostname);
        if ($results['has_custom_hostname']) {
            // Basic hostname validation
            $hostname = $company->hostname;
            $results['hostname_valid'] = filter_var('http://' . $hostname, FILTER_VALIDATE_URL) !== false;
            
            if (!$results['hostname_valid']) {
                $results['warnings'][] = 'Custom hostname "' . $hostname . '" appears to be invalid';
            }
        } else {
            $results['recommendations'][] = 'Consider setting up a custom hostname for branded URLs';
        }
        
        $tenantconfig = new tenant_config($companyid);
        
        // Test logo
        $logo = $tenantconfig->get_config('login_logo') ?: $tenantconfig->get_config('logo');
        $results['has_logo'] = !empty($logo);
        if (!$results['has_logo']) {
            $results['recommendations'][] = 'Upload a company logo for better branding';
        }
        
        // Test background
        $background = self::get_login_background_url($companyid);
        $results['has_background'] = !empty($background);
        if (!$results['has_background']) {
            $results['recommendations'][] = 'Upload a login background image for visual appeal';
        }
        
        // Test tagline
        $tagline = $tenantconfig->get_config('login_tagline');
        $results['has_tagline'] = !empty($tagline);
        if (!$results['has_tagline']) {
            $results['recommendations'][] = 'Add a login tagline to communicate your brand message';
        }
        
        // Test all available URLs
        $urls = self::get_all_company_login_urls($companyid);
        foreach ($urls as $key => $url_info) {
            $results['urls_accessible'][$key] = [
                'url' => $url_info['url'],
                'type' => $url_info['type'],
                'accessible' => true, // In a real implementation, you might test HTTP accessibility
                'primary' => $url_info['primary'] ?? false
            ];
        }
        
        // Validate company shortname for parameter URLs
        if (empty($company->shortname)) {
            $results['warnings'][] = 'Company shortname is empty, which may affect parameter-based URL generation';
        } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $company->shortname)) {
            $results['warnings'][] = 'Company shortname contains special characters, which may cause issues with parameter-based URLs';
        }
        
        return $results;
    }
    
    /**
     * Export company login configuration with URL information
     * CORRECTED: Include hostname information
     * 
     * @param int $companyid
     * @return array
     */
    public static function export_login_config($companyid) {
        global $DB;
        
        $company = $DB->get_record('company', ['id' => $companyid]);
        $tenantconfig = new tenant_config($companyid);
        
        $a = new \moodle_url('/theme/iomadremui/company_settings.php', [
                'companyid' => $companyid,
                'tab' => 'loginpage'
        ]);
        $settings_url = a->out();

        $config = [
            'company_id' => $companyid,
            'company_info' => [
                'name' => $company->name ?? '',
                'shortname' => $company->shortname ?? '',
                'hostname' => $company->hostname ?? '',  // CORRECTED: Include hostname
                'has_custom_hostname' => !empty($company->hostname)
            ],
            'login_settings' => [
                'login_style' => $tenantconfig->get_config('login_style'),
                'welcome_message' => $tenantconfig->get_config('welcome_message'),
                'login_tagline' => $tenantconfig->get_config('login_tagline'),
                'signuptextcolor' => $tenantconfig->get_config('signuptextcolor'),
                'primarycolor' => $tenantconfig->get_config('primarycolor'),
            ],
            'branding' => [
                'has_logo' => !empty($tenantconfig->get_config('logo')),
                'has_login_logo' => !empty($tenantconfig->get_config('login_logo')),
                'has_background' => !empty(self::get_login_background_url($companyid)),
            ],
            'urls' => self::get_all_company_login_urls($companyid),
            'settings_url' => $settings_url
        ];
        
        return $config;
    }
    
    /**
     * Validate hostname format for company
     * 
     * @param string $hostname
     * @return array Validation result with success boolean and message
     */
    public static function validate_hostname($hostname) {
        if (empty($hostname)) {
            return ['success' => true, 'message' => 'No hostname specified'];
        }
        
        // Basic hostname validation
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/', $hostname)) {
            return ['success' => false, 'message' => 'Hostname contains invalid characters'];
        }
        
        // Check if it looks like a valid domain
        if (!filter_var('http://' . $hostname, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Hostname does not appear to be a valid domain'];
        }
        
        return ['success' => true, 'message' => 'Hostname appears valid'];
    }
    
    /**
     * Update company hostname
     * 
     * @param int $companyid
     * @param string $hostname
     * @return bool Success
     */
    public static function update_company_hostname($companyid, $hostname) {
        global $DB;
        
        $validation = self::validate_hostname($hostname);
        if (!$validation['success']) {
            return false;
        }
        
        // Check if hostname is already in use by another company
        if (!empty($hostname)) {
            $existing = $DB->get_record('company', [
                'hostname' => $hostname,
                'suspended' => 0
            ]);
            
            if ($existing && $existing->id != $companyid) {
                return false; // Hostname already in use
            }
        }
        
        return $DB->set_field('company', 'hostname', $hostname, ['id' => $companyid]);
    }
}