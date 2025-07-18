define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    
    return {
        init: function() {
            $('#company-selector').on('change', function() {
                var companyId = $(this).val();
                if (companyId) {
                    // Use IOMAD's company switching mechanism
                    var url = M.cfg.wwwroot + '/local/iomad/company_user.php?companyid=' + companyId;
                    window.location.href = url;
                }
            });
        }
    };
});