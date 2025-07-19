<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_theme_iomadremui_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025071400) {
        // Define table iomadremui_company_config to be created.
        $table = new xmldb_table('iomadremui_company_config');

        // Adding fields to table iomadremui_company_config.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('configkey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('configvalue', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('configtype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'text');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table iomadremui_company_config.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('companyid', XMLDB_KEY_FOREIGN, ['companyid'], 'company', ['id']);

        // Adding indexes to table iomadremui_company_config.
        $table->add_index('companyid_configkey', XMLDB_INDEX_UNIQUE, ['companyid', 'configkey']);

        // Conditionally launch create table for iomadremui_company_config.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table iomadremui_presets to be created.
        $table = new xmldb_table('iomadremui_presets');

        // Adding fields to table iomadremui_presets.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('configdata', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('isdefault', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table iomadremui_presets.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table iomadremui_presets.
        $table->add_index('name', XMLDB_INDEX_UNIQUE, ['name']);

        // Conditionally launch create table for iomadremui_presets.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Iomadremui savepoint reached.
        upgrade_plugin_savepoint(true, 2025071400, 'theme', 'iomadremui');
    }

    return true;
}