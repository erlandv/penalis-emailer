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
            $('#select-all-visible-btn').on('click', this.selectAllVisible.bind(this));
            $('#select-all-users-btn').on('click', this.selectAllUsers.bind(this));
            $('#deselect-all-users-btn').on('click', this.deselectAll.bind(this));
            $('#select-authors-btn').on('click', this.selectByRole.bind(this, 'author'));
            $('#select-contributors-btn').on('click', this.selectByRole.bind(this, 'contributor'));
            // Only listen to checkboxes in the table (not hidden ones)
            $(document).on('change', '.user-row .user-checkbox', this.updateSelectedCount.bind(this));
            
            // Form submission
            $('#penalis-email-form').on('submit', this.confirmSubmit.bind(this));
            
            // Preview
            $('#preview-email-btn').on('click', this.previewEmail.bind(this));
            $('#close-preview-modal').on('click', this.closePreview.bind(this));
            $('#email-preview-modal').on('click', this.closePreviewOnOutsideClick.bind(this));
            
            // Draft actions
            $('#save-draft-btn').on('click', this.saveDraft.bind(this));
            $('#load-draft-btn').on('click', this.loadDraft.bind(this));
            $('#clear-draft-btn').on('click', this.clearDraft.bind(this));
            $('#delete-draft-btn').on('click', this.deleteDraft.bind(this));
            
            // Row hover effect
            $('.user-row').hover(
                function() { $(this).css('background-color', '#f6f7f7'); },
                function() { $(this).css('background-color', ''); }
            );
        },
        
        updateSelectedCount: function() {
            // Count visible checkboxes in table
            const visibleChecked = $('.user-row .user-checkbox:checked').length;
            // Count hidden checkboxes
            const hiddenChecked = $('#hidden-user-checkboxes input:checked').length;
            // Total selected
            const selectedCount = visibleChecked + hiddenChecked;
            
            $('#selected-count').text(selectedCount);
            
            // Update "select all" checkbox state (only for visible checkboxes in table)
            const visibleCheckboxes = $('.user-row .user-checkbox');
            const allVisibleChecked = visibleCheckboxes.length > 0 && visibleChecked === visibleCheckboxes.length;
            const someVisibleChecked = visibleChecked > 0 && visibleChecked < visibleCheckboxes.length;
            
            $('#select-all-checkbox').prop('checked', allVisibleChecked);
            $('#select-all-checkbox').prop('indeterminate', someVisibleChecked);
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
            // Only toggle visible checkboxes in the table (not hidden ones)
            $('.user-row .user-checkbox').prop('checked', isChecked);
            this.updateSelectedCount();
        },
        
        selectAllVisible: function() {
            // Only select visible checkboxes in the table (not hidden ones)
            $('.user-row .user-checkbox').prop('checked', true);
            this.updateSelectedCount();
        },
        
        selectAllUsers: function() {
            const button = $('#select-all-users-btn');
            const originalText = button.text();
            
            // Disable button and show loading state
            button.prop('disabled', true).text(penalisAdmin.i18n.selectingAllUsers);
            
            // Make AJAX request to get all user IDs
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_get_all_user_ids',
                nonce: penalisAdmin.nonces.getAllUserIds
            }, function(response) {
                if (response.success) {
                    // Convert all user IDs to integers (AJAX returns strings)
                    const allUserIds = response.data.user_ids.map(function(id) {
                        return parseInt(id);
                    });
                    const hiddenContainer = $('#hidden-user-checkboxes');
                    
                    // Clear existing hidden checkboxes
                    hiddenContainer.empty();
                    
                    // First, check all visible checkboxes in the table
                    $('.user-row .user-checkbox').prop('checked', true);
                    
                    // Get IDs of visible users
                    const visibleUserIds = $('.user-row .user-checkbox').map(function() {
                        return parseInt($(this).val());
                    }).get();
                    
                    // Then, add hidden checkboxes for users not on current page
                    allUserIds.forEach(function(userId) {
                        if (!visibleUserIds.includes(userId)) {
                            // Create hidden checkbox for users not on current page
                            const hiddenCheckbox = $('<input>', {
                                type: 'checkbox',
                                name: 'user_ids[]',
                                value: userId,
                                class: 'hidden-user-checkbox',
                                checked: true
                            });
                            hiddenContainer.append(hiddenCheckbox);
                        }
                    });
                    
                    // Update count
                    ComposeEmailHandler.updateSelectedCount();
                } else {
                    alert(penalisAdmin.i18n.failedToLoadUsers);
                }
                
                // Re-enable button
                button.prop('disabled', false).text(originalText);
            }).fail(function() {
                alert(penalisAdmin.i18n.failedToLoadUsers);
                button.prop('disabled', false).text(originalText);
            });
        },
        
        deselectAll: function() {
            // Deselect all visible checkboxes in table
            $('.user-row .user-checkbox').prop('checked', false);
            // Clear hidden checkboxes
            $('#hidden-user-checkboxes').empty();
            this.updateSelectedCount();
        },
        
        selectByRole: function(role) {
            const button = role === 'author' ? $('#select-authors-btn') : $('#select-contributors-btn');
            const originalText = button.text();
            
            // Disable button and show loading state
            button.prop('disabled', true).text(penalisAdmin.i18n.selectingByRole);
            
            // Make AJAX request to get users by role
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_get_users_by_role',
                role: role,
                nonce: penalisAdmin.nonces.getUsersByRole
            }, function(response) {
                if (response.success) {
                    // Convert all user IDs to integers
                    const roleUserIds = response.data.user_ids.map(function(id) {
                        return parseInt(id);
                    });
                    const hiddenContainer = $('#hidden-user-checkboxes');
                    
                    // Clear existing hidden checkboxes
                    hiddenContainer.empty();
                    
                    // Deselect all visible checkboxes first
                    $('.user-row .user-checkbox').prop('checked', false);
                    
                    // Get IDs of visible users
                    const visibleUserIds = $('.user-row .user-checkbox').map(function() {
                        return parseInt($(this).val());
                    }).get();
                    
                    // Check visible users that match the role
                    $('.user-row').each(function() {
                        const userId = parseInt($(this).find('.user-checkbox').val());
                        if (roleUserIds.includes(userId)) {
                            $(this).find('.user-checkbox').prop('checked', true);
                        }
                    });
                    
                    // Add hidden checkboxes for users with this role not on current page
                    roleUserIds.forEach(function(userId) {
                        if (!visibleUserIds.includes(userId)) {
                            // Create hidden checkbox for users not on current page
                            const hiddenCheckbox = $('<input>', {
                                type: 'checkbox',
                                name: 'user_ids[]',
                                value: userId,
                                class: 'hidden-user-checkbox',
                                checked: true
                            });
                            hiddenContainer.append(hiddenCheckbox);
                        }
                    });
                    
                    // Update count
                    ComposeEmailHandler.updateSelectedCount();
                } else {
                    alert(penalisAdmin.i18n.failedToLoadUsers);
                }
                
                // Re-enable button
                button.prop('disabled', false).text(originalText);
            }).fail(function() {
                alert(penalisAdmin.i18n.failedToLoadUsers);
                button.prop('disabled', false).text(originalText);
            });
        },
        
        confirmSubmit: function(e) {
            // Count visible checkboxes in table
            const visibleChecked = $('.user-row .user-checkbox:checked').length;
            // Count hidden checkboxes
            const hiddenChecked = $('#hidden-user-checkboxes input:checked').length;
            // Total selected
            const selectedCount = visibleChecked + hiddenChecked;
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
        },
        
        saveDraft: function(e) {
            e.preventDefault();
            
            // Set action type to save_draft
            $('#action-type').val('save_draft');
            
            // Submit form
            $('#penalis-email-form').off('submit').submit();
        },
        
        loadDraft: function() {
            const draftId = $('#load-draft-select').val();
            
            if (!draftId) {
                alert(penalisAdmin.i18n.selectDraft || 'Please select a draft to load.');
                return;
            }
            
            // Redirect to compose page with draft_id parameter
            window.location.href = penalisAdmin.composeUrl + '&draft_id=' + draftId;
        },
        
        clearDraft: function() {
            if (!confirm(penalisAdmin.i18n.confirmClearDraft || 'Are you sure you want to clear this draft? Unsaved changes will be lost.')) {
                return;
            }
            
            // Redirect to compose page without draft_id
            window.location.href = penalisAdmin.composeUrl;
        },
        
        deleteDraft: function() {
            const draftId = $(this).data('draft-id');
            
            if (!draftId) {
                return;
            }
            
            if (!confirm(penalisAdmin.i18n.confirmDeleteDraft || 'Are you sure you want to delete this draft permanently?')) {
                return;
            }
            
            const button = $(this);
            button.prop('disabled', true).text(penalisAdmin.i18n.deleting || 'Deleting...');
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_delete_draft',
                nonce: penalisAdmin.nonces.deleteDraft,
                draft_id: draftId
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Redirect to compose page without draft_id
                    window.location.href = penalisAdmin.composeUrl;
                } else {
                    alert(response.data.message);
                    button.prop('disabled', false).text(penalisAdmin.i18n.deleteDraft || 'Delete Draft');
                }
            }).fail(function() {
                alert(penalisAdmin.i18n.deleteDraftFailed || 'Failed to delete draft. Please try again.');
                button.prop('disabled', false).text(penalisAdmin.i18n.deleteDraft || 'Delete Draft');
            });
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
     * Draft Management Handler
     */
    const DraftManagementHandler = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Select all checkbox
            $('#select-all-drafts').on('change', this.toggleSelectAll.bind(this));
            
            // Bulk delete
            $('#doaction, #doaction2').on('click', this.bulkDelete.bind(this));
            
            // Row actions
            $(document).on('click', '.delete-draft', this.deleteSingleDraft.bind(this));
            $(document).on('click', '.preview-draft', this.previewDraft.bind(this));
            $(document).on('click', '.duplicate-draft', this.duplicateDraft.bind(this));
            $(document).on('click', '.send-draft, .send-draft-btn', this.sendDraft.bind(this));
        },
        
        toggleSelectAll: function() {
            const isChecked = $('#select-all-drafts').is(':checked');
            $('.draft-checkbox').prop('checked', isChecked);
        },
        
        bulkDelete: function(e) {
            const action = $(e.currentTarget).attr('id') === 'doaction' 
                ? $('#bulk-action-selector-top').val() 
                : $('#bulk-action-selector-bottom').val();
            
            if (action !== 'delete') {
                return;
            }
            
            const selectedIds = $('.draft-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                alert(penalisAdmin.i18n.selectDrafts);
                return;
            }
            
            const confirmMessage = penalisAdmin.i18n.confirmBulkDeleteDrafts.replace('%d', selectedIds.length);
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Disable button
            $(e.currentTarget).prop('disabled', true).text('Deleting...');
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_bulk_delete_drafts',
                nonce: penalisAdmin.nonces.bulkDeleteDrafts,
                draft_ids: selectedIds
            }, function(response) {
                if (response.success) {
                    // Remove deleted rows
                    $('.draft-checkbox:checked').closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('#drafts-table-body tr').length === 0) {
                            window.location.reload();
                        }
                    });
                    $('#select-all-drafts').prop('checked', false);
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
                $(e.currentTarget).prop('disabled', false).text('Apply');
            }).fail(function() {
                alert('An error occurred. Please try again.');
                $(e.currentTarget).prop('disabled', false).text('Apply');
            });
        },
        
        deleteSingleDraft: function(e) {
            e.preventDefault();
            
            const draftId = $(e.currentTarget).data('draft-id');
            
            if (!confirm(penalisAdmin.i18n.confirmDeleteSingleDraft)) {
                return;
            }
            
            const row = $(e.currentTarget).closest('tr');
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_delete_draft',
                nonce: penalisAdmin.nonces.deleteDraft,
                draft_id: draftId
            }, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('#drafts-table-body tr').length === 0) {
                            window.location.reload();
                        }
                    });
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            }).fail(function() {
                alert('An error occurred. Please try again.');
            });
        },
        
        previewDraft: function(e) {
            e.preventDefault();
            
            const draftId = $(e.currentTarget).data('draft-id');
            
            // Show loading modal
            if ($('#draft-preview-modal').length === 0) {
                $('body').append(`
                    <div id="draft-preview-modal" class="penalis-modal">
                        <div class="penalis-modal-content" style="max-width: 800px;">
                            <span class="penalis-modal-close" id="close-draft-preview">&times;</span>
                            <h2>${penalisAdmin.i18n.previewLoading}</h2>
                            <div id="draft-preview-content">
                                <p style="text-align: center; padding: 40px;">
                                    <span class="spinner is-active" style="float: none;"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                `);
                
                // Bind close event
                $(document).on('click', '#close-draft-preview', function() {
                    $('#draft-preview-modal').hide();
                });
                
                $(document).on('click', '#draft-preview-modal', function(e) {
                    if (e.target === this) {
                        $(this).hide();
                    }
                });
            }
            
            $('#draft-preview-modal').show();
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_preview_draft',
                nonce: penalisAdmin.nonces.previewDraft,
                draft_id: draftId
            }, function(response) {
                if (response.success) {
                    const subject = response.data.subject || '(No subject)';
                    const fromName = response.data.from_name || 'Penalis';
                    
                    $('#draft-preview-content').html(`
                        <div style="margin-bottom: 20px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                            <p style="margin: 0 0 10px 0;"><strong>From:</strong> ${fromName}</p>
                            <p style="margin: 0;"><strong>Subject:</strong> ${subject}</p>
                        </div>
                        <iframe style="width: 100%; height: 500px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
                    `);
                    
                    const iframe = $('#draft-preview-content iframe')[0];
                    iframe.contentWindow.document.open();
                    iframe.contentWindow.document.write(response.data.html);
                    iframe.contentWindow.document.close();
                } else {
                    $('#draft-preview-content').html(`<p style="color: #d63638;">${response.data.message}</p>`);
                }
            }).fail(function() {
                $('#draft-preview-content').html('<p style="color: #d63638;">Failed to load preview.</p>');
            });
        },
        
        duplicateDraft: function(e) {
            e.preventDefault();
            
            const draftId = $(e.currentTarget).data('draft-id');
            const link = $(e.currentTarget);
            const originalText = link.text();
            
            link.text(penalisAdmin.i18n.duplicating);
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_duplicate_draft',
                nonce: penalisAdmin.nonces.duplicateDraft,
                draft_id: draftId
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data.message);
                    link.text(originalText);
                }
            }).fail(function() {
                alert('An error occurred. Please try again.');
                link.text(originalText);
            });
        },
        
        sendDraft: function(e) {
            e.preventDefault();
            
            const draftId = $(e.currentTarget).data('draft-id');
            
            if (!confirm(penalisAdmin.i18n.confirmSendDraft)) {
                return;
            }
            
            const button = $(e.currentTarget);
            const originalText = button.text();
            
            button.prop('disabled', true).text(penalisAdmin.i18n.sendingDraft);
            
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_send_draft_ajax',
                nonce: penalisAdmin.nonces.sendDraftAjax,
                draft_id: draftId
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    // Remove row or reload
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('#drafts-table-body tr').length === 0) {
                            window.location.reload();
                        }
                    });
                } else {
                    alert(response.data.message);
                    button.prop('disabled', false).text(originalText);
                }
            }).fail(function() {
                alert('An error occurred. Please try again.');
                button.prop('disabled', false).text(originalText);
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
        
        if ($('#drafts-filter').length) {
            DraftManagementHandler.init();
        }
    });
    
})(jQuery);
