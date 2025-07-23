/**
 * CSV Page Generator Admin JavaScript
 *
 * @package ReasonDigital\CSVPageGenerator
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

(function($) {
    'use strict';

    /**
     * CSV Page Generator Admin Object
     */
    const CSVPageGeneratorAdmin = {
        
        /**
         * Initialize the admin functionality
         */
        init: function() {
            this.bindEvents();
            this.setupFileUpload();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submission
            $('#csv-upload-form').on('submit', this.handleFormSubmission.bind(this));
            
            // File input change
            $('#csv_file').on('change', this.handleFileSelection.bind(this));
            
            // Cancel import
            $('#cancel-import-btn').on('click', this.cancelImport.bind(this));
            
            // Start new import
            $('#start-new-import-btn').on('click', this.startNewImport.bind(this));
            
            // View created pages
            $('#view-created-pages-btn').on('click', this.viewCreatedPages.bind(this));
            
            // Download log
            $('#download-log-btn').on('click', this.downloadLog.bind(this));
        },

        /**
         * Setup file upload functionality
         */
        setupFileUpload: function() {
            const fileInput = $('#csv_file');
            const maxSize = parseInt(fileInput.data('max-size'), 10);

            // Add drag and drop functionality
            const uploadArea = $('.csv-file-upload');
            
            uploadArea.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });

            uploadArea.on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
            });

            uploadArea.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    fileInput[0].files = files;
                    CSVPageGeneratorAdmin.handleFileSelection();
                }
            });
        },

        /**
         * Handle file selection
         */
        handleFileSelection: function() {
            const fileInput = $('#csv_file')[0];
            const file = fileInput.files[0];
            
            if (!file) {
                return;
            }

            // Validate file type
            if (!file.name.toLowerCase().endsWith('.csv')) {
                this.showError(csvPageGenerator.strings.error + ': ' + 'Please select a CSV file.');
                fileInput.value = '';
                return;
            }

            // Validate file size
            const maxSize = parseInt($('#csv_file').data('max-size'), 10);
            if (file.size > maxSize) {
                this.showError(csvPageGenerator.strings.error + ': ' + 'File size exceeds the maximum limit.');
                fileInput.value = '';
                return;
            }

            // Show file info
            this.showFileInfo(file);
        },

        /**
         * Show file information
         */
        showFileInfo: function(file) {
            const fileSize = this.formatFileSize(file.size);
            const fileName = file.name;
            
            $('.file-upload-info').html(
                '<p class="file-selected"><strong>Selected:</strong> ' + fileName + ' (' + fileSize + ')</p>'
            );
        },

        /**
         * Handle form submission
         */
        handleFormSubmission: function(e) {
            e.preventDefault();
            
            const form = $('#csv-upload-form');
            const fileInput = $('#csv_file')[0];
            
            // Validate form
            if (!this.validateForm()) {
                return;
            }

            // Show progress section
            this.showProgress();
            
            // Prepare form data
            const formData = new FormData(form[0]);
            formData.append('action', 'csv_page_generator_upload');
            formData.append('nonce', csvPageGenerator.nonce);

            // Start upload
            this.uploadFile(formData);
        },

        /**
         * Validate form before submission
         */
        validateForm: function() {
            const fileInput = $('#csv_file')[0];
            
            if (!fileInput.files || fileInput.files.length === 0) {
                this.showError('Please select a CSV file.');
                return false;
            }

            return true;
        },

        /**
         * Upload file via AJAX
         */
        uploadFile: function(formData) {
            const self = this;
            
            $.ajax({
                url: csvPageGenerator.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    
                    // Upload progress
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            self.updateProgress(percentComplete, 'Uploading...');
                        }
                    }, false);
                    
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        self.handleUploadSuccess(response.data);
                    } else {
                        self.handleUploadError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    self.handleUploadError({
                        message: 'Upload failed: ' + error
                    });
                }
            });
        },

        /**
         * Handle successful upload
         */
        handleUploadSuccess: function(data) {
            this.updateProgress(100, 'Processing...');
            
            // Start processing
            this.startProcessing(data.import_id);
        },

        /**
         * Handle upload error
         */
        handleUploadError: function(data) {
            this.showError(data.message || 'Upload failed.');
            this.hideProgress();
        },

        /**
         * Start processing the uploaded file
         */
        startProcessing: function(importId) {
            // This would typically start a background process
            // For now, we'll simulate processing
            this.simulateProcessing();
        },

        /**
         * Simulate processing for demonstration
         */
        simulateProcessing: function() {
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 10;
                
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    this.showResults({
                        total: 100,
                        successful: 95,
                        failed: 5,
                        created_pages: []
                    });
                }
                
                this.updateProgress(progress, 'Processing rows...');
                this.updateProgressDetails(Math.floor(progress), 100, Math.floor(progress * 0.95), Math.floor(progress * 0.05));
            }, 500);
        },

        /**
         * Update progress bar and status
         */
        updateProgress: function(percentage, status) {
            $('.progress-fill').css('width', percentage + '%');
            $('.progress-percentage').text(Math.round(percentage) + '%');
            $('.progress-status').text(status);
        },

        /**
         * Update progress details
         */
        updateProgressDetails: function(processed, total, successful, errors) {
            $('.processed-count').text('Processed: ' + processed + ' of ' + total + ' rows');
            $('.success-count').text('Successful: ' + successful);
            $('.error-count').text('Errors: ' + errors);
        },

        /**
         * Show progress section
         */
        showProgress: function() {
            $('.upload-section').hide();
            $('#upload-progress').show();
            this.updateProgress(0, csvPageGenerator.strings.uploading);
        },

        /**
         * Hide progress section
         */
        hideProgress: function() {
            $('#upload-progress').hide();
            $('.upload-section').show();
        },

        /**
         * Show results section
         */
        showResults: function(results) {
            $('#upload-progress').hide();
            
            const resultsHtml = this.buildResultsHtml(results);
            $('.results-summary').html(resultsHtml);
            
            $('#import-results').show();
        },

        /**
         * Build results HTML
         */
        buildResultsHtml: function(results) {
            return `
                <div class="results-stats">
                    <p><strong>Import completed successfully!</strong></p>
                    <ul>
                        <li>Total rows processed: ${results.total}</li>
                        <li>Pages created successfully: ${results.successful}</li>
                        <li>Errors encountered: ${results.failed}</li>
                    </ul>
                </div>
            `;
        },

        /**
         * Cancel import
         */
        cancelImport: function() {
            if (confirm(csvPageGenerator.strings.confirmCancel || 'Are you sure you want to cancel the import?')) {
                // Cancel the import process
                this.hideProgress();
                this.showError('Import cancelled by user.');
            }
        },

        /**
         * Start new import
         */
        startNewImport: function() {
            $('#import-results').hide();
            $('.upload-section').show();
            $('#csv-upload-form')[0].reset();
            $('.file-upload-info').html('');
        },

        /**
         * View created pages
         */
        viewCreatedPages: function() {
            window.open(csvPageGenerator.adminUrl + 'edit.php?post_type=page', '_blank');
        },

        /**
         * Download import log
         */
        downloadLog: function() {
            // This would trigger a download of the import log
            this.showNotice('Log download functionality coming soon.');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice message
         */
        showNotice: function(message, type = 'info') {
            const noticeClass = type === 'error' ? 'notice-error' : 'notice-info';
            const notice = $(`
                <div class="notice ${noticeClass} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut();
            }, 5000);
        },

        /**
         * Format file size for display
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        CSVPageGeneratorAdmin.init();
    });

})(jQuery);
