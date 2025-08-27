<?php
namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

/**
 * ENHANCED: Settings management class with login background upload support
 */
class settings_manager {
    
    /**
     * Render company settings form
     * @param int $companyid
     * @param string $tab
     * @return string HTML
     */
    public static function render_company_settings_form($companyid, $tab = 'basic') {
        global $OUTPUT, $PAGE;
        
        $tenantconfig = new tenant_config($companyid);
        $tabs = $tenantconfig->get_config_tabs();
        
        if (!isset($tabs[$tab])) {
            $tab = 'basic';
        }
        
        $PAGE->requires->js_call_amd('theme_iomadremui/settings', 'init');
        
        // Get current values for all fields in this tab
        $currentvalues = [];
        foreach ($tabs[$tab]['fields'] as $fieldkey => $fieldconfig) {
            $value = $tenantconfig->get_config($fieldkey, $fieldconfig['default'] ?? '');
            
            // ENHANCED: Handle file fields - get actual file URLs
            if ($fieldconfig['type'] === 'file') {
                $fileurl = self::get_file_url($companyid, $fieldkey, $fieldconfig['filearea']);
                $currentvalues[$fieldkey] = $fileurl;
            } else {
                $currentvalues[$fieldkey] = $value;
            }
        }
        
        $context = [
            'companyid' => $companyid,
            'currenttab' => $tab,
            'tabs' => array_keys($tabs),
            'tabdata' => $tabs[$tab],
            'currentvalues' => $currentvalues,
            'formaction' => new \moodle_url('/theme/iomadremui/company_settings.php'),
            'sesskey' => sesskey(),
            'backurl' => new \moodle_url('/local/iomad_dashboard/index.php')
        ];
        
        return $OUTPUT->render_from_template('theme_iomadremui/company_settings_form', $context);
    }
    
    /**
     * Process company settings form submission with enhanced file handling
     * @param int $companyid
     * @param array $data
     * @return array Result with success status and messages
     */
    public static function process_company_settings($companyid, $data) {
        $result = ['success' => true, 'messages' => []];
        $tenantconfig = new tenant_config($companyid);
        $tabs = $tenantconfig->get_config_tabs();
        
        foreach ($data as $key => $value) {
            // Skip non-setting fields
            if (in_array($key, ['sesskey', 'companyid', 'tab', '_qf__company_settings_form'])) {
                continue;
            }
            
            // Find which tab this setting belongs to
            $fieldconfig = null;
            foreach ($tabs as $tabname => $tabdata) {
                if (isset($tabdata['fields'][$key])) {
                    $fieldconfig = $tabdata['fields'][$key];
                    break;
                }
            }
            
            if (!$fieldconfig) {
                continue; // Skip unknown fields
            }
            
            try {
                // ENHANCED: Handle file uploads with specific support for login backgrounds
                if ($fieldconfig['type'] === 'file') {
                    $filevalue = self::handle_file_upload($companyid, $key, $fieldconfig);
                    if ($filevalue !== null) {
                        $value = $filevalue;
                        
                        // Special handling for login background
                        if ($key === 'login_background') {
                            $result['messages'][] = get_string('login_background_uploaded', 'theme_iomadremui');
                        } elseif ($key === 'login_logo') {
                            $result['messages'][] = get_string('login_logo_uploaded', 'theme_iomadremui');
                        }
                    } else {
                        continue; // Skip if no file was uploaded
                    }
                }
                
                // Validate and sanitize value
                $value = self::validate_setting_value($value, $fieldconfig);
                
                if ($value !== null) {
                    $success = $tenantconfig->set_config($key, $value, $fieldconfig['type']);
                    if ($success) {
                        if ($fieldconfig['type'] !== 'file') { // Don't duplicate file upload messages
                            $result['messages'][] = get_string('settingsaved', 'core', get_string($key, 'theme_iomadremui'));
                        }
                    } else {
                        $result['success'] = false;
                        $result['messages'][] = get_string('settingnotsaved', 'core', get_string($key, 'theme_iomadremui'));
                    }
                }
            } catch (Exception $e) {
                $result['success'] = false;
                $result['messages'][] = 'Error saving ' . $key . ': ' . $e->getMessage();
            }
        }
        
        // ENHANCED: Clear theme cache after saving
        theme_reset_all_caches();
        
        return $result;
    }
    
