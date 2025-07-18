<?php
defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes();

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

<div id="page">
    <div id="page-content">
        <?php echo $OUTPUT->main_content(); ?>
    </div>
</div>

<?php echo $OUTPUT->standard_end_of_body_html(); ?>

</body>
</html>