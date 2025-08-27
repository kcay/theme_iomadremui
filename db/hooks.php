<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\theme_iomadremui\hook_callbacks::class, 'before_footer_html_generation'],
        'priority' => 0,
    ],
];