    /**
     * ENHANCED: Handle file upload for company settings with specific login background support
     * @param int $companyid
     * @param string $settingkey
     * @param array $fieldconfig
     * @return string|null File URL or null if no file
     */
    private static function handle_file_upload($companyid, $settingkey, $fieldconfig) {
        global $USER;
        
        $context = \context_system::instance();
        $fs = get_file_storage();
        
        // Get the file from the draft area
        $draftid = optional_param($settingkey, 0, PARAM_INT);
        if (!$draftid) {
            return null;
        }
        
        // Validate file area
        $filearea = $fieldconfig['filearea'];
        $allowedareas = ['companylogo', 'companyfavicon', 'herobackground', 'loginbackground', 'loginlogo'];
        if (!in_array($filearea, $allowedareas)) {
            throw new \moodle_exception('invalidfilearea', 'theme_iomadremui');
        }
        
        // ENHANCED: File restrictions based on file type
        $fileoptions = [
            'subdirs' => false,
            'maxfiles' => 1,
            'accepted_types' => $fieldconfig['accepted_types'] ?? ['web_image'],
            'maxbytes' => $fieldconfig['maxbytes'] ?? 2097152, // Default 2MB
        ];
        
        // Special handling for login backgrounds (larger file size allowed)
        if ($filearea === 'loginbackground' || $filearea === 'herobackground') {
            $fileoptions['maxbytes'] = 5242880; // 5MB for background images
        }
        
        // Save the file to the proper filearea
        $filearea_with_company = $filearea . '_' . $companyid;
        
        // Delete existing files first
        $fs->delete_area_files($context->id, 'theme_iomadremui', $filearea_with_company, 0);
        
        file_save_draft_area_files(
            $draftid,
            $context->id,
            'theme_iomadremui',
            $filearea_with_company,
            0,
            $fileoptions
        );
        
        // Return the file URL
        $files = $fs->get_area_files($context->id, 'theme_iomadremui', $filearea_with_company, 0, 'filename', false);
        if ($files) {
            $file = reset($files);
            return \moodle_url::make_pluginfile_url(
                $context->id,
                'theme_iomadremui',
                $filearea_with_company,
                0,
                '/',
                $file->get_filename()
            )->out();
        }
        
        return null;
    }
    
    /**
     * Get file URL for a specific company setting
     * @param int $companyid
     * @param string $settingkey
     * @param string $filearea
     * @return string|null
     */
    private static function get_file_url($companyid, $settingkey, $filearea) {
        $context = \context_system::instance();
        $fs = get_file_storage();
        
        $filearea_with_company = $filearea . '_' . $companyid;
        $files = $fs->get_area_files($context->id, 'theme_iomadremui', $filearea_with_company, 0, 'filename', false);
        
        if ($files) {
            $file = reset($files);
            return \moodle_url::make_pluginfile_url(
                $context->id,
                'theme_iomadremui',
                $filearea_with_company,
                0,
                '/',
                $file->get_filename()
            )->out();
        }
        
        return null;
    }
    
    /**
     * Enhanced setting value validation with security measures
     * @param mixed $value
     * @param array $field
     * @return mixed Validated value or null if invalid
     */
    private static function validate_setting_value($value, $field) {
        switch ($field['type']) {
            case 'color':
                if (!preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                    return null;
                }
                break;
                
            case 'number':
                $value = (float)$value;
                if (isset($field['min']) && $value < $field['min']) {
                    $value = $field['min'];
                }
                if (isset($field['max']) && $value > $field['max']) {
                    $value = $field['max'];
                }
                break;
                
            case 'select':
                if (isset($field['options']) && !array_key_exists($value, $field['options'])) {
                    return null;
                }
                break;
                
            case 'checkbox':
                $value = $value ? 1 : 0;
                break;
                
            case 'text':
                $value = clean_param($value, PARAM_TEXT);
                if (isset($field['maxlength']) && strlen($value) > $field['maxlength']) {
                    $value = substr($value, 0, $field['maxlength']);
                }
                break;
                
            case 'textarea':
                $value = clean_text($value, FORMAT_PLAIN);
                break;
                
            case 'editor':
                $value = clean_text($value, FORMAT_HTML);
                break;
                
            case 'url':
                $value = clean_param($value, PARAM_URL);
                break;
        }
        
        return $value;
    }
    
