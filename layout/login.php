<?php
defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes();

// Get company context for login page
$companyid = optional_param('companyid', 0, PARAM_INT);
$companyconfig = null;

if ($companyid) {
    $companyconfig = new \theme_iomadremui\tenant_config($companyid);
} else {
    // Try to get company from URL or other methods
    $companyid = theme_iomadremui_get_login_company_id();
    if ($companyid) {
        $companyconfig = new \theme_iomadremui\tenant_config($companyid);
    }
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'companyid' => $companyid,
];

// Add company-specific login context
if ($companyconfig) {
    $templatecontext['company'] = [
        'id' => $companyid,
        'logo' => $companyconfig->get_config('login_logo') ?: $companyconfig->get_config('logo'),
        'background' => $companyconfig->get_config('login_background'),
        'welcome_message' => $companyconfig->get_config('welcome_message'),
        'login_style' => $companyconfig->get_config('login_style', 'default'),
        'primarycolor' => $companyconfig->get_config('primarycolor', '#007bff'),
    ];
    
    // Add company-specific body class
    $templatecontext['bodyattributes'] .= ' company-' . $companyid . ' login-style-' . $templatecontext['company']['login_style'];
}

/**
 * Helper function to get company ID for login page
 */
function theme_iomadremui_get_login_company_id() {
    global $SESSION;
    
    // Check URL parameter
    $companyid = optional_param('companyid', 0, PARAM_INT);
    if ($companyid) {
        return $companyid;
    }
    
    // Check session
    if (!empty($SESSION->iomad_company_id)) {
        return $SESSION->iomad_company_id;
    }
    
    return 0;
}

// IMPORTANT: Call doctype() and start HTML structure
echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($templatecontext['company'])): ?>
    <style>
    .login-company-<?php echo $templatecontext['company']['id']; ?> {
        <?php if (!empty($templatecontext['company']['background'])): ?>
        background-image: url('<?php echo $templatecontext['company']['background']; ?>');
        background-size: cover;
        background-position: center;
        <?php endif; ?>
    }

    .login-company-<?php echo $templatecontext['company']['id']; ?> .login-container {
        --primary-color: <?php echo $templatecontext['company']['primarycolor']; ?>;
    }

    <?php if ($templatecontext['company']['login_style'] === 'centered'): ?>
    .login-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }
    <?php elseif ($templatecontext['company']['login_style'] === 'split'): ?>
    .login-wrapper {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: 100vh;
    }
    .login-content {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-image {
        background: var(--primary-color, #007bff);
        <?php if (!empty($templatecontext['company']['background'])): ?>
        background-image: url('<?php echo $templatecontext['company']['background']; ?>');
        background-size: cover;
        background-position: center;
        <?php endif; ?>
    }
    <?php endif; ?>
    </style>
    <?php endif; ?>
</head>

<body <?php echo $templatecontext['bodyattributes']; ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div id="page-wrapper" class="login-company-<?php echo $templatecontext['company']['id'] ?? 0; ?>">
    
    <?php if (!empty($templatecontext['company']) && $templatecontext['company']['login_style'] === 'split'): ?>
    <div class="login-wrapper">
        <div class="login-image"></div>
        <div class="login-content">
    <?php endif; ?>
    
    <div class="login-container">
        <?php if (!empty($templatecontext['company']['logo'])): ?>
        <div class="login-logo text-center mb-4">
            <img src="<?php echo $templatecontext['company']['logo']; ?>" alt="<?php echo $templatecontext['sitename']; ?>" class="img-fluid" style="max-height: 80px;">
        </div>
        <?php endif; ?>
        
        <?php if (!empty($templatecontext['company']['welcome_message'])): ?>
        <div class="welcome-message mb-4">
            <?php echo $templatecontext['company']['welcome_message']; ?>
        </div>
        <?php endif; ?>
        
        <?php echo $OUTPUT->main_content(); ?>
    </div>
    
    <?php if (!empty($templatecontext['company']) && $templatecontext['company']['login_style'] === 'split'): ?>
        </div>
    </div>
    <?php endif; ?>

    <?php echo $OUTPUT->standard_end_of_body_html(); ?>
</div>

</body>
</html>