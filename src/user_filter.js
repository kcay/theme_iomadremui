/**
 * User filtering functionality
 */
define(['jquery'], function($) {
    
    var UserFilter = {
        
        init: function() {
            $('#company-filter-select').on('change', this.filterUsers);
        },
        
        filterUsers: function() {
            var companyId = $(this).val();
            var $userList = $('.user-listing, .participants');
            
            if (companyId === '') {
                // Show all users
                $userList.find('.user-item, .participant').show();
            } else {
                // Hide all users first
                $userList.find('.user-item, .participant').hide();
                
                // Show users from selected company
                $userList.find('[data-company="' + companyId + '"]').show();
            }
        }
    };
    
    return UserFilter;
});
