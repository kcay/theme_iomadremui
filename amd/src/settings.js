/**
 * Company settings form functionality
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    
    var Settings = {
        
        init: function() {
            this.initColorPickers();
            this.initFileUploads();
            this.initFormValidation();
            this.initTabSwitching();
        },
        
        initColorPickers: function() {
            $('input[type="color"]').on('change', function() {
                var $this = $(this);
                var color = $this.val();
                var preview = $this.siblings('.color-preview');
                
                if (preview.length) {
                    preview.css('background-color', color);
                }
                
                // Live preview for primary/secondary colors
                if ($this.attr('name') === 'primarycolor') {
                    $('body').css('--primary', color);
                } else if ($this.attr('name') === 'secondarycolor') {
                    $('body').css('--secondary', color);
                }
            });
        },
        
        initFileUploads: function() {
            $('.file-upload-area').on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            
            $('.file-upload-area').on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });
            
            $('.file-upload-area').on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    var input = $(this).find('input[type="file"]')[0];
                    input.files = files;
                    $(input).trigger('change');
                }
            });
        },
        
        initFormValidation: function() {
            $('form').on('submit', function(e) {
                var valid = true;
                
                // Validate required fields
                $(this).find('[required]').each(function() {
                    if (!$(this).val()) {
                        $(this).addClass('is-invalid');
                        valid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                
                // Validate color fields
                $(this).find('input[type="color"]').each(function() {
                    var color = $(this).val();
                    if (color && !/^#[a-fA-F0-9]{6}$/.test(color)) {
                        $(this).addClass('is-invalid');
                        valid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    Notification.alert('Error', 'Please fix the validation errors before submitting.');
                }
            });
        },
        
        initTabSwitching: function() {
            $('.settings-tab').on('click', function(e) {
                e.preventDefault();
                
                var tab = $(this).data('tab');
                var url = new URL(window.location);
                url.searchParams.set('tab', tab);
                
                window.location.href = url.toString();
            });
        }
    };
    
    return Settings;
});
