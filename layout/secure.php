<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/local/iomad/lib/company.php');

// Get company context
$companyid = 0;
$companyconfig = null;
if (isloggedin()) {
    $companyid = iomad::get_my_companyid(context_system::instance());
    if ($companyid) {
        $companyconfig = new \theme_iomadremui\tenant_config($companyid);
    }
}

if (isloggedin()) {
    $navdraweropen = (get_user_preferences('drawer-open-nav', 'true') == 'true');
} else {
    $navdraweropen = false;
}

$extraclasses = [];
if ($navdraweropen) {
    $extraclasses[] = 'drawer-open-left';
}
if ($companyid) {
    $extraclasses[] = 'company-' . $companyid;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = strpos($blockshtml, 'data-block=') !== false;
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body <?php echo $bodyattributes; ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div id="page-wrapper" class="d-print-block">

    <?php 
    // ADD: Company selector for secure pages too
    if (method_exists($OUTPUT, 'company_header')) {
        echo $OUTPUT->company_header(); 
    } 
    ?>

    <div id="page" class="container-fluid d-print-block">
        
        <?php echo $OUTPUT->full_header(); ?>
        
        <div id="page-content" class="pb-3 d-print-block">
            <div id="region-main-box">
                
                <?php if (!empty($regionmainsettingsmenu)): ?>
                <div id="region-main-settings-menu" class="d-print-none">
                    <div><?php echo $regionmainsettingsmenu; ?></div>
                </div>
                <?php endif; ?>
                
                <section id="region-main" class="<?php echo $hasblocks ? 'has-blocks' : 'no-blocks'; ?>" aria-label="<?php echo get_string('content'); ?>">
                    <?php echo $OUTPUT->main_content(); ?>
                </section>
                
                <?php if ($hasblocks): ?>
                <section data-region="blocks-column" class="d-print-none" aria-label="<?php echo get_string('blocks'); ?>">
                    <?php echo $blockshtml; ?>
                </section>
                <?php endif; ?>
                
            </div>
        </div>
        
        <?php echo $OUTPUT->standard_footer_html(); ?>
    </div>
    
    <?php echo $OUTPUT->standard_end_of_body_html(); ?>
</div>

</body>
</html>