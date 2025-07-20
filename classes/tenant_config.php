<?php
namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

/**
 * ENHANCED: Tenant configuration management class with comprehensive field definitions
 */
class tenant_config {
    
    /** @var int Company ID */
    private $companyid;
    
    /** @var array Configuration cache */
    private static $configcache = [];
    
    /**
     * Constructor
     * @param int $companyid
     */
    public function __construct($companyid) {
        $this->companyid = $companyid;
    }
    
    /**
     * Get configuration value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_config($key, $default = null) {
        if (!isset(self::$configcache[$this->companyid])) {
            $this->load_config();
        }
        
        return isset(self::$configcache[$this->companyid][$key]) 
            ? self::$configcache[$this->companyid][$key] 
            : $default;
    }
    
    /**
     * Set configuration value
     * @param string $key
     * @param mixed $value
     * @param string $type
     */
    public function set_config($key, $value, $type = 'text') {
        global $DB;
        
        $record = $DB->get_record('iomadremui_company_config', [
            'companyid' => $this->companyid,
            'configkey' => $key
        ]);
        
        $data = (object)[
            'companyid' => $this->companyid,
            'configkey' => $key,
            'configvalue' => $value,
            'configtype' => $type,
            'timemodified' => time()
        ];
        
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('iomadremui_company_config', $data);
        } else {
            $data->timecreated = time();
            $DB->insert_record('iomadremui_company_config', $data);
        }
        
        // Update cache
        self::$configcache[$this->companyid][$key] = $value;
        
        // Clear theme cache
        theme_reset_all_caches();
        