    /**
     * ENHANCED: Generate company login preview
     * @param int $companyid
     * @return string HTML preview
     */
    public static function generate_login_preview($companyid) {
        $helper = new \theme_iomadremui\login_helper();
        $context = $helper->get_login_page_context($companyid);
        
        if (empty($context)) {
            return '<div class="alert alert-info">No company context available for preview</div>';
        }
        
        $preview_html = '
        <div class="login-preview-container" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; max-width: 600px;">
            <div class="preview-header" style="background: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd;">
                <strong>Login Page Preview - Company ID: ' . $companyid . '</strong>
                <a href="' . $helper->get_company_login_url($companyid)->out() . '" target="_blank" class="btn btn-sm btn-primary float-right">Open Login Page</a>
            </div>
            <div class="preview-content" style="position: relative; height: 300px; overflow: hidden;">';
        
        // Background preview
        if (!empty($context['background'])) {
            $preview_html .= '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url(' . $context['background'] . '); background-size: cover; background-position: center;"></div>';
        } else {
            $preview_html .= '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, ' . $context['primarycolor'] . ', ' . $context['signuptextcolor'] . ');"></div>';
        }
        
        // Content overlay
        $preview_html .= '<div style="position: relative; z-index: 1; padding: 20px; background: rgba(255,255,255,0.9); margin: 20px; border-radius: 8px;">';
        
        // Logo
        if (!empty($context['logo'])) {
            $preview_html .= '<div style="text-align: center; margin-bottom: 15px;"><img src="' . $context['logo'] . '" style="max-height: 40px;" alt="Company Logo"></div>';
        }
        
        // Welcome message
        if (!empty($context['welcome_message'])) {
            $preview_html .= '<div style="text-align: center; margin-bottom: 15px; color: ' . $context['signuptextcolor'] . ';">' . $context['welcome_message'] . '</div>';
        }
        
        // Tagline
        if (!empty($context['login_tagline'])) {
            $preview_html .= '<div style="text-align: center; margin-bottom: 15px; font-style: italic; color: ' . $context['signuptextcolor'] . ';">' . $context['login_tagline'] . '</div>';
        }
        
        // Mock login form
        $preview_html .= '
            <div style="text-align: center;">
                <div style="display: inline-block; text-align: left;">
                    <input type="text" placeholder="Username" style="display: block; width: 200px; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <input type="password" placeholder="Password" style="display: block; width: 200px; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <button style="width: 100%; padding: 10px; background: ' . $context['primarycolor'] . '; color: white; border: none; border-radius: 4px;">Log in</button>
                </div>
            </div>';
        
        $preview_html .= '</div></div></div>';
        
        return $preview_html;
    }
    
