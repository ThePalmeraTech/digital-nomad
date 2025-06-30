/**
 * Palmerita Subscriptions - Frontend JavaScript
 * Handles modal interactions and AJAX form submissions
 */

(function($) {
    'use strict';
    
    // Main plugin object
    const PalmeritaSubs = {
        
        // Configuration
        config: {
            modalSelector: '#palmerita-subscription-modal',
            formSelector: '#palmerita-subscription-form',
            emailSelector: '#palmerita-email',
            submitSelector: '.palmerita-btn-submit',
            cancelSelector: '.palmerita-btn-cancel',
            closeSelector: '.palmerita-modal-close',
            btnSelector: '[data-palmerita-btn]',
            titleSelector: '#palmerita-modal-title',
            descriptionSelector: '#palmerita-modal-description'
        },
        
        // Modal content for different types
        content: {
            cv: {
                title: '📄 Get my CV',
                description: 'Receive a secure link to view my professional CV with an integrated PDF viewer. The link includes direct download, contact information, and expires in 7 days for security. Maximum 3 downloads per link.',
                submit: 'Get CV Link'
            },
            promo: {
                title: '🎯 Subscribe to Promotions',
                description: 'Join my promotions list to receive special offers on web development, landing pages, online stores, and consulting services. Stay informed about exclusive discounts and new services.',
                submit: 'Subscribe'
            },
            plugin: {
                title: '🗂️ Get the File',
                description: 'Receive a secure link to download the file. The link is unique, expires in 7 days, and allows up to 3 downloads.',
                submit: 'Get Download Link'
            }
        },
        
        // Current subscription type
        currentType: null,
        
        // Initialize the plugin
        init: function() {
            // Merge overridden copy from PHP if provided
            if(typeof palmerita_subs_ajax !== 'undefined' && palmerita_subs_ajax.modal_copy){
                const mc = palmerita_subs_ajax.modal_copy;
                if(mc.cv_title){ this.content.cv.title = mc.cv_title; }
                if(mc.cv_desc){ this.content.cv.description = mc.cv_desc; }
                if(mc.cv_submit_text){ this.content.cv.submit = mc.cv_submit_text; }

                if(mc.promo_title){ this.content.promo.title = mc.promo_title; }
                if(mc.promo_desc){ this.content.promo.description = mc.promo_desc; }
                if(mc.promo_submit_text){ this.content.promo.submit = mc.promo_submit_text; }

                if(mc.file_title){ this.content.plugin.title = mc.file_title; }
                if(mc.file_desc){ this.content.plugin.description = mc.file_desc; }
                if(mc.file_submit_text){ this.content.plugin.submit = mc.file_submit_text; }
            }
            this.bindEvents();
            this.setupModal();
        },
        
        // Bind all event listeners
        bindEvents: function() {
            const self = this;
            
            // Button clicks
            $(document).on('click', this.config.btnSelector, function(e) {
                e.preventDefault();
                const type = $(this).data('type');
                self.openModal(type);
            });
            
            // Modal close events
            $(document).on('click', this.config.closeSelector, function(e) {
                e.preventDefault();
                self.closeModal();
            });
            
            $(document).on('click', this.config.cancelSelector, function(e) {
                e.preventDefault();
                self.closeModal();
            });
            
            // Close modal on backdrop click
            $(document).on('click', this.config.modalSelector, function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // Close modal on ESC key
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $(self.config.modalSelector).hasClass('show')) {
                    self.closeModal();
                }
            });
            
            // Form submission
            $(document).on('submit', this.config.formSelector, function(e) {
                e.preventDefault();
                self.handleFormSubmit();
            });
            
            // Email input validation
            $(document).on('input', this.config.emailSelector, function() {
                self.validateEmail();
            });
        },
        
        // Setup modal initial state
        setupModal: function() {
            // Ensure modal is hidden initially
            $(this.config.modalSelector).removeClass('show');
        },
        
        // Open modal with specific type
        openModal: function(type) {
            if (!this.content[type]) {
                console.error('Invalid subscription type:', type);
                return;
            }
            
            this.currentType = type;
            
            // Set modal content
            $(this.config.titleSelector).text(this.content[type].title);
            $(this.config.descriptionSelector).text(this.content[type].description);
            $(this.config.submitSelector).find('.btn-text').text(this.content[type].submit);
            
            // Reset form
            this.resetForm();
            
            // Show modal
            $(this.config.modalSelector).addClass('show');
            
            // Focus on email input
            setTimeout(() => {
                $(this.config.emailSelector).focus();
            }, 300);
            
            // Prevent body scroll
            $('body').addClass('modal-open');
            
            // Track event (if analytics available)
            this.trackEvent('modal_open', type);
        },
        
        // Close modal
        closeModal: function() {
            $(this.config.modalSelector).removeClass('show');
            $('body').removeClass('modal-open');
            this.currentType = null;
            this.resetForm();
        },
        
        // Reset form to initial state
        resetForm: function() {
            $(this.config.formSelector)[0].reset();
            $(this.config.emailSelector).removeClass('error');
            this.hideMessage();
            this.setSubmitState(false);
        },
        
        // Handle form submission
        handleFormSubmit: function() {
            const email = $(this.config.emailSelector).val().trim();
            
            // Validate email
            if (!this.isValidEmail(email)) {
                this.showMessage(palmerita_subs_ajax.messages.invalid_email, 'error');
                $(this.config.emailSelector).addClass('error').focus();
                return;
            }
            
            // Set loading state
            this.setSubmitState(true);
            
            // Prepare data
            const data = {
                action: 'palmerita_subscribe',
                email: email,
                type: this.currentType,
                nonce: palmerita_subs_ajax.nonce,
                pal_subs_hp: '' // honeypot
            };
            
            const sendRequest = (token = '') => {
                if(token){ data.recaptcha_token = token; }
                $.ajax({
                    url: palmerita_subs_ajax.ajax_url,
                    type: 'POST',
                    data: data,
                    timeout: 10000,
                    success: (response) => {
                        this.handleAjaxSuccess(response);
                    },
                    error: (xhr, status, error) => {
                        this.handleAjaxError(xhr, status, error);
                    }
                });
            };
            
            // If reCAPTCHA site key provided, get token first
            if(palmerita_subs_ajax.recaptcha_site_key){
                grecaptcha.ready(() => {
                    grecaptcha.execute(palmerita_subs_ajax.recaptcha_site_key, {action: 'subscribe'}).then((token) => {
                        sendRequest(token);
                    }).catch(()=>{
                        this.setSubmitState(false);
                        this.showMessage('reCAPTCHA error.', 'error');
                    });
                });
            } else {
                sendRequest();
            }
        },
        
        // Handle successful AJAX response
        handleAjaxSuccess: function(response) {
            this.setSubmitState(false);
            
            if (response.success) {
                this.showMessage(response.data.message, 'success');
                
                // Track successful subscription
                this.trackEvent('subscription_success', this.currentType);
                
                // Close modal after delay
                setTimeout(() => {
                    this.closeModal();
                }, 2000);
                
            } else {
                this.showMessage(response.data || palmerita_subs_ajax.messages.error, 'error');
            }
        },
        
        // Handle AJAX error
        handleAjaxError: function(xhr, status, error) {
            this.setSubmitState(false);
            
            let errorMessage = palmerita_subs_ajax.messages.error;
            
            if (status === 'timeout') {
                errorMessage = 'The request has taken too long. Please try again.';
            } else if (xhr.status === 0) {
                errorMessage = 'Connection error. Please check your internet connection.';
            }
            
            this.showMessage(errorMessage, 'error');
            
            // Track error
            this.trackEvent('subscription_error', this.currentType, error);
        },
        
        // Set submit button loading state
        setSubmitState: function(loading) {
            const $submit = $(this.config.submitSelector);
            const $text = $submit.find('.btn-text');
            const $loading = $submit.find('.btn-loading');
            
            if (loading) {
                $submit.prop('disabled', true);
                $text.hide();
                $loading.show();
            } else {
                $submit.prop('disabled', false);
                $text.show();
                $loading.hide();
            }
        },
        
        // Show message to user
        showMessage: function(message, type) {
            this.hideMessage();
            
            const messageHtml = `
                <div class="palmerita-message ${type}">
                    ${message}
                </div>
            `;
            
            $(this.config.formSelector).prepend(messageHtml);
        },
        
        // Hide message
        hideMessage: function() {
            $('.palmerita-message').remove();
        },
        
        // Validate email input
        validateEmail: function() {
            const email = $(this.config.emailSelector).val().trim();
            const $input = $(this.config.emailSelector);
            
            if (email === '') {
                $input.removeClass('error valid');
                return;
            }
            
            if (this.isValidEmail(email)) {
                $input.removeClass('error').addClass('valid');
            } else {
                $input.removeClass('valid').addClass('error');
            }
        },
        
        // Check if email is valid
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        // Track events (Google Analytics, etc.)
        trackEvent: function(event, type, additional) {
            // Google Analytics 4
            if (typeof gtag !== 'undefined') {
                gtag('event', event, {
                    subscription_type: type,
                    additional_info: additional
                });
            }
            
            // Facebook Pixel
            if (typeof fbq !== 'undefined') {
                fbq('track', 'Lead', {
                    content_name: type === 'cv' ? 'CV Download' : 'Promotions Subscription'
                });
            }
            
            // Console log for debugging
            if (window.console && console.log) {
                console.log('Palmerita Subs Event:', event, type, additional);
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        PalmeritaSubs.init();
    });
    
    // Add CSS for modal-open state
    const modalCSS = `
        <style>
            body.modal-open {
                overflow: hidden;
            }
            
            .form-group input.error {
                border-color: #ef4444 !important;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
            }
            
            .form-group input.valid {
                border-color: #10b981 !important;
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
            }
        </style>
    `;
    
    $('head').append(modalCSS);
    
    // Expose to global scope for external access
    window.PalmeritaSubs = PalmeritaSubs;
    
})(jQuery); 