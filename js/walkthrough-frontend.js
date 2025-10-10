/**
 * Frontend JavaScript for AVLP Walkthrough Tour plugin
 * Lightweight tour engine following VLP standards
 */

(function($) {
    'use strict';

    // VLP Walkthrough Tour Engine
    window.VLPWalkthrough = {
        currentTour: null,
        currentStep: 0,
        isActive: false,
        container: null,
        overlay: null,
        tooltip: null,
        highlightedElement: null,
        settings: {
            animationSpeed: 300,
            showProgress: true,
            allowSkip: true,
            allowDisable: true
        },

        /**
         * Initialize the tour engine
         */
        init: function() {
            this.container = document.getElementById('vlp-walkthrough-container');
            this.overlay = document.getElementById('vlp-walkthrough-overlay');
            this.tooltip = document.getElementById('vlp-walkthrough-tooltip');
            
            if (!this.container || !this.overlay || !this.tooltip) {
                console.error('VLP Walkthrough: Required DOM elements not found');
                return;
            }

            this.bindEvents();
            this.loadSettings();
            
            // Auto-start tours from window data
            if (window.VLPWalkthroughData && window.VLPWalkthroughData.length > 0) {
                setTimeout(() => {
                    this.startTour(window.VLPWalkthroughData[0]);
                }, 500);
            } else {
                // Auto-detect tours for this page
                this.autoDetectTours();
            }
        },

        /**
         * Load settings from localized script
         */
        loadSettings: function() {
            if (typeof vlp_walkthrough_frontend !== 'undefined') {
                this.settings.animationSpeed = vlp_walkthrough_frontend.animation_speed || 300;
                this.settings.showProgress = vlp_walkthrough_frontend.show_progress !== false;
                this.settings.allowSkip = vlp_walkthrough_frontend.allow_skip !== false;
                this.settings.allowDisable = vlp_walkthrough_frontend.allow_disable !== false;
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Close button
            $(this.tooltip).on('click', '.vlp-walkthrough-close', this.closeTour.bind(this));
            
            // Overlay click
            $(this.overlay).on('click', this.closeTour.bind(this));
            
            // Navigation buttons
            $(this.tooltip).on('click', '.vlp-walkthrough-next', this.nextStep.bind(this));
            $(this.tooltip).on('click', '.vlp-walkthrough-prev', this.prevStep.bind(this));
            
            // Skip buttons
            $(this.tooltip).on('click', '.vlp-walkthrough-skip', this.skipTour.bind(this));
            $(this.tooltip).on('click', '.vlp-walkthrough-disable', this.disableTours.bind(this));
            
            // Tour trigger buttons
            $(document).on('click', '.vlp-walkthrough-trigger', this.handleTriggerClick.bind(this));
            
            // Keyboard navigation
            $(document).on('keydown', this.handleKeyboard.bind(this));
            
            // Window resize
            $(window).on('resize', this.repositionTooltip.bind(this));
        },

        /**
         * Start a tour
         */
        startTour: function(tourData) {
            console.log('VLP Walkthrough: startTour called with data:', tourData);
            
            if (this.isActive) {
                console.log('VLP Walkthrough: Tour already active, closing current tour');
                this.closeTour();
            }

            this.currentTour = tourData;
            this.currentStep = 0;
            this.isActive = true;
            
            console.log('VLP Walkthrough: Tour initialized, current step:', this.currentStep);

            // Track tour start
            this.trackInteraction('in_progress', 0);

            // Show the first step
            console.log('VLP Walkthrough: Showing step 0');
            this.showStep(0);
        },

        /**
         * Show a specific step
         */
        showStep: function(stepIndex) {
            console.log('VLP Walkthrough: showStep called with index:', stepIndex);
            console.log('VLP Walkthrough: currentTour:', this.currentTour);
            console.log('VLP Walkthrough: steps length:', this.currentTour ? this.currentTour.steps.length : 'no tour');
            
            if (!this.currentTour || !this.currentTour.steps || stepIndex >= this.currentTour.steps.length) {
                console.log('VLP Walkthrough: Invalid step conditions, closing tour');
                this.closeTour();
                return;
            }

            const step = this.currentTour.steps[stepIndex];
            this.currentStep = stepIndex;

            // Update tooltip content
            console.log('VLP Walkthrough: Updating tooltip content');
            this.updateTooltipContent(step);
            
            // Show tooltip
            console.log('VLP Walkthrough: Showing tooltip');
            this.showTooltip();
            
            // Highlight target element (skip for modal positioning)
            console.log('VLP Walkthrough: Highlighting element');
            if (step.step_position !== 'modal') {
                this.highlightElement(step.target_selector);
            } else {
                console.log('VLP Walkthrough: Modal positioning - skipping element highlight');
            }
            
            // Position tooltip
            console.log('VLP Walkthrough: Positioning tooltip');
            this.positionTooltip(step);
            
            // Update progress
            console.log('VLP Walkthrough: Updating progress');
            this.updateProgress();
            
            // Update button text
            this.updateButtonText();
            
            // Track step view
            console.log('VLP Walkthrough: Tracking interaction');
            this.trackInteraction('in_progress', stepIndex);
        },

        /**
         * Update tooltip content
         */
        updateTooltipContent: function(step) {
            const title = $(this.tooltip).find('.vlp-walkthrough-title');
            const body = $(this.tooltip).find('.vlp-walkthrough-body');
            
            title.text(step.step_title);
            body.html(step.step_content);
        },

        /**
         * Show tooltip with animation
         */
        showTooltip: function() {
            console.log('VLP Walkthrough: showTooltip - container:', this.container);
            console.log('VLP Walkthrough: showTooltip - tooltip:', this.tooltip);
            console.log('VLP Walkthrough: showTooltip - animation speed:', this.settings.animationSpeed);
            
            $(this.container).fadeIn(this.settings.animationSpeed);
            $(this.tooltip).addClass('vlp-walkthrough-fade-in');
            
            console.log('VLP Walkthrough: showTooltip - fade in complete');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                $(this.tooltip).removeClass('vlp-walkthrough-fade-in');
                console.log('VLP Walkthrough: showTooltip - animation class removed');
                
                // Debug: Check if tooltip is still visible
                setTimeout(() => {
                    console.log('VLP Walkthrough: Tooltip visibility check:');
                    console.log('VLP Walkthrough: Container display:', $(this.container).css('display'));
                    console.log('VLP Walkthrough: Container visibility:', $(this.container).css('visibility'));
                    console.log('VLP Walkthrough: Tooltip position:', $(this.tooltip).position());
                    console.log('VLP Walkthrough: Tooltip offset:', $(this.tooltip).offset());
                    console.log('VLP Walkthrough: Tooltip dimensions:', {
                        width: $(this.tooltip).outerWidth(),
                        height: $(this.tooltip).outerHeight()
                    });
                }, 100);
            }, this.settings.animationSpeed);
        },

        /**
         * Hide tooltip with animation
         */
        hideTooltip: function() {
            console.log('VLP Walkthrough: hideTooltip called - Stack trace:', new Error().stack);
            $(this.tooltip).addClass('vlp-walkthrough-fade-out');
            
            setTimeout(() => {
                $(this.container).fadeOut(this.settings.animationSpeed);
                $(this.tooltip).removeClass('vlp-walkthrough-fade-out');
            }, this.settings.animationSpeed);
        },

        /**
         * Highlight target element
         */
        highlightElement: function(selector) {
            console.log('VLP Walkthrough: highlightElement called with selector:', selector);
            
            // Remove previous highlight
            if (this.highlightedElement) {
                $(this.highlightedElement).removeClass('vlp-walkthrough-highlight');
            }

            // Find and highlight new element
            const element = $(selector);
            console.log('VLP Walkthrough: Found elements:', element.length);
            
            if (element.length > 0) {
                console.log('VLP Walkthrough: Highlighting element:', element[0]);
                this.highlightedElement = element[0];
                $(element).addClass('vlp-walkthrough-highlight');
                
                // Scroll element into view if needed
                this.scrollToElement(element);
            } else {
                console.warn('VLP Walkthrough: Target element not found:', selector);
                console.warn('VLP Walkthrough: Available elements with similar IDs:', $('[id*="vlp"]').length);
                this.highlightedElement = null;
            }
        },

        /**
         * Scroll element into view
         */
        scrollToElement: function(element) {
            const elementTop = element.offset().top;
            const elementBottom = elementTop + element.outerHeight();
            const windowTop = $(window).scrollTop();
            const windowBottom = windowTop + $(window).height();
            
            // Check if element is already visible
            if (elementTop >= windowTop && elementBottom <= windowBottom) {
                return;
            }
            
            // Calculate optimal scroll position
            const scrollTop = elementTop - ($(window).height() / 2) + (element.outerHeight() / 2);
            
            $('html, body').animate({
                scrollTop: Math.max(0, scrollTop)
            }, this.settings.animationSpeed);
        },

        /**
         * Position tooltip relative to target element
         */
        positionTooltip: function(step) {
            let position = step.step_position || 'auto';
            
            // Handle modal positioning (center of page, no element highlighting)
            if (position === 'modal') {
                this.centerTooltip();
                this.hideArrow();
                $(this.tooltip).addClass('modal');
                // Show full overlay for modal steps
                $('.vlp-walkthrough-overlay').css('background', 'rgba(0, 0, 0, 0.55)');
                return;
            }
            
            if (!this.highlightedElement) {
                // Center tooltip if no target element
                this.centerTooltip();
                // Show full overlay when no element is highlighted
                $('.vlp-walkthrough-overlay').css('background', 'rgba(0, 0, 0, 0.55)');
                return;
            }

            // Hide overlay background for spotlight effect (element's box-shadow handles it)
            $('.vlp-walkthrough-overlay').css('background', 'transparent');

            const element = $(this.highlightedElement);
            const elementRect = element[0].getBoundingClientRect();
            const tooltipRect = this.tooltip.getBoundingClientRect();
            const windowWidth = $(window).width();
            const windowHeight = $(window).height();
            
            if (position === 'auto') {
                position = this.calculateBestPosition(elementRect, tooltipRect, windowWidth, windowHeight);
            }
            
            this.setTooltipPosition(elementRect, tooltipRect, position);
            this.setArrowPosition(position);
            
            // Remove modal class for non-modal positioning
            $(this.tooltip).removeClass('modal');
        },

        /**
         * Calculate best position for tooltip
         */
        calculateBestPosition: function(elementRect, tooltipRect, windowWidth, windowHeight) {
            const spaceAbove = elementRect.top;
            const spaceBelow = windowHeight - elementRect.bottom;
            const spaceLeft = elementRect.left;
            const spaceRight = windowWidth - elementRect.right;
            
            const tooltipHeight = tooltipRect.height;
            const tooltipWidth = tooltipRect.width;
            
            // Check if tooltip fits above or below
            if (spaceAbove >= tooltipHeight + 20) {
                return 'top';
            } else if (spaceBelow >= tooltipHeight + 20) {
                return 'bottom';
            } else if (spaceLeft >= tooltipWidth + 20) {
                return 'left';
            } else if (spaceRight >= tooltipWidth + 20) {
                return 'right';
            }
            
            // Default to bottom if no good position found
            return 'bottom';
        },

        /**
         * Set tooltip position
         */
        setTooltipPosition: function(elementRect, tooltipRect, position) {
            const offset = 20;
            let left, top;
            
            switch (position) {
                case 'top':
                    left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
                    top = elementRect.top - tooltipRect.height - offset;
                    break;
                case 'bottom':
                    left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
                    top = elementRect.bottom + offset;
                    break;
                case 'left':
                    left = elementRect.left - tooltipRect.width - offset;
                    top = elementRect.top + (elementRect.height / 2) - (tooltipRect.height / 2);
                    break;
                case 'right':
                    left = elementRect.right + offset;
                    top = elementRect.top + (elementRect.height / 2) - (tooltipRect.height / 2);
                    break;
            }
            
            // Keep tooltip within viewport
            left = Math.max(10, Math.min(left, $(window).width() - tooltipRect.width - 10));
            top = Math.max(10, Math.min(top, $(window).height() - tooltipRect.height - 10));
            
            $(this.tooltip).css({
                left: left + 'px',
                top: top + 'px'
            });
        },

        /**
         * Set arrow position
         */
        setArrowPosition: function(position) {
            const arrow = $(this.tooltip).find('.vlp-walkthrough-arrow');
            arrow.removeClass('top bottom left right');
            arrow.addClass(position);
        },

        /**
         * Hide arrow when centered
         */
        hideArrow: function() {
            $(this.tooltip).find('.vlp-walkthrough-arrow').removeClass('top bottom left right').hide();
        },

        /**
         * Center tooltip in viewport
         */
        centerTooltip: function() {
            const windowWidth = $(window).width();
            const windowHeight = $(window).height();
            const tooltipRect = this.tooltip.getBoundingClientRect();
            
            const left = (windowWidth - tooltipRect.width) / 2;
            const top = (windowHeight - tooltipRect.height) / 2;
            
            $(this.tooltip).css({
                left: left + 'px',
                top: top + 'px'
            });
            
            // Hide arrow when centered
            this.hideArrow();
        },

        /**
         * Update progress indicator
         */
        updateProgress: function() {
            if (!this.currentTour || !this.currentTour.show_progress) {
                $(this.tooltip).find('.vlp-walkthrough-progress').hide();
                return;
            }
            
            const totalSteps = this.currentTour.steps.length;
            const currentStep = this.currentStep + 1;
            const percentage = (currentStep / totalSteps) * 100;
            
            $(this.tooltip).find('.vlp-walkthrough-progress-fill').css('width', percentage + '%');
            $(this.tooltip).find('.vlp-walkthrough-progress-text').text(
                currentStep + '/' + totalSteps
            );
            
            $(this.tooltip).find('.vlp-walkthrough-progress').show();
        },

        /**
         * Update button text based on current step
         */
        updateButtonText: function() {
            if (!this.currentTour) return;
            
            const totalSteps = this.currentTour.steps.length;
            const isLastStep = this.currentStep === totalSteps - 1;
            
            const nextButton = $(this.tooltip).find('.vlp-walkthrough-next');
            if (isLastStep) {
                nextButton.text('Done');
            } else {
                nextButton.text('Next');
            }
        },

        /**
         * Go to next step
         */
        nextStep: function() {
            if (!this.currentTour || this.currentStep >= this.currentTour.steps.length - 1) {
                this.completeTour();
                return;
            }
            
            this.showStep(this.currentStep + 1);
        },

        /**
         * Go to previous step
         */
        prevStep: function() {
            if (this.currentStep > 0) {
                this.showStep(this.currentStep - 1);
            }
        },

        /**
         * Complete the tour
         */
        completeTour: function() {
            this.trackInteraction('completed', this.currentStep);
            this.closeTour();
            
            // Show completion message if available
            if (typeof vlp_walkthrough_frontend !== 'undefined' && vlp_walkthrough_frontend.strings.completed) {
                this.showMessage(vlp_walkthrough_frontend.strings.completed);
            }
        },

        /**
         * Skip the tour
         */
        skipTour: function() {
            this.trackInteraction('skipped_session', this.currentStep);
            this.closeTour();
        },

        /**
         * Disable tours permanently
         */
        disableTours: function() {
            console.log('VLP Walkthrough: Disabling tours permanently');
            this.trackInteraction('skipped_permanent', this.currentStep);
            this.closeTour();
        },

        /**
         * Close the tour
         */
        closeTour: function() {
            if (!this.isActive) {
                return;
            }
            
            // Remove highlight
            if (this.highlightedElement) {
                $(this.highlightedElement).removeClass('vlp-walkthrough-highlight');
                this.highlightedElement = null;
            }
            
            // Hide tooltip
            this.hideTooltip();
            
            // Reset state
            this.currentTour = null;
            this.currentStep = 0;
            this.isActive = false;
        },

        /**
         * Handle trigger button clicks
         */
        handleTriggerClick: function(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const tourId = parseInt(button.data('tour-id'));
            
            if (!tourId) {
                console.error('VLP Walkthrough: Tour ID not found on trigger button');
                return;
            }
            
            // Find tour data
            let tourData = null;
            if (window.VLPWalkthroughData) {
                tourData = window.VLPWalkthroughData.find(tour => tour.tour_id === tourId);
            }
            
            if (!tourData) {
                console.error('VLP Walkthrough: Tour data not found for ID:', tourId);
                return;
            }
            
            this.startTour(tourData);
        },

        /**
         * Handle keyboard navigation
         */
        handleKeyboard: function(e) {
            if (!this.isActive) {
                return;
            }
            
            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    this.closeTour();
                    break;
                case 'ArrowRight':
                case 'Enter':
                    e.preventDefault();
                    this.nextStep();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.prevStep();
                    break;
            }
        },

        /**
         * Reposition tooltip on window resize
         */
        repositionTooltip: function() {
            if (!this.isActive || !this.currentTour) {
                return;
            }
            
            const step = this.currentTour.steps[this.currentStep];
            if (step) {
                this.highlightElement(step.target_selector);
                this.positionTooltip(step);
            }
        },

        /**
         * Track user interactions
         */
        trackInteraction: function(action, stepCompleted) {
            if (!this.currentTour || typeof vlpWalkthroughTour === 'undefined') {
                console.log('VLP Walkthrough: Cannot track interaction - missing tour or frontend data');
                return;
            }
            
            const data = {
                action: 'vlp_walkthrough_track_interaction',
                nonce: vlpWalkthroughTour.nonce,
                tour_id: this.currentTour.tour_id,
                action_type: action,
                page_url: window.location.pathname,
                step_completed: stepCompleted
            };
            
            console.log('VLP Walkthrough: Tracking interaction:', data);
            
            $.post(vlpWalkthroughTour.ajax_url, data, function(response) {
                console.log('VLP Walkthrough: Track interaction response:', response);
                if (!response.success) {
                    console.error('VLP Walkthrough: Failed to track interaction:', response.data);
                }
            }).fail(function(xhr, status, error) {
                console.error('VLP Walkthrough: AJAX request failed:', status, error);
            });
        },

        /**
         * Show temporary message
         */
        showMessage: function(message) {
            const messageEl = $('<div class="vlp-walkthrough-message">' + message + '</div>');
            $('body').append(messageEl);
            
            messageEl.fadeIn(300);
            
            setTimeout(() => {
                messageEl.fadeOut(300, function() {
                    messageEl.remove();
                });
            }, 3000);
        },

        /**
         * Auto-detect tours for the current page
         */
        autoDetectTours: function() {
            if (typeof vlpWalkthroughTour === 'undefined') {
                console.warn('VLP Walkthrough: Frontend data not available');
                return;
            }

            const currentUrl = window.location.pathname + window.location.search;
            console.log('VLP Walkthrough: Auto-detecting tours for:', currentUrl);
            
            $.ajax({
                url: vlpWalkthroughTour.ajax_url,
                type: 'POST',
                data: {
                    action: 'vlp_walkthrough_get_page_tours',
                    nonce: vlpWalkthroughTour.nonce,
                    current_url: currentUrl
                },
                success: (response) => {
                    console.log('VLP Walkthrough: AJAX response:', response);
                    if (response.success && response.data.tours && response.data.tours.length > 0) {
                        console.log('VLP Walkthrough: Found tours:', response.data.tours);
                        // Find tours that should auto-start
                        const autoTours = response.data.tours.filter(tour => {
                            return tour.user_tracking.status === 'not_started' || 
                                   tour.user_tracking.status === 'in_progress';
                        });
                        
                        console.log('VLP Walkthrough: Auto-startable tours:', autoTours);
                        if (autoTours.length > 0) {
                            console.log('VLP Walkthrough: Starting tour:', autoTours[0]);
                            // Start the first available tour
                            setTimeout(() => {
                                this.startTour(autoTours[0]);
                            }, 1000); // Small delay to ensure page is fully loaded
                        }
                    } else {
                        console.log('VLP Walkthrough: No tours found for this page');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('VLP Walkthrough: Failed to fetch tours:', error);
                    console.error('VLP Walkthrough: Response:', xhr.responseText);
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        VLPWalkthrough.init();
    });

    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && VLPWalkthrough.isActive) {
            VLPWalkthrough.closeTour();
        }
    });

})(jQuery);
