<?php
/**
 * STEP 1: Create a form extension that hooks into IOMAD's company_edit_form
 * File: classes/form_extension.php
 */

namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

class form_extension {
    
    /**
     * Hook into IOMAD's company_edit_form to add login customization fields
     * This should be called from the form's definition() method
     */
    public static function extend_company_form($mform, $companyid = 0, $isadding = false) {
        global $CFG;
        
        // Add Login Appearance section after the existing Appearance section
        $mform->addElement('header', 'loginappearance', get_string('loginappearance', 'theme_iomadremui'));
        $mform->setExpanded('loginappearance', false);
        
        // Login Background Image
        $backgroundoptions = [
            'subdirs' => 0,
            'maxbytes' => 5242880, // 5MB
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL
        ];
        
        $mform->addElement('filemanager', 'login_background', 
                          get_string('login_background', 'theme_iomadremui'), 
                          null, 
                          $backgroundoptions);
        $mform->addHelpButton('login_background', 'login_background', 'theme_iomadremui');
        
        // Login Tagline/Description
        $editoroptions = [
            'trusttext' => true,
            'subdirs' => false,
            'maxfiles' => 0,
            'context' => \context_system::instance()
        ];
        
        $mform->addElement('editor', 'login_tagline', 
                          get_string('login_tagline', 'theme_iomadremui'), 
                          ['rows' => 4], 
                          $editoroptions);
        $mform->setType('login_tagline', PARAM_RAW);
        $mform->addHelpButton('login_tagline', 'login_tagline', 'theme_iomadremui');
        
        // Welcome Message
        $mform->addElement('editor', 'welcome_message', 
                          get_string('welcome_message', 'theme_iomadremui'), 
                          ['rows' => 3], 
                          $editoroptions);
        $mform->setType('welcome_message', PARAM_RAW);
        $mform->addHelpButton('welcome_message', 'welcome_message', 'theme_iomadremui');
        
        // Login Style
        $login_styles = [
            'default' => get_string('default', 'theme_iomadremui'),
            'centered' => get_string('centered', 'theme_iomadremui'),
            'split' => get_string('split', 'theme_iomadremui')
        ];
        $mform->addElement('select', 'login_style', 
                          get_string('login_style', 'theme_iomadremui'), 
                          $login_styles);
        $mform->setDefault('login_style', 'default');
        $mform->addHelpButton('login_style', 'login_style', 'theme_iomadremui');
        
        // Login Text Color - use IOMAD's color picker
        $mform->addElement('iomad_colourpicker', 'login_text_color', 
                          get_string('signuptextcolor', 'theme_iomadremui'));
        $mform->setDefault('login_text_color', '#ffffff');
        $mform->addHelpButton('login_text_color', 'signuptextcolor', 'theme_iomadremui');
        
        // Login Logo (separate from main logo)
        $logooptions = [
            'subdirs' => 0,
            'maxbytes' => 2097152, // 2MB
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL
        ];
        
        $mform->addElement('filemanager', 'login_logo', 
                          get_string('login_logo', 'theme_iomadremui'), 
                          null, 
                          $logooptions);
        $mform->addHelpButton('login_logo', 'login_logo', 'theme_iomadremui');
    }
    
    /**
     * Load existing login data for the form
     */
    public static function load_login_data($companyid) {
        if (!$companyid) {
            return new \stdClass();
        }
        
        $tenantconfig = new \theme_iomadremui\tenant_config($companyid);
        $context = \context_system::instance();
        
        $data = new \stdClass();
        
        // Load file manager data for background
        $draftitemid = file_get_submitted_draft_itemid('login_background');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'theme_iomadremui',
            'loginbackground_' . $companyid,
            0,
            ['subdirs' => 0, 'maxbytes' => 5242880, 'maxfiles' => 1, 'accepted_types' => ['web_image']]
        );
        $data->login_background = $draftitemid;
        
        // Load file manager data for logo
        $draftitemid = file_get_submitted_draft_itemid('login_logo');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'theme_iomadremui',
            'loginlogo_' . $companyid,
            0,
            ['subdirs' => 0, 'maxbytes' => 2097152, 'maxfiles' => 1, 'accepted_types' => ['web_image']]
        );
        $data->login_logo = $draftitemid;
        
        // Load text settings
        $data->login_tagline = [
            'text' => $tenantconfig->get_config('login_tagline', ''),
            'format' => FORMAT_HTML
        ];
        
        $data->welcome_message = [
            'text' => $tenantconfig->get_config('welcome_message', ''),
            'format' => FORMAT_HTML
        ];
        
        $data->login_style = $tenantconfig->get_config('login_style', 'default');
        $data->login_text_color = $tenantconfig->get_config('login_text_color', '#ffffff');
        
        return $data;
    }
    
    /**
     * Process login data after form submission
     */
    public static function process_login_data($data, $companyid) {
        if (!$companyid) {
            return false;
        }
        
        $tenantconfig = new \theme_iomadremui\tenant_config($companyid);
        $context = \context_system::instance();
        
        // Process login background image
        if (isset($data->login_background)) {
            file_save_draft_area_files(
                $data->login_background,
                $context->id,
                'theme_iomadremui',
                'loginbackground_' . $companyid,
                0,
                ['subdirs' => 0, 'maxbytes' => 5242880, 'maxfiles' => 1, 'accepted_types' => ['web_image']]
            );
        }
        
        // Process login logo
        if (isset($data->login_logo)) {
            file_save_draft_area_files(
                $data->login_logo,
                $context->id,
                'theme_iomadremui',
                'loginlogo_' . $companyid,
                0,
                ['subdirs' => 0, 'maxbytes' => 2097152, 'maxfiles' => 1, 'accepted_types' => ['web_image']]
            );
        }
        
        // Save text settings
        if (isset($data->login_tagline) && is_array($data->login_tagline)) {
            $tenantconfig->set_config('login_tagline', $data->login_tagline['text'], 'editor');
        }
        
        if (isset($data->welcome_message) && is_array($data->welcome_message)) {
            $tenantconfig->set_config('welcome_message', $data->welcome_message['text'], 'editor');
        }
        
        if (isset($data->login_style)) {
            $tenantconfig->set_config('login_style', $data->login_style, 'text');
        }
        
        if (isset($data->login_text_color)) {
            $tenantconfig->set_config('login_text_color', $data->login_text_color, 'text');
        }
        
        // Clear theme cache
        theme_reset_all_caches();
        
        return true;
    }
}
