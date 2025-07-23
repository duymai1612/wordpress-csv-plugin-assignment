/**
 * Frontend JavaScript for CSV-generated pages
 * 
 * Handles interactive features like metadata toggle functionality
 * with accessibility support and smooth animations.
 */

(function($) {
    'use strict';

    /**
     * CSV Page Frontend functionality
     */
    const CSVPageFrontend = {
        
        /**
         * Initialize frontend functionality
         */
        init: function() {
            this.bindEvents();
            this.setupAccessibility();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Metadata toggle functionality
            $(document).on('click', '.csv-metadata-toggle', this.toggleMetadata.bind(this));
            
            // Keyboard navigation support
            $(document).on('keydown', '.csv-metadata-toggle', this.handleKeyboardToggle.bind(this));
        },

        /**
         * Setup accessibility features
         */
        setupAccessibility: function() {
            // Add ARIA labels and descriptions
            $('.csv-metadata-toggle').each(function() {
                const $toggle = $(this);
                const $content = $toggle.siblings('.csv-metadata-content');
                
                if ($content.length) {
                    const contentId = 'csv-metadata-' + Math.random().toString(36).substr(2, 9);
                    $content.attr('id', contentId);
                    $toggle.attr('aria-controls', contentId);
                    $toggle.attr('aria-describedby', contentId);
                }
            });
        },

        /**
         * Toggle metadata section visibility
         */
        toggleMetadata: function(event) {
            event.preventDefault();
            
            const $toggle = $(event.currentTarget);
            const $content = $toggle.siblings('.csv-metadata-content');
            const isExpanded = $toggle.attr('aria-expanded') === 'true';
            
            if (isExpanded) {
                this.hideMetadata($toggle, $content);
            } else {
                this.showMetadata($toggle, $content);
            }
        },

        /**
         * Show metadata content
         */
        showMetadata: function($toggle, $content) {
            $toggle.attr('aria-expanded', 'true');
            $toggle.find('.toggle-text').text('Hide');
            
            // Smooth slide down animation
            $content.slideDown(300, function() {
                // Focus management for accessibility
                $content.find('h3').focus();
            });
            
            // Store user preference
            this.setUserPreference('metadata-visible', true);
        },

        /**
         * Hide metadata content
         */
        hideMetadata: function($toggle, $content) {
            $toggle.attr('aria-expanded', 'false');
            $toggle.find('.toggle-text').text('Show');
            
            // Smooth slide up animation
            $content.slideUp(300);
            
            // Store user preference
            this.setUserPreference('metadata-visible', false);
        },

        /**
         * Handle keyboard navigation for toggle
         */
        handleKeyboardToggle: function(event) {
            // Enter or Space key
            if (event.keyCode === 13 || event.keyCode === 32) {
                event.preventDefault();
                $(event.currentTarget).click();
            }
        },

        /**
         * Set user preference in localStorage
         */
        setUserPreference: function(key, value) {
            if (typeof Storage !== 'undefined') {
                try {
                    localStorage.setItem('csv-page-' + key, JSON.stringify(value));
                } catch (e) {
                    // localStorage not available or quota exceeded
                    console.log('Could not save user preference:', e);
                }
            }
        },

        /**
         * Get user preference from localStorage
         */
        getUserPreference: function(key, defaultValue) {
            if (typeof Storage !== 'undefined') {
                try {
                    const stored = localStorage.getItem('csv-page-' + key);
                    return stored !== null ? JSON.parse(stored) : defaultValue;
                } catch (e) {
                    // localStorage not available or invalid JSON
                    return defaultValue;
                }
            }
            return defaultValue;
        },

        /**
         * Restore user preferences on page load
         */
        restoreUserPreferences: function() {
            const metadataVisible = this.getUserPreference('metadata-visible', false);
            
            if (metadataVisible) {
                $('.csv-metadata-toggle').each(function() {
                    const $toggle = $(this);
                    const $content = $toggle.siblings('.csv-metadata-content');
                    
                    $toggle.attr('aria-expanded', 'true');
                    $toggle.find('.toggle-text').text('Hide');
                    $content.show();
                });
            }
        },

        /**
         * Smooth scroll to metadata section if hash is present
         */
        handleHashNavigation: function() {
            if (window.location.hash === '#csv-metadata') {
                const $metadataSection = $('.csv-metadata-section');
                if ($metadataSection.length) {
                    // Open metadata if closed
                    const $toggle = $metadataSection.find('.csv-metadata-toggle');
                    const $content = $metadataSection.find('.csv-metadata-content');
                    
                    if ($toggle.attr('aria-expanded') !== 'true') {
                        this.showMetadata($toggle, $content);
                    }
                    
                    // Smooth scroll to section
                    $('html, body').animate({
                        scrollTop: $metadataSection.offset().top - 50
                    }, 500);
                }
            }
        },

        /**
         * Add print-friendly functionality
         */
        setupPrintSupport: function() {
            // Show all metadata when printing
            window.addEventListener('beforeprint', function() {
                $('.csv-metadata-content').show();
            });
            
            // Restore original state after printing
            window.addEventListener('afterprint', function() {
                $('.csv-metadata-toggle').each(function() {
                    const $toggle = $(this);
                    const $content = $toggle.siblings('.csv-metadata-content');
                    
                    if ($toggle.attr('aria-expanded') !== 'true') {
                        $content.hide();
                    }
                });
            });
        }
    };

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        CSVPageFrontend.init();
        CSVPageFrontend.restoreUserPreferences();
        CSVPageFrontend.handleHashNavigation();
        CSVPageFrontend.setupPrintSupport();
    });

    /**
     * Handle hash changes for deep linking
     */
    $(window).on('hashchange', function() {
        CSVPageFrontend.handleHashNavigation();
    });

})(jQuery);