    /**
     * Export company settings to JSON with enhanced login data
     * @param int $companyid
     * @return string JSON string
     */
    public static function export_company_settings($companyid) {
        $tenantconfig = new tenant_config($companyid);
        $allsettings = [];
        
        $tabs = $tenantconfig->get_config_tabs();
        foreach ($tabs as $tabname => $tabdata) {
            foreach ($tabdata['fields'] as $fieldkey => $fieldconfig) {
                $value = $tenantconfig->get_config($fieldkey);
                if ($value !== null) {
                    $allsettings[$fieldkey] = [
                        'value' => $value,
                        'type' => $fieldconfig['type'],
                        'tab' => $tabname
                    ];
                    
                    // ENHANCED: Include file URLs for file fields
                    if ($fieldconfig['type'] === 'file') {
                        $fileurl = self::get_file_url($companyid, $fieldkey, $fieldconfig['filearea']);
                        $allsettings[$fieldkey]['file_url'] = $fileurl;
                    }
                }
            }
        }
        
        // Add metadata
        $export_data = [
            'export_date' => date('Y-m-d H:i:s'),
            'company_id' => $companyid,
            'theme_version' => get_config('theme_iomadremui', 'version'),
            'settings' => $allsettings
        ];
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * ENHANCED: Test login page configuration
     * @param int $companyid
     * @return array Test results
     */
    public static function test_login_configuration($companyid) {
        $helper = new \theme_iomadremui\login_helper();
        return $helper->test_company_login_setup($companyid);
    }
    
    /**
     * ENHANCED: Generate login setup instructions
     * @param int $companyid
     * @return string HTML instructions
     */
    public static function generate_login_instructions($companyid) {
        $helper = new \theme_iomadremui\login_helper();
        return $helper->generate_login_instructions($companyid);
    }
    
    /**
     * Import company settings from JSON with validation
     * @param int $companyid
     * @param string $json
     * @return array Result with success status and messages
     */
    public static function import_company_settings($companyid, $json) {
        $result = ['success' => true, 'messages' => []];
        
        try {
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format');
            }
            
            // Validate import data structure
            if (!isset($data['settings'])) {
                throw new \Exception('Invalid import format: missing settings data');
            }
            
            $tenantconfig = new tenant_config($companyid);
            $imported = 0;
            $skipped = 0;
            
            foreach ($data['settings'] as $key => $setting) {
                if (!isset($setting['value']) || !isset($setting['type'])) {
                    $skipped++;
                    continue;
                }
                
                // Skip file uploads in import (they need to be handled separately)
                if ($setting['type'] === 'file') {
                    $result['messages'][] = "Skipped file setting: {$key} (files must be uploaded manually)";
                    $skipped++;
                    continue;
                }
                
                $success = $tenantconfig->set_config($key, $setting['value'], $setting['type']);
                if ($success) {
                    $imported++;
                } else {
                    $result['messages'][] = 'Failed to import setting: ' . $key;
                }
            }
            
            $result['messages'][] = "Successfully imported {$imported} settings, skipped {$skipped} settings";
            
            // Clear cache after import
            theme_reset_all_caches();
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['messages'][] = 'Import failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Reset company settings to defaults with enhanced options
     * @param int $companyid
     * @param string|null $tab Reset specific tab or all settings
     * @param bool $keep_files Whether to keep uploaded files
     * @return array Result with success status and messages
     */
    public static function reset_company_settings($companyid, $tab = null, $keep_files = false) {
        global $DB;
        
        $result = ['success' => true, 'messages' => []];
        
        try {
            if ($tab) {
                // Reset specific tab
                $tenantconfig = new tenant_config($companyid);
                $tabs = $tenantconfig->get_config_tabs();
                
                if (isset($tabs[$tab])) {
                    foreach ($tabs[$tab]['fields'] as $fieldkey => $fieldconfig) {
                        // Handle file fields specially if not keeping files
                        if ($fieldconfig['type'] === 'file' && !$keep_files) {
                            self::delete_company_file($companyid, $fieldconfig['filearea']);
                        }
                        
                        $DB->delete_records('iomadremui_company_config', [
                            'companyid' => $companyid,
                            'configkey' => $fieldkey
                        ]);
                    }
                    $result['messages'][] = "Reset {$tab} settings to defaults";
                } else {
                    $result['success'] = false;
                    $result['messages'][] = "Invalid tab: {$tab}";
                }
            } else {
                // Reset all settings
                if (!$keep_files) {
                    // Delete all company files
                    $file_areas = ['companylogo', 'companyfavicon', 'loginbackground', 'loginlogo', 'herobackground'];
                    foreach ($file_areas as $area) {
                        self::delete_company_file($companyid, $area);
                    }
                }
                
                $DB->delete_records('iomadremui_company_config', ['companyid' => $companyid]);
                $result['messages'][] = 'Reset all settings to defaults';
            }
            
            // Clear theme cache
            theme_reset_all_caches();
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['messages'][] = 'Reset failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Delete company file from specific area
     * @param int $companyid
     * @param string $filearea
     */
    private static function delete_company_file($companyid, $filearea) {
        $context = \context_system::instance();
        $fs = get_file_storage();
        
        $filearea_with_company = $filearea . '_' . $companyid;
        $fs->delete_area_files($context->id, 'theme_iomadremui', $filearea_with_company, 0);
    }
    
    /**
     * Get company settings summary for dashboard with enhanced file info
     * @param int $companyid
     * @return array Summary data
     */
    public static function get_settings_summary($companyid) {
        $tenantconfig = new tenant_config($companyid);
        $tabs = $tenantconfig->get_config_tabs();
        
        $summary = [
            'total_settings' => 0,
            'configured_settings' => 0,
            'uploaded_files' => 0,
            'tabs' => []
        ];
        
        foreach ($tabs as $tabname => $tabdata) {
            $tab_configured = 0;
            $tab_total = count($tabdata['fields']);
            $tab_files = 0;
            
            foreach ($tabdata['fields'] as $fieldkey => $fieldconfig) {
                $value = $tenantconfig->get_config($fieldkey);
                
                if ($fieldconfig['type'] === 'file') {
                    $fileurl = self::get_file_url($companyid, $fieldkey, $fieldconfig['filearea']);
                    if ($fileurl) {
                        $tab_configured++;
                        $tab_files++;
                    }
                } elseif ($value !== null && $value !== '') {
                    $tab_configured++;
                }
            }
            
            $summary['tabs'][$tabname] = [
                'total' => $tab_total,
                'configured' => $tab_configured,
                'files' => $tab_files,
                'percentage' => $tab_total > 0 ? round(($tab_configured / $tab_total) * 100) : 0
            ];
            
            $summary['total_settings'] += $tab_total;
            $summary['configured_settings'] += $tab_configured;
            $summary['uploaded_files'] += $tab_files;
        }
        
        $summary['overall_percentage'] = $summary['total_settings'] > 0 
            ? round(($summary['configured_settings'] / $summary['total_settings']) * 100) 
            : 0;
        
        return $summary;
    }
}