        return true;
    }
    
    /**
     * Load configuration from database
     */
    private function load_config() {
        global $DB;
        
        $configs = $DB->get_records('iomadremui_company_config', ['companyid' => $this->companyid]);
        
        self::$configcache[$this->companyid] = [];
        foreach ($configs as $config) {
            self::$configcache[$this->companyid][$config->configkey] = $config->configvalue;
        }
    }
    
    /**
     * ENHANCED: Get all configuration tabs structure with comprehensive field definitions
     */
    public function get_config_tabs() {
        return [
            'basic' => [
                'title' => get_string('basicsettings', 'theme_iomadremui'),
                'icon' => 'fa-cog',
                'fields' => [
                    'logo' => [
                        'type' => 'file',
                        'filearea' => 'companylogo',
                        'label' => get_string('logo', 'theme_iomadremui'),
                        'description' => get_string('logo_desc', 'theme_iomadremui'),
                        'accepted_types' => ['web_image'],
                        'maxbytes' => 2097152, // 2MB
                        'default' => ''
                    ],
                    'favicon' => [
                        'type' => 'file',
                        'filearea' => 'companyfavicon',
                        'label' => get_string('favicon', 'theme_iomadremui'),
                        'description' => get_string('favicon_desc', 'theme_iomadremui'),
                        'accepted_types' => ['web_image'],
                        'maxbytes' => 1048576, // 1MB
                        'default' => ''
                    ],
                    'primarycolor' => [
                        'type' => 'color',
                        'label' => get_string('primarycolor', 'theme_iomadremui'),
                        'description' => get_string('primarycolor_desc', 'theme_iomadremui'),
                        'default' => '#007bff'
                    ],
                    'secondarycolor' => [
                        'type' => 'color',
                        'label' => get_string('secondarycolor', 'theme_iomadremui'),
                        'description' => get_string('secondarycolor_desc', 'theme_iomadremui'),
                        'default' => '#6c757d'
                    ],
                    'fontfamily' => [
                        'type' => 'select',
                        'label' => get_string('fontfamily', 'theme_iomadremui'),
                        'description' => get_string('fontfamily_desc', 'theme_iomadremui'),
                        'options' => [
                            '"Segoe UI", Roboto, sans-serif' => 'Segoe UI / Roboto',
                            '"Helvetica Neue", Helvetica, Arial, sans-serif' => 'Helvetica',
                            'Georgia, "Times New Roman", serif' => 'Georgia',
                            '"Courier New", monospace' => 'Courier New',
                            '"Open Sans", sans-serif' => 'Open Sans',
                            '"Montserrat", sans-serif' => 'Montserrat',
                            '"Poppins", sans-serif' => 'Poppins',
                            '"Lato", sans-serif' => 'Lato',
                        ],
                        'default' => '"Segoe UI", Roboto, sans-serif'
                    ],
                    'fontsize' => [
                        'type' => 'number',
                        'label' => get_string('fontsize', 'theme_iomadremui'),
                        'description' => get_string('fontsize_desc', 'theme_iomadremui'),
                        'min' => 0.8,
                        'max' => 2.0,
                        'step' => 0.1,
                        'default' => 1.0
                    ],
                    'container_class' => [
                        'type' => 'select',
                        'label' => get_string('container_class', 'theme_iomadremui'),
                        'description' => get_string('container_class_desc', 'theme_iomadremui'),
                        'options' => [
                            'container' => get_string('container', 'theme_iomadremui'),
                            'container-fluid' => get_string('container_fluid', 'theme_iomadremui')
                        ],
                        'default' => 'container'
                    ]
                ]
            ],
            'dashboard' => [
                'title' => get_string('dashboardsettings', 'theme_iomadremui'),
                'icon' => 'fa-dashboard',
                'fields' => [
                    'dashboard_layout' => [
                        'type' => 'select',
                        'label' => get_string('dashboard_layout', 'theme_iomadremui'),
                        'description' => get_string('dashboard_layout_desc', 'theme_iomadremui'),
                        'options' => [
                            'grid' => get_string('grid', 'theme_iomadremui'),
                            'list' => get_string('list', 'theme_iomadremui'),
                            'cards' => get_string('cards', 'theme_iomadremui')
                        ],
                        'default' => 'grid'
                    ],
                    'show_analytics' => [
                        'type' => 'checkbox',
                        'label' => get_string('show_analytics', 'theme_iomadremui'),
                        'description' => get_string('show_analytics_desc', 'theme_iomadremui'),
                        'default' => 1
                    ],
                    'show_quick_access' => [
                        'type' => 'checkbox',
                        'label' => get_string('show_quick_access', 'theme_iomadremui'),
                        'description' => get_string('show_quick_access_desc', 'theme_iomadremui'),
                        'default' => 1
                    ],
                    'dashboard_widgets' => [
                        'type' => 'textarea',
                        'label' => get_string('dashboard_widgets', 'theme_iomadremui'),
                        'description' => get_string('dashboard_widgets_desc', 'theme_iomadremui'),
                        'rows' => 6,
                        'default' => ''
                    ]
                ]
            ],
            'homepage' => [
                'title' => get_string('homepagesettings', 'theme_iomadremui'),
                'icon' => 'fa-home',
                'fields' => [
                    'hero_title' => [
                        'type' => 'text',
                        'label' => get_string('hero_title', 'theme_iomadremui'),
                        'description' => get_string('hero_title_desc', 'theme_iomadremui'),
                        'maxlength' => 100,
                        'default' => ''
                    ],
                    'hero_subtitle' => [
                        'type' => 'text',
                        'label' => get_string('hero_subtitle', 'theme_iomadremui'),
                        'description' => get_string('hero_subtitle_desc', 'theme_iomadremui'),
                        'maxlength' => 200,
                        'default' => ''
                    ],
                    'hero_background' => [
                        'type' => 'file',
                        'filearea' => 'herobackground',
                        'label' => get_string('hero_background', 'theme_iomadremui'),
                        'description' => get_string('hero_background_desc', 'theme_iomadremui'),
                        'accepted_types' => ['web_image'],
                        'maxbytes' => 5242880, // 5MB
                        'default' => ''
                    ],
                    'featured_courses' => [
                        'type' => 'checkbox',
                        'label' => get_string('featured_courses', 'theme_iomadremui'),
                        'description' => get_string('featured_courses_desc', 'theme_iomadremui'),
                        'default' => 1
                    ],
                    'homepage_content' => [
                        'type' => 'editor',
                        'label' => get_string('homepage_content', 'theme_iomadremui'),
                        'description' => get_string('homepage_content_desc', 'theme_iomadremui'),
                        'default' => ''
                    ]
                ]
            ],
            'coursepage' => [
                'title' => get_string('coursepagesettings', 'theme_iomadremui'),
                'icon' => 'fa-book',
                'fields' => [
                    'course_layout' => [
                        'type' => 'select',
                        'label' => get_string('course_layout', 'theme_iomadremui'),
                        'description' => get_string('course_layout_desc', 'theme_iomadremui'),
                        'options' => [
                            'card' => get_string('cards', 'theme_iomadremui'),
                            'list' => get_string('list', 'theme_iomadremui'),
                            'grid' => get_string('grid', 'theme_iomadremui')
                        ],
                        'default' => 'card'
                    ],
                    'show_progress' => [
                        'type' => 'checkbox',
                        'label' => get_string('show_progress', 'theme_iomadremui'),
                        'description' => get_string('show_progress_desc', 'theme_iomadremui'),
                        'default' => 1
                    ],
                    'show_company_logo_on_courses' => [
                        'type' => 'checkbox',
                        'label' => get_string('show_company_logo_on_courses', 'theme_iomadremui'),
                        'description' => get_string('show_company_logo_on_courses_desc', 'theme_iomadremui'),
                        'default' => 1
                    ],
                    'show_company_header_on_frontpage' => [
                        'type' => 'checkbox',
                        'label' => get_string('show_company_header_on_frontpage', 'theme_iomadremui'),
                        'description' => get_string('show_company_header_on_frontpage_desc', 'theme_iomadremui'),
                        'default' => 1
                    ],
                    'course_sidebar' => [
                        'type' => 'select',
                        'label' => get_string('course_sidebar', 'theme_iomadremui'),
                        'description' => get_string('course_sidebar_desc', 'theme_iomadremui'),
                        'options' => [
                            'left' => get_string('left', 'theme_iomadremui'),
                            'right' => get_string('right', 'theme_iomadremui'),
                            'none' => get_string('none', 'theme_iomadremui')
                        ],
                        'default' => 'right'
                    ],
                    'course_header_style' => [
                        'type' => 'select',
                        'label' => get_string('course_header_style', 'theme_iomadremui'),
                        'description' => get_string('course_header_style_desc', 'theme_iomadremui'),
                        'options' => [
                            'default' => get_string('default', 'theme_iomadremui'),
                            'banner' => get_string('banner', 'theme_iomadremui'),
                            'minimal' => get_string('minimal', 'theme_iomadremui')
                        ],
                        'default' => 'default'
                    ]
                ]
            ],
            'footer' => [
                'title' => get_string('footersettings', 'theme_iomadremui'),
                'icon' => 'fa-info',
                'fields' => [
                    'footer_content' => [
                        'type' => 'editor',
                        'label' => get_string('footer_content', 'theme_iomadremui'),
                        'description' => get_string('footer_content_desc', 'theme_iomadremui'),
                        'default' => ''
                    ],
                    'footer_links' => [
                        'type' => 'textarea',
                        'label' => get_string('footer_links', 'theme_iomadremui'),
                        'description' => get_string('footer_links_desc', 'theme_iomadremui'),
                        'rows' => 4,
                        'default' => ''
                    ],
                    'contact_info' => [
                        'type' => 'textarea',
                        'label' => get_string('contact_info', 'theme_iomadremui'),
                        'description' => get_string('contact_info_desc', 'theme_iomadremui'),
                        'rows' => 4,
                        'default' => ''
                    ],
                    'social_media' => [
                        'type' => 'textarea',
                        'label' => get_string('social_media', 'theme_iomadremui'),
                        'description' => get_string('social_media_desc', 'theme_iomadremui'),
                        'rows' => 4,
                        'placeholder' => '{"facebook": "https://facebook.com/yourpage", "twitter": "https://twitter.com/yourhandle"}',
                        'default' => ''
                    ],
                    'copyright_text' => [
                        'type' => 'text',
                        'label' => get_string('copyright_text', 'theme_iomadremui'),
                        'description' => get_string('copyright_text_desc', 'theme_iomadremui'),
                        'maxlength' => 200,
                        'default' => ''
                    ]
                ]
            ],
            'loginpage' => [
                'title' => get_string('loginpagesettings', 'theme_iomadremui'),
                'icon' => 'fa-sign-in',
                'fields' => [
                    'login_background' => [
                        'type' => 'file',
                        'filearea' => 'loginbackground',
                        'label' => get_string('login_background', 'theme_iomadremui'),
                        'description' => get_string('login_background_desc', 'theme_iomadremui'),
                        'accepted_types' => ['web_image'],
                        'maxbytes' => 5242880, // 5MB
                        'default' => ''
                    ],
                    'login_style' => [
                        'type' => 'select',
                        'label' => get_string('login_style', 'theme_iomadremui'),
                        'description' => get_string('login_style_desc', 'theme_iomadremui'),
                        'options' => [
                            'default' => get_string('default', 'theme_iomadremui'),
                            'centered' => get_string('centered', 'theme_iomadremui'),
                            'split' => get_string('split', 'theme_iomadremui')
                        ],
                        'default' => 'default'
                    ],
                    'welcome_message' => [
                        'type' => 'editor',
                        'label' => get_string('welcome_message', 'theme_iomadremui'),
                        'description' => get_string('welcome_message_desc', 'theme_iomadremui'),
                        'default' => ''
                    ],
                    'login_logo' => [
                        'type' => 'file',
                        'filearea' => 'loginlogo',
                        'label' => get_string('login_logo', 'theme_iomadremui'),
                        'description' => get_string('login_logo_desc', 'theme_iomadremui'),
                        'accepted_types' => ['web_image'],
                        'maxbytes' => 2097152, // 2MB
                        'default' => ''
                    ]
                ]
            ],
            'advanced' => [
                'title' => get_string('advancedsettings', 'theme_iomadremui'),
                'icon' => 'fa-code',
                'fields' => [
                    'customcss' => [
                        'type' => 'textarea',
                        'label' => get_string('customcss', 'theme_iomadremui'),
                        'description' => get_string('customcss_desc', 'theme_iomadremui'),
                        'rows' => 10,
                        'class' => 'code-editor',
                        'default' => ''
                    ],
                    'customjs' => [
                        'type' => 'textarea',
                        'label' => get_string('customjs', 'theme_iomadremui'),
                        'description' => get_string('customjs_desc', 'theme_iomadremui'),
                        'rows' => 8,
                        'class' => 'code-editor',
                        'default' => ''
                    ],
                    'headcode' => [
                        'type' => 'textarea',
                        'label' => get_string('headcode', 'theme_iomadremui'),
                        'description' => get_string('headcode_desc', 'theme_iomadremui'),
                        'rows' => 6,
                        'class' => 'code-editor',
                        'default' => ''
                    ],
                    'footercode' => [
                        'type' => 'textarea',
                        'label' => get_string('footercode', 'theme_iomadremui'),
                        'description' => get_string('footercode_desc', 'theme_iomadremui'),
                        'rows' => 6,
                        'class' => 'code-editor',
                        'default' => ''
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get field configuration by key
     * @param string $fieldkey
     * @return array|null
     */
    public function get_field_config($fieldkey) {
        $tabs = $this->get_config_tabs();
        
        foreach ($tabs as $tab) {
            if (isset($tab['fields'][$fieldkey])) {
                return $tab['fields'][$fieldkey];
            }
        }
        
        return null;
    }
    
    /**
     * Validate field value according to field configuration
     * @param string $fieldkey
     * @param mixed $value
     * @return bool
     */
    public function validate_field_value($fieldkey, $value) {
        $fieldconfig = $this->get_field_config($fieldkey);
        
        if (!$fieldconfig) {
            return false;
        }
        
        switch ($fieldconfig['type']) {
            case 'color':
                return preg_match('/^#[a-fA-F0-9]{6}$/', $value);
                
            case 'number':
                if (!is_numeric($value)) {
                    return false;
                }
                if (isset($fieldconfig['min']) && $value < $fieldconfig['min']) {
                    return false;
                }
                if (isset($fieldconfig['max']) && $value > $fieldconfig['max']) {
                    return false;
                }
                return true;
                
            case 'select':
                return isset($fieldconfig['options']) && array_key_exists($value, $fieldconfig['options']);
                
            case 'text':
                if (isset($fieldconfig['maxlength']) && strlen($value) > $fieldconfig['maxlength']) {
                    return false;
                }
                return true;
                
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
                
            default:
                return true;
        }
    }
    
    /**
     * Get all configuration for this company
     * @return array
     */
    public function get_all_config() {
        if (!isset(self::$configcache[$this->companyid])) {
            $this->load_config();
        }
        
        return self::$configcache[$this->companyid];
    }
    
    /**
     * Delete configuration key
     * @param string $key
     * @return bool
     */
    public function delete_config($key) {
        global $DB;
        
        $result = $DB->delete_records('iomadremui_company_config', [
            'companyid' => $this->companyid,
            'configkey' => $key
        ]);
        
        if ($result && isset(self::$configcache[$this->companyid][$key])) {
            unset(self::$configcache[$this->companyid][$key]);
        }
        
        return $result;
    }
    
    /**
     * Clear all configuration for this company
     * @return bool
     */
    public function clear_all_config() {
        global $DB;
        
        $result = $DB->delete_records('iomadremui_company_config', [
            'companyid' => $this->companyid
        ]);
        
        if ($result) {
            self::$configcache[$this->companyid] = [];
            theme_reset_all_caches();
        }
        
        return $result;
    }
    
    /**
     * Get configuration with field defaults
     * @param string $key
     * @return mixed
     */
    public function get_config_with_default($key) {
        $fieldconfig = $this->get_field_config($key);
        $default = $fieldconfig ? ($fieldconfig['default'] ?? null) : null;
        
        return $this->get_config($key, $default);
    }
    
    /**
     * Export configuration as array
     * @return array
     */
    public function export_config() {
        $config = $this->get_all_config();
        $tabs = $this->get_config_tabs();
        $export = [];
        
        foreach ($tabs as $tabname => $tabdata) {
            foreach ($tabdata['fields'] as $fieldkey => $fieldconfig) {
                if (isset($config[$fieldkey])) {
                    $export[$fieldkey] = [
                        'value' => $config[$fieldkey],
                        'type' => $fieldconfig['type'],
                        'tab' => $tabname
                    ];
                }
            }
        }
        
        return $export;
    }
    
    /**
     * Import configuration from array
     * @param array $config
     * @return array Results
     */
    public function import_config($config) {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($config as $key => $data) {
            if (!isset($data['value']) || !isset($data['type'])) {
                $results['failed']++;
                $results['errors'][] = "Invalid data for key: {$key}";
                continue;
            }
            
            if ($this->validate_field_value($key, $data['value'])) {
                try {
                    $this->set_config($key, $data['value'], $data['type']);
                    $results['success']++;
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Failed to set {$key}: " . $e->getMessage();
                }
            } else {
                $results['failed']++;
                $results['errors'][] = "Invalid value for key: {$key}";
            }
        }
        
        return $results;
    }
}