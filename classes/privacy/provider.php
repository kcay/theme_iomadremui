<?php
namespace theme_iomadremui\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for theme_iomadremui.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\subsystem\provider,
    \core_privacy\local\request\user_preference_provider {

    /*
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
  
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            'iomadremui_company_config',
            [
                'companyid' => 'privacy:metadata:iomadremui_company_config:companyid',
                'configkey' => 'privacy:metadata:iomadremui_company_config:configkey',
                'configvalue' => 'privacy:metadata:iomadremui_company_config:configvalue',
                'timecreated' => 'privacy:metadata:iomadremui_company_config:timecreated',
                'timemodified' => 'privacy:metadata:iomadremui_company_config:timemodified',
            ],
            'privacy:metadata:iomadremui_company_config'
        );

        $items->add_user_preference('theme_iomadremui_company', 'privacy:metadata:preference:company');

        return $items;
    }

    /*
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Export personal data for the given approved_contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // No user data is stored in contexts.
    }

    /*
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // No user data is stored in contexts.
    }

    /*
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // No user data is stored in contexts.
    }

    /*
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        // No user data is stored in contexts.
    }

    /*
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // No user data is stored in contexts.
    }

    /*
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $preference = get_user_preferences('theme_iomadremui_company', null, $userid);
        if ($preference !== null) {
            $desc = get_string('privacy:metadata:preference:company', 'theme_iomadremui');
            writer::export_user_preference('theme_iomadremui', 'company', $preference, $desc);
        }
    }
}