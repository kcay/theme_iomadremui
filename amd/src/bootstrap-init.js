/**
 * Bootstrap initialization for IOMAD RemUI theme
 * Ensures Bootstrap JavaScript components work properly
 */
define(['jquery'], function($) {
    
    return {
        init: function() {
            // Ensure Bootstrap is available
            if (typeof window.bootstrap === 'undefined') {
                // If Bootstrap isn't loaded globally, try to initialize it
                console.warn('Bootstrap JS not found globally. Attempting to initialize...');
                
                // Try to load Bootstrap from CDN as fallback
                if (typeof $.fn.modal === 'undefined') {
                    var script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js';
                    script.onload = function() {
                        console.log('Bootstrap loaded from CDN');
                        initializeBootstrapComponents();
                    };
                    document.head.appendChild(script);
                } else {
                    initializeBootstrapComponents();
                }
            } else {
                initializeBootstrapComponents();
            }
            
            function initializeBootstrapComponents() {
                // Initialize Bootstrap components that might not auto-initialize
                
                // Reinitialize tooltips
                $('[data-toggle="tooltip"]').each(function() {
                    if (window.bootstrap && window.bootstrap.Tooltip) {
                        new window.bootstrap.Tooltip(this);
                    } else if ($.fn.tooltip) {
                        $(this).tooltip();
                    }
                });
                
                // Reinitialize popovers
                $('[data-toggle="popover"]').each(function() {
                    if (window.bootstrap && window.bootstrap.Popover) {
                        new window.bootstrap.Popover(this);
                    } else if ($.fn.popover) {
                        $(this).popover();
                    }
                });
                
                // Reinitialize dropdowns
                $('[data-toggle="dropdown"]').each(function() {
                    if (window.bootstrap && window.bootstrap.Dropdown) {
                        new window.bootstrap.Dropdown(this);
                    }
                });
                
                // Reinitialize modals
                $('[data-toggle="modal"]').each(function() {
                    if (window.bootstrap && window.bootstrap.Modal) {
                        new window.bootstrap.Modal($(this).attr('data-target') || $(this).attr('href'));
                    }
                });
                
                // Reinitialize collapse/accordion
                $('[data-toggle="collapse"]').each(function() {
                    if (window.bootstrap && window.bootstrap.Collapse) {
                        var target = $(this).attr('data-target') || $(this).attr('href');
                        if (target) {
                            new window.bootstrap.Collapse(document.querySelector(target));
                        }
                    }
                });
                
                // Handle toggle buttons specifically
                initializeToggleButtons();
            }
            
            function initializeToggleButtons() {
                // Bootstrap toggle buttons (radio/checkbox groups)
                $('[data-toggle="buttons"]').each(function() {
                    var $group = $(this);
                    
                    $group.find('input[type="radio"], input[type="checkbox"]').on('change', function() {
                        var $input = $(this);
                        var $label = $input.closest('label');
                        var $group = $label.closest('[data-toggle="buttons"]');
                        
                        if ($input.attr('type') === 'radio') {
                            // Radio buttons - remove active from siblings
                            $group.find('label').removeClass('active');
                            if ($input.is(':checked')) {
                                $label.addClass('active');
                            }
                        } else if ($input.attr('type') === 'checkbox') {
                            // Checkboxes - toggle individual active state
                            if ($input.is(':checked')) {
                                $label.addClass('active');
                            } else {
                                $label.removeClass('active');
                            }
                        }
                    });
                    
                    // Initialize current state
                    $group.find('input:checked').closest('label').addClass('active');
                });
                
                // Handle button toggle functionality
                $('[data-toggle="button"]').on('click', function() {
                    var $btn = $(this);
                    
                    if ($btn.hasClass('active')) {
                        $btn.removeClass('active');
                        $btn.attr('aria-pressed', 'false');
                    } else {
                        $btn.addClass('active');
                        $btn.attr('aria-pressed', 'true');
                    }
                });
            }
            
            // Re-run initialization when new content is loaded (AJAX, etc.)
            $(document).on('DOMNodeInserted', function(e) {
                if ($(e.target).find('[data-toggle]').length > 0) {
                    setTimeout(initializeBootstrapComponents, 100);
                }
            });
            
            // MutationObserver for modern browsers
            if (window.MutationObserver) {
                var observer = new MutationObserver(function(mutations) {
                    var shouldReinit = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                if (node.nodeType === 1) { // Element node
                                    if (node.hasAttribute && node.hasAttribute('data-toggle') || 
                                        (node.querySelector && node.querySelector('[data-toggle]'))) {
                                        shouldReinit = true;
                                        break;
                                    }
                                }
                            }
                        }
                    });
                    
                    if (shouldReinit) {
                        setTimeout(initializeBootstrapComponents, 100);
                    }
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        }
    };
});