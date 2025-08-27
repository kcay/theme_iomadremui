<?php
namespace theme_iomadremui;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for IOMAD company form integration
 */
class observers {
    
    /**
     * Observe company form display to add our fields
     * This would be triggered by a custom event or hook
     */
    public static function company_form_display($event) {
        // This would be called when IOMAD's company form is being built
        // Implementation depends on how IOMAD allows form extensions
    }
    
    /**
     * Observe company form submission to process our data
     */
    public static function company_form_submit($event) {
        $data = $event->get_data();
        if (isset($data['companyid'])) {
            \theme_iomadremui\form\company_edit_form_extension::process_login_data(
                $data, 
                $data['companyid']
            );
        }
    }
}
