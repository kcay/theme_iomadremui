<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingiomadremui', get_string('configtitle', 'theme_iomadremui'));
    
    // General settings page
    $page = new admin_settingpage('theme_iomadremui_general', get_string('generalsettings', 'theme_iomadremui'));

    // Logo setting
    $name = 'theme_iomadremui/logo';
    $title = get_string('logo', 'theme_iomadremui');
    $description = get_string('logodesc', 'theme_iomadremui');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Favicon setting
    $name = 'theme_iomadremui/favicon';
    $title = get_string('favicon', 'theme_iomadremui');
    $description = get_string('favicondesc', 'theme_iomadremui');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Primary color setting
    $name = 'theme_iomadremui/primarycolor';
    $title = get_string('primarycolor', 'theme_iomadremui');
    $description = get_string('primarycolordesc', 'theme_iomadremui');
    $default = '#007bff';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Secondary color setting
    $name = 'theme_iomadremui/secondarycolor';
    $title = get_string('secondarycolor', 'theme_iomadremui');
    $description = get_string('secondarycolordesc', 'theme_iomadremui');
    $default = '#6c757d';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Font family setting
    $name = 'theme_iomadremui/fontfamily';
    $title = get_string('fontfamily', 'theme_iomadremui');
    $description = get_string('fontfamilydesc', 'theme_iomadremui');
    $default = '"Segoe UI", Roboto, sans-serif';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Custom CSS setting
    $name = 'theme_iomadremui/customcss';
    $title = get_string('customcss', 'theme_iomadremui');
    $description = get_string('customcssdesc', 'theme_iomadremui');
    $setting = new admin_setting_configtextarea($name, $title, $description, '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // Company settings page (for IOMAD administrators)
    // Use correct IOMAD capability check
    if (has_capability('block/iomad_company_admin:company_view', context_system::instance()) || 
        has_capability('moodle/site:config', context_system::instance())) {
        $page = new admin_settingpage('theme_iomadremui_company', get_string('companysettings', 'theme_iomadremui'));
        
        // Note about company-specific settings
        $setting = new admin_setting_description('theme_iomadremui/companynote',
            get_string('companysettings', 'theme_iomadremui'),
            get_string('companysettingsdesc', 'theme_iomadremui'));
        $page->add($setting);
        
        $settings->add($page);
    }
}
