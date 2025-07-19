<?php
namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

/**
 * ENHANCED: Settings management class for IOMAD RemUI theme
 * Provides comprehensive company configuration management
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
            $currentvalues[$fieldkey] = $tenantconfig->get_config($fieldkey, $fieldconfig['default'] ?? '');
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
     * Process company settings form submission with enhanced validation
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
                // Handle file uploads
                if ($fieldconfig['type'] === 'file') {
                    $filevalue = self::handle_file_upload($companyid, $key, $fieldconfig['filearea']);
                    if ($filevalue !== null) {
                        $value = $filevalue;
                    } else {
                        continue; // Skip if no file was uploaded
                    }
                }
                
                // Validate and sanitize value
                $value = self::validate_setting_value($value, $fieldconfig);
                
                if ($value !== null) {
                    $success = $tenantconfig->set_config($key, $value, $fieldconfig['type']);
                    if ($success) {
                        $result['messages'][] = get_string('settingsaved', 'core', get_string($key, 'theme_iomadremui'));
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
        
        return $result;
    }
    
    /**
     * Handle file upload for company settings with enhanced security
     * @param int $companyid
     * @param string $settingkey
     * @param string $filearea
     * @return string|null File URL or null if no file
     */
    private static function handle_file_upload($companyid, $settingkey, $filearea) {
        global $USER;
        
        $context = \context_system::instance();
        $fs = get_file_storage();
        
        // Get the file from the draft area
        $draftid = optional_param($settingkey, 0, PARAM_INT);
        if (!$draftid) {
            return null;
        }
        
        // Validate file area
        $allowedareas = ['companylogo', 'companyfavicon', 'herobackground', 'loginbackground', 'loginlogo'];
        if (!in_array($filearea, $allowedareas)) {
            throw new \moodle_exception('invalidfilearea', 'theme_iomadremui');
        }
        
        // File restrictions
        $fileoptions = [
            'subdirs' => false,
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
            'maxbytes' => 2 * 1024 * 1024, // 2MB limit
        ];
        
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
                if (isset($field['options']) && !in_array($value, $field['options'])) {
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
     * Export company settings to JSON
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
                }
            }
        }
        
        return json_encode($allsettings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import company settings from JSON
     * @param int $companyid
     * @param string $json
     * @return array Result with success status and messages
     */
    public static function import_company_settings($companyid, $json) {
        $result = ['success' => true, 'messages' => []];
        
        try {
            $settings = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format');
            }
            
            $tenantconfig = new tenant_config($companyid);
            $imported = 0;
            
            foreach ($settings as $key => $setting) {
                if (!isset($setting['value']) || !isset($setting['type'])) {
                    continue;
                }
                
                $success = $tenantconfig->set_config($key, $setting['value'], $setting['type']);
                if ($success) {
                    $imported++;
                } else {
                    $result['messages'][] = 'Failed to import setting: ' . $key;
                }
            }
            
            $result['messages'][] = "Successfully imported {$imported} settings";
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['messages'][] = 'Import failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Reset company settings to defaults
     * @param int $companyid
     * @param string|null $tab Reset specific tab or all settings
     * @return array Result with success status and messages
     */
    public static function reset_company_settings($companyid, $tab = null) {
        global $DB;
        
        $result = ['success' => true, 'messages' => []];
        
        try {
            if ($tab) {
                // Reset specific tab
                $tenantconfig = new tenant_config($companyid);
                $tabs = $tenantconfig->get_config_tabs();
                
                if (isset($tabs[$tab])) {
                    foreach ($tabs[$tab]['fields'] as $fieldkey => $fieldconfig) {
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
     * Get company settings summary for dashboard
     * @param int $companyid
     * @return array Summary data
     */
    public static function get_settings_summary($companyid) {
        $tenantconfig = new tenant_config($companyid);
        $tabs = $tenantconfig->get_config_tabs();
        
        $summary = [
            'total_settings' => 0,
            'configured_settings' => 0,
            'tabs' => []
        ];
        
        foreach ($tabs as $tabname => $tabdata) {
            $tab_configured = 0;
            $tab_total = count($tabdata['fields']);
            
            foreach ($tabdata['fields'] as $fieldkey => $fieldconfig) {
                $value = $tenantconfig->get_config($fieldkey);
                if ($value !== null && $value !== '') {
                    $tab_configured++;
                }
            }
            
            $summary['tabs'][$tabname] = [
                'total' => $tab_total,
                'configured' => $tab_configured,
                'percentage' => $tab_total > 0 ? round(($tab_configured / $tab_total) * 100) : 0
            ];
            
            $summary['total_settings'] += $tab_total;
            $summary['configured_settings'] += $tab_configured;
        }
        
        $summary['overall_percentage'] = $summary['total_settings'] > 0 
            ? round(($summary['configured_settings'] / $summary['total_settings']) * 100) 
            : 0;
        
        return $summary;
    }
}