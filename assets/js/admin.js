/**
 * Penalis Emailer - Admin Scripts
 * 
 * @package Penalis_Emailer
 * @since 1.1.0
 */

(function($) {
    'use strict';
    
    /**
     * Compose Email Page Handler
     */
    const ComposeEmailHandler = {
        
        init: function() {
            this.bindEvents();
            this.updateSelectedCount();
        },
        
        bindEvents: function() {
            // User selection
            $('#user-search').on('keyup', this.filterUsers.bind(this));
            $('#select-all-checkbox').on('change', this.toggleSelectAll.bind(this));
            $('#select-all-users-btn').on('click', this.selectAllVisible.bind(this));
            $('#deselect-all-users-btn').on('click', this.deselectAll.bind(this));
            $('#select-authors-btn').on('click', this.selectByRole.bind(this, 'author'));
            $('#select-contributors-btn').on('click', this.selectByRole.bind(this, 'contributor'));
            $('.user-checkbox').on('change', this.updateSelectedCount.bind(this));
            
            // Form submission
            $('#penalis-email-form').on('submit', this.confirmSubmit.bind(this));
            
            // Preview
            $('#preview-email-btn').on('click', this.previewEmail.bind(this));
            $('#close-preview-modal').on('click', this.closePreview.bind(this));
            $('#email-preview-modal').on('click', this.closePreviewOnOutsideClick.bind(this));
            
            // Row hover effect
            $('.user-row').hover(
                function() { $(this).css('background-color', '#f6f7f7'); },
                function() { $(this).css('background-color', ''); }
            );
        },
        
        updateSelectedCount: function() {
            const selectedCount = $('.user-checkbox:checked').length;
            const totalCount = $('.user-checkbox').length;
            $('#selected-count').text(selectedCount);
            
            // Update "select all" checkbox state
            const allChecked = selectedCount > 0 && selectedCount === totalCount;
            const someChecked = selectedCount > 0 && selectedCount < totalCount;
            $('#select-all-checkbox').prop('checked', allChecked);
            $('#select-all-checkbox').prop('indeterminate', someChecked);
        },
        
        filterUsers: function() {
            const searchTerm = $('#user-search').val().toLowerCase();
            
            $('.user-row').each(function() {
                const name = $(this).data('name');
                const email = $(this).data('email');
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            this.updateSelectedCount();
        },
        
        toggleSelectAll: function() {
            const isChecked = $('#select-all-checkbox').is(':checked');
            $('.user-checkbox:visible').prop('checked', isChecked);
            this.updateSelectedCount();
        },
        
        selectAllVisible: function() {
            $('.user-checkbox:visible').prop('checked', true);
            this.updateSelectedCount();
        },
        
        deselectAll: function() {
            $('.user-checkbox').prop('checked', false);
            this.updateSelectedCount();
        },
        
        selectByRole: function(role) {
            $('.user-row').each(function() {
                const userRole = $(this).data('role');
                if (userRole.includes(role)) {
                    $(this).find('.user-checkbox').prop('checked', true);
                }
            });
            this.updateSelectedCount();
        },
        
        confirmSubmit: function(e) {
            const selectedCount = $('.user-checkbox:checked').length;
            const subject = $('#subject').val();
            
            if (selectedCount === 0) {
                alert(penalisAdmin.i18n.selectRecipients);
                e.preventDefault();
                return false;
            }
            
            const message = penalisAdmin.i18n.confirmSend + '\n\n' +
                          penalisAdmin.i18n.subject + ': ' + subject + '\n' +
                          penalisAdmin.i18n.recipients + ': ' + selectedCount + ' ' + penalisAdmin.i18n.users;
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },
        
        previewEmail: function() {
            const body = $('#body').val();
            
            if (!body) {
                alert(penalisAdmin.i18n.enterBodyFirst);
                return;
            }
            
            $('#email-preview-modal').show();
            $('#preview-loading').show();
            $('#email-preview-iframe').hide();
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_preview_email',
                body: body,
                nonce: penalisAdmin.nonces.preview
            }, function(response) {
                $('#preview-loading').hide();
                
                if (response.success) {
                    $('#email-preview-iframe').show();
                    const iframe = document.getElementById('email-preview-iframe');
                    iframe.contentWindow.document.open();
                    iframe.contentWindow.document.write(response.data.html);
                    iframe.contentWindow.document.close();
                } else {
                    alert(penalisAdmin.i18n.previewFailed);
                    $('#email-preview-modal').hide();
                }
            });
        },
        
        closePreview: function() {
            $('#email-preview-modal').hide();
        },
        
        closePreviewOnOutsideClick: function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        }
    };
    
    /**
     * Template Settings Page Handler
     */
    const TemplateSettingsHandler = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#preview-template').on('click', this.previewTemplate.bind(this));
            $('#test-email-btn').on('click', this.sendTestEmail.bind(this));
            $('#close-preview').on('click', this.closePreview.bind(this));
            $('#template-preview-modal').on('click', this.closePreviewOnOutsideClick.bind(this));
        },
        
        previewTemplate: function() {
            const body = $('#email_body').val();
            
            // Replace placeholders with sample data
            const preview = body
                .replace(/{AUTHOR_NAME}/g, 'John Doe')
                .replace(/{POST_TITLE}/g, 'Sample Post Title')
                .replace(/{POST_URL}/g, penalisAdmin.siteUrl + '/sample-post')
                .replace(/{TANGGAL}/g, penalisAdmin.currentDate)
                .replace(/{DATE}/g, penalisAdmin.currentDate)
                .replace(/{SITE_NAME}/g, penalisAdmin.siteName)
                .replace(/{SITE_URL}/g, penalisAdmin.siteUrl);
            
            // Show modal with loading
            $('#template-preview-modal').show();
            $('#template-preview-loading').show();
            $('#preview-iframe').hide();
            
            // Send AJAX request
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_preview_auto_email',
                body: preview,
                nonce: penalisAdmin.nonces.previewAuto
            }, function(response) {
                $('#template-preview-loading').hide();
                
                if (response.success) {
                    $('#preview-iframe').show();
                    const iframe = document.getElementById('preview-iframe');
                    iframe.contentWindow.document.open();
                    iframe.contentWindow.document.write(response.data.html);
                    iframe.contentWindow.document.close();
                } else {
                    alert(penalisAdmin.i18n.previewFailed);
                    $('#template-preview-modal').hide();
                }
            });
        },
        
        sendTestEmail: function() {
            const body = $('#email_body').val();
            const button = $(this);
            
            if (!confirm(penalisAdmin.i18n.confirmTestEmail)) {
                return;
            }
            
            button.prop('disabled', true).text(penalisAdmin.i18n.sending);
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_send_test_email',
                body: body,
                nonce: penalisAdmin.nonces.testEmail
            }, function(response) {
                button.prop('disabled', false).text(penalisAdmin.i18n.sendTestEmail);
                
                if (response.success) {
                    alert(penalisAdmin.i18n.testEmailSent);
                } else {
                    alert(penalisAdmin.i18n.testEmailFailed + ' ' + (response.data.message || ''));
                }
            });
        },
        
        closePreview: function() {
            $('#template-preview-modal').hide();
        },
        
        closePreviewOnOutsideClick: function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        }
    };
    
    /**
     * History Delete Handler
     */
    const HistoryDeleteHandler = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Select all checkbox
            $('#select-all-logs').on('change', this.toggleSelectAll.bind(this));
            
            // Bulk delete
            $('#doaction').on('click', this.bulkDelete.bind(this));
            
            // Clear all
            $('#clear-all-logs').on('click', this.clearAll.bind(this));
        },
        
        toggleSelectAll: function() {
            const isChecked = $('#select-all-logs').is(':checked');
            $('.log-checkbox').prop('checked', isChecked);
        },
        
        bulkDelete: function() {
            const action = $('#bulk-action-selector').val();
            
            if (action !== 'delete') {
                return;
            }
            
            const selectedIds = $('.log-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                alert(penalisAdmin.i18n.selectLogs);
                return;
            }
            
            const confirmMessage = penalisAdmin.i18n.confirmBulkDelete.replace('%d', selectedIds.length);
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Disable button
            $('#doaction').prop('disabled', true).text('Deleting...');
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_bulk_delete_logs',
                nonce: penalisAdmin.nonces.bulkDeleteLogs,
                log_ids: selectedIds
            }, function(response) {
                if (response.success) {
                    $('.log-checkbox:checked').closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('#history-table-body tr').length === 0) {
                            window.location.reload();
                        }
                    });
                    $('#select-all-logs').prop('checked', false);
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
                $('#doaction').prop('disabled', false).text('Apply');
            }).fail(function(xhr, status, error) {
                alert('An error occurred. Please try again.');
                $('#doaction').prop('disabled', false).text('Apply');
            });
        },
        
        clearAll: function(e) {
            const button = $(e.currentTarget);
            const type = button.data('type');
            const typeName = type === 'manual' ? 'Manual' : 'Automatic';
            
            const confirmMessage = penalisAdmin.i18n.confirmClearAll.replace('%s', typeName);
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Double confirmation for safety
            if (!confirm(penalisAdmin.i18n.confirmClearAllFinal)) {
                return;
            }
            
            // Disable button
            button.prop('disabled', true).text('Clearing...');
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_clear_all_logs',
                nonce: penalisAdmin.nonces.clearAllLogs,
                type: type
            }, function(response) {
                if (response.success) {
                    // Reload page to show empty state
                    window.location.reload();
                } else {
                    alert(response.data.message);
                    button.prop('disabled', false).text('Clear All ' + typeName + ' History');
                }
            }).fail(function(xhr, status, error) {
                alert('An error occurred. Please try again.');
                button.prop('disabled', false).text('Clear All ' + typeName + ' History');
            });
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Initialize based on current page
        if ($('#penalis-email-form').length) {
            ComposeEmailHandler.init();
        }
        
        if ($('.penalis-history-list').length) {
            HistoryDeleteHandler.init();
        }
        
        if ($('#template-settings-form').length) {
            TemplateSettingsHandler.init();
        }
    });
    
})(jQuery);
