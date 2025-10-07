/**
 * Admin JavaScript for AVLP Walkthrough Tour plugin
 */

(function($) {
    'use strict';

    // Admin functionality
    const VLPWalkthroughAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add step button
            $(document).on('click', '#add-step-button', this.showAddStepForm);
            $(document).on('click', '.cancel-step', this.hideAddStepForm);
            
            // Edit step
            $(document).on('click', '.edit-step', this.editStep);
            
            // Delete step
            $(document).on('click', '.delete-step', this.deleteStep);
            
            // Save step form
            $(document).on('submit', '.step-form', this.saveStep);
            
            // Tour trigger type change
            $(document).on('change', '#tour_trigger_type', this.handleTriggerTypeChange);
            
            // Element selector helper
            $(document).on('click', '.element-selector-helper', this.openElementSelector);
        },

        /**
         * Initialize sortable functionality for steps
         */
        initSortable: function() {
            if ($('.sortable-steps').length > 0) {
                $('.sortable-steps').sortable({
                    placeholder: 'sortable-placeholder',
                    handle: '.step-item',
                    update: function(event, ui) {
                        VLPWalkthroughAdmin.reorderSteps();
                    }
                });
            }
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                $(this).addClass('vlp-admin-tooltip');
            });
        },

        /**
         * Show add step form
         */
        showAddStepForm: function(e) {
            if (e && e.preventDefault) {
                e.preventDefault();
            }
            
            const form = $('#add-step-form');
            const button = $('#add-step-button');
            
            form.slideDown(300);
            button.hide();
            
            // Focus first input
            form.find('input:first').focus();
        },

        /**
         * Hide add step form
         */
        hideAddStepForm: function(e) {
            if (e && e.preventDefault) {
                e.preventDefault();
            }
            
            const form = $('#add-step-form');
            const button = $('#add-step-button');
            
            form.slideUp(300);
            button.show();
            
            // Reset form
            form[0].reset();
            form.find('input[name="step_id"]').remove();
        },

        /**
         * Edit step
         */
        editStep: function(e) {
            e.preventDefault();
            
            const stepId = $(this).data('step-id');
            const stepItem = $(this).closest('.step-item');
            
            // Show loading state
            stepItem.addClass('vlp-admin-loading');
            
            // Get step data via AJAX
            $.ajax({
                url: ajaxurl || vlp_walkthrough_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vlp_walkthrough_get_step',
                    step_id: stepId,
                    nonce: vlp_walkthrough_ajax.nonce
                },
                success: function(response) {
                    stepItem.removeClass('vlp-admin-loading');
                    
                    if (response.success) {
                        VLPWalkthroughAdmin.populateStepForm(response.data);
                        VLPWalkthroughAdmin.showAddStepForm();
                    } else {
                        alert(vlp_walkthrough_ajax.strings.error);
                    }
                },
                error: function() {
                    stepItem.removeClass('vlp-admin-loading');
                    alert(vlp_walkthrough_ajax.strings.error);
                }
            });
        },

        /**
         * Populate step form with data
         */
        populateStepForm: function(stepData) {
            const form = $('#add-step-form .step-form');
            
            form.find('input[name="step_title"]').val(stepData.step_title);
            form.find('textarea[name="step_content"]').val(stepData.step_content);
            form.find('input[name="target_selector"]').val(stepData.target_selector);
            form.find('select[name="step_position"]').val(stepData.step_position);
            form.find('input[name="page_url_pattern"]').val(stepData.page_url_pattern);
            form.find('input[name="step_order"]').val(stepData.step_order);
            form.find('input[name="step_delay"]').val(stepData.step_delay);
            
            // Add step ID hidden field
            if (!form.find('input[name="step_id"]').length) {
                form.append('<input type="hidden" name="step_id" value="' + stepData.step_id + '">');
            } else {
                form.find('input[name="step_id"]').val(stepData.step_id);
            }
        },

        /**
         * Delete step
         */
        deleteStep: function(e) {
            e.preventDefault();
            
            if (!confirm(vlp_walkthrough_ajax.strings.confirm_delete_step)) {
                return;
            }
            
            const stepId = $(this).data('step-id');
            const stepItem = $(this).closest('.step-item');
            
            // Show loading state
            stepItem.addClass('vlp-admin-loading');
            
            // Delete via AJAX
            $.ajax({
                url: ajaxurl || vlp_walkthrough_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vlp_walkthrough_delete_step_ajax',
                    step_id: stepId,
                    nonce: vlp_walkthrough_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        stepItem.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        stepItem.removeClass('vlp-admin-loading');
                        alert(vlp_walkthrough_ajax.strings.error);
                    }
                },
                error: function() {
                    stepItem.removeClass('vlp-admin-loading');
                    alert(vlp_walkthrough_ajax.strings.error);
                }
            });
        },

        /**
         * Save step form
         */
        saveStep: function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('input[type="submit"]');
            const originalText = submitBtn.val();
            
            // Show loading state
            submitBtn.val(vlp_walkthrough_ajax.strings.saving).prop('disabled', true);
            
            // Submit form
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success || response.indexOf('updated=1') !== -1) {
                        // Reload page to show updated data
                        window.location.reload();
                    } else {
                        alert(vlp_walkthrough_ajax.strings.error);
                        submitBtn.val(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert(vlp_walkthrough_ajax.strings.error);
                    submitBtn.val(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Reorder steps
         */
        reorderSteps: function() {
            const stepIds = [];
            $('.sortable-steps .step-item').each(function() {
                stepIds.push($(this).data('step-id'));
            });
            
            $.ajax({
                url: ajaxurl || vlp_walkthrough_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vlp_walkthrough_reorder_steps',
                    step_ids: stepIds,
                    nonce: vlp_walkthrough_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update step order numbers
                        $('.sortable-steps .step-item').each(function(index) {
                            $(this).find('.step-order').text(index + 1);
                        });
                    }
                },
                error: function() {
                    console.error('Failed to reorder steps');
                }
            });
        },

        /**
         * Handle tour trigger type change
         */
        handleTriggerTypeChange: function() {
            const triggerType = $(this).val();
            const triggerValueRow = $('#trigger_value_row');
            const description = $('#trigger_value_description');
            
            if (triggerType === 'automatic') {
                triggerValueRow.slideUp(200);
            } else {
                triggerValueRow.slideDown(200);
                
                if (triggerType === 'manual') {
                    description.text('Shortcode to use: [vlp_walkthrough_tour tour_id="X"]');
                } else if (triggerType === 'url_parameter') {
                    description.text('URL parameter name (e.g., "show_tour"). Tour will show when URL contains ?show_tour=1');
                }
            }
        },

        /**
         * Open element selector helper
         */
        openElementSelector: function(e) {
            e.preventDefault();
            
            // Create modal for element selection
            const modal = $('<div class="vlp-element-selector-modal">' +
                '<div class="vlp-modal-content">' +
                    '<div class="vlp-modal-header">' +
                        '<h3>Select Element</h3>' +
                        '<button type="button" class="vlp-modal-close">&times;</button>' +
                    '</div>' +
                    '<div class="vlp-modal-body">' +
                        '<p>Click on any element on the page to select it as the target for this step.</p>' +
                        '<div class="vlp-selected-element"></div>' +
                    '</div>' +
                    '<div class="vlp-modal-footer">' +
                        '<button type="button" class="button button-primary vlp-confirm-selection">Use Selected Element</button>' +
                        '<button type="button" class="button vlp-cancel-selection">Cancel</button>' +
                    '</div>' +
                '</div>' +
            '</div>');
            
            $('body').append(modal);
            
            // Add overlay
            const overlay = $('<div class="vlp-modal-overlay"></div>');
            $('body').append(overlay);
            
            // Show modal
            modal.fadeIn(300);
            overlay.fadeIn(300);
            
            // Enable element selection mode
            VLPWalkthroughAdmin.enableElementSelection(modal);
        },

        /**
         * Enable element selection mode
         */
        enableElementSelection: function(modal) {
            let selectedElement = null;
            
            // Add hover effect to all elements
            $('*').addClass('vlp-element-hover');
            
            // Handle element hover
            $(document).on('mouseenter.vlp-selector', '*', function(e) {
                e.stopPropagation();
                $(this).addClass('vlp-element-highlight');
                VLPWalkthroughAdmin.updateSelectedElementInfo($(this), modal);
            });
            
            $(document).on('mouseleave.vlp-selector', '*', function(e) {
                e.stopPropagation();
                $(this).removeClass('vlp-element-highlight');
            });
            
            // Handle element click
            $(document).on('click.vlp-selector', '*', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                selectedElement = $(this);
                selectedElement.addClass('vlp-element-selected');
                
                VLPWalkthroughAdmin.updateSelectedElementInfo(selectedElement, modal);
            });
            
            // Confirm selection
            modal.find('.vlp-confirm-selection').on('click', function() {
                if (selectedElement) {
                    const selector = VLPWalkthroughAdmin.generateSelector(selectedElement);
                    $('#target_selector').val(selector);
                }
                VLPWalkthroughAdmin.closeElementSelector(modal);
            });
            
            // Cancel selection
            modal.find('.vlp-cancel-selection, .vlp-modal-close').on('click', function() {
                VLPWalkthroughAdmin.closeElementSelector(modal);
            });
            
            // Close on overlay click
            $('.vlp-modal-overlay').on('click', function() {
                VLPWalkthroughAdmin.closeElementSelector(modal);
            });
        },

        /**
         * Close element selector
         */
        closeElementSelector: function(modal) {
            // Remove event handlers
            $(document).off('.vlp-selector');
            
            // Remove classes
            $('*').removeClass('vlp-element-hover vlp-element-highlight vlp-element-selected');
            
            // Hide modal
            modal.fadeOut(300, function() {
                $(this).remove();
            });
            
            $('.vlp-modal-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Update selected element info
         */
        updateSelectedElementInfo: function(element, modal) {
            const tagName = element.prop('tagName').toLowerCase();
            const id = element.attr('id');
            const classes = element.attr('class');
            const selector = VLPWalkthroughAdmin.generateSelector(element);
            
            const info = '<strong>Tag:</strong> ' + tagName + '<br>' +
                        (id ? '<strong>ID:</strong> ' + id + '<br>' : '') +
                        (classes ? '<strong>Classes:</strong> ' + classes + '<br>' : '') +
                        '<strong>Selector:</strong> ' + selector;
            
            modal.find('.vlp-selected-element').html(info);
        },

        /**
         * Generate CSS selector for element
         */
        generateSelector: function(element) {
            // Try ID first
            const id = element.attr('id');
            if (id) {
                return '#' + id;
            }
            
            // Try data attributes
            const dataTour = element.attr('data-tour');
            if (dataTour) {
                return '[data-tour="' + dataTour + '"]';
            }
            
            // Try classes
            const classes = element.attr('class');
            if (classes) {
                const classList = classes.split(' ').filter(cls => cls.trim());
                if (classList.length > 0) {
                    return '.' + classList.join('.');
                }
            }
            
            // Fall back to tag name with parent context
            const tagName = element.prop('tagName').toLowerCase();
            const parent = element.parent();
            
            if (parent.length && parent.attr('id')) {
                return '#' + parent.attr('id') + ' ' + tagName;
            }
            
            return tagName;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VLPWalkthroughAdmin.init();
    });

    // Add CSS for element selector
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .vlp-element-selector-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 999999;
                display: none;
            }
            
            .vlp-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999998;
                display: none;
            }
            
            .vlp-modal-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 8px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                min-width: 400px;
                max-width: 600px;
                z-index: 999999;
            }
            
            .vlp-modal-header {
                padding: 16px 20px;
                border-bottom: 1px solid #e9ecef;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .vlp-modal-header h3 {
                margin: 0;
                color: #333;
            }
            
            .vlp-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
            }
            
            .vlp-modal-body {
                padding: 20px;
            }
            
            .vlp-modal-footer {
                padding: 16px 20px;
                border-top: 1px solid #e9ecef;
                text-align: right;
            }
            
            .vlp-modal-footer .button {
                margin-left: 8px;
            }
            
            .vlp-selected-element {
                background: #f8f9fa;
                padding: 12px;
                border-radius: 4px;
                margin-top: 12px;
                font-family: monospace;
                font-size: 12px;
                line-height: 1.4;
            }
            
            .vlp-element-hover {
                cursor: crosshair !important;
            }
            
            .vlp-element-highlight {
                outline: 2px solid #ff6600 !important;
                outline-offset: 2px !important;
            }
            
            .vlp-element-selected {
                outline: 3px solid #0066ff !important;
                outline-offset: 3px !important;
            }
        `)
        .appendTo('head');

})(jQuery);
