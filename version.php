<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2025071402;        // YYYYMMDDHH (year, month, day, 24-hr time)
$plugin->requires  = 2022041900;        // Moodle 4.0 minimum
$plugin->component = 'theme_iomadremui'; // Full name of the plugin (used for diagnostics)
$plugin->dependencies = [
    'local_iomad' => ANY_VERSION,
];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.2';