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
        
        autosaveTimer: null,
        autosaveInterval: 60000, // 60 seconds
        lastAutosaveData: null,
        
        init: function() {
            this.bindEvents();
            this.updateSelectedCount();
            this.initAutosave();
            // Initialise infinite scroll for the recipients list
            InfiniteScrollHandler.init();
        },
        
        bindEvents: function() {
            // User selection
            $('#user-search').on('input', this.onSearchInput.bind(this));
            $('#select-all-users-btn').on('click', this.selectAllUsers.bind(this));
            $('#deselect-all-users-btn').on('click', this.deselectAll.bind(this));
            $('#select-authors-btn').on('click', this.selectByRole.bind(this, 'author'));
            $('#select-contributors-btn').on('click', this.selectByRole.bind(this, 'contributor'));
            // Listen to checkbox changes
            $(document).on('change', '.user-checkbox', this.updateSelectedCount.bind(this));
            
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
        },
        
        updateSelectedCount: function() {
            // Count visible checkboxes
            const visibleChecked = $('.user-checkbox:checked').length;
            // Count hidden checkboxes
            const hiddenChecked = $('#hidden-user-checkboxes input:checked').length;
            // Total selected
            const selectedCount = visibleChecked + hiddenChecked;
            
            $('#selected-count').text(selectedCount);
            $('#main-selected-count').text(selectedCount);
        },
        
        // Debounce timer for search input
        searchTimer: null,

        /**
         * Handle search input — debounced AJAX search.
         * Replaces the old DOM-only filterUsers method.
         */
        onSearchInput: function() {
            clearTimeout(this.searchTimer);
            const self = this;
            this.searchTimer = setTimeout(function() {
                self.performSearch($('#user-search').val().trim());
            }, 300);
        },

        /**
         * Perform AJAX search or restore the normal list.
         *
         * @param {string} term
         */
        performSearch: function(term) {
            const $list = $('#recipients-list');

            if (term === '') {
                // Empty search — restore the list to its initial batch via AJAX
                // (no page reload, so checked state is preserved)
                if ($list.data('search-mode')) {
                    this.clearSearch();
                }
                return;
            }

            // Mark list as being in search mode so infinite scroll is paused
            $list.data('search-mode', true);
            InfiniteScrollHandler.pause();

            // Save checked state of currently visible checkboxes to hidden
            // container before replacing the list
            this.saveVisibleCheckedToHidden();

            // Show loading state
            $list.html(
                '<div class="penalis-recipients-loading penalis-recipients-loading--full">' +
                '<span class="penalis-spinner-sm"></span>' +
                '<span>' + (penalisAdmin.i18n.loadingRecipients || 'Loading...') + '</span>' +
                '</div>'
            );

            const self = this;

            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_load_recipients',
                nonce:  penalisAdmin.nonces.loadRecipients,
                search: term
            }, function(response) {
                if (!response.success || !response.data.html) {
                    $list.html('<div class="penalis-no-users"><p>' + (penalisAdmin.i18n.searchNoResults || 'No users found.') + '</p></div>');
                    return;
                }

                $list.html(response.data.html);

                // Re-apply checked state for users that were already selected
                self.restoreCheckedState();
                self.updateSelectedCount();
            }).fail(function() {
                $list.html('<div class="penalis-no-users"><p>Search failed. Please try again.</p></div>');
            });
        },

        /**
         * Clear search mode: re-fetch the first batch via AJAX and restore
         * the list to its normal infinite-scroll state.
         * Called when the search box is emptied.
         */
        clearSearch: function() {
            const $list  = $('#recipients-list');
            const self   = this;
            const batch  = parseInt($list.data('batch')) || 30;

            // Save any checkboxes that are currently checked in the search
            // results before we replace the list
            this.saveVisibleCheckedToHidden();

            // Show loading state while fetching
            $list.html(
                '<div class="penalis-recipients-loading penalis-recipients-loading--full">' +
                '<span class="penalis-spinner-sm"></span>' +
                '<span>' + (penalisAdmin.i18n.loadingRecipients || 'Loading...') + '</span>' +
                '</div>'
            );

            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_load_recipients',
                nonce:  penalisAdmin.nonces.loadRecipients,
                offset: 0
            }, function(response) {
                if (!response.success || !response.data.html) {
                    $list.html('<div class="penalis-no-users"><p>Failed to reload list.</p></div>');
                    return;
                }

                // Build the list HTML — items + sentinel + loading indicator
                // if there are more users to load
                let html = response.data.html;

                if (response.data.has_more) {
                    html += '<div id="recipients-scroll-sentinel" class="penalis-scroll-sentinel"></div>'
                          + '<div id="recipients-loading" class="penalis-recipients-loading" style="display:none;">'
                          + '<span class="penalis-spinner-sm"></span>'
                          + '<span>' + (penalisAdmin.i18n.loadingRecipients || 'Loading...') + '</span>'
                          + '</div>';
                }

                $list.html(html);

                // Update list state attributes
                $list.data('offset',     batch);
                $list.data('has-more',   response.data.has_more ? 1 : 0);
                $list.data('search-mode', false);

                // Restore checked state for users now visible in the list
                self.restoreCheckedState();
                self.updateSelectedCount();

                // Re-attach IntersectionObserver if more items exist
                if (response.data.has_more) {
                    InfiniteScrollHandler.resume();
                }
            }).fail(function() {
                $list.html('<div class="penalis-no-users"><p>Failed to reload list.</p></div>');
            });
        },

        /**
         * Move all currently checked visible checkboxes into the hidden
         * container so their values survive a list replacement.
         */
        saveVisibleCheckedToHidden: function() {
            const $hidden = $('#hidden-user-checkboxes');
            const existingHiddenIds = $hidden.find('input').map(function() {
                return parseInt($(this).val());
            }).get();

            $('.user-checkbox:checked').each(function() {
                const id = parseInt($(this).val());
                if (!existingHiddenIds.includes(id)) {
                    $hidden.append(
                        $('<input>', {
                            type:    'checkbox',
                            name:    'user_ids[]',
                            value:   id,
                            class:   'hidden-user-checkbox',
                            checked: true
                        })
                    );
                }
            });
        },

        /**
         * After replacing the list HTML, re-check any checkboxes whose IDs
         * are already in the hidden container (i.e. were previously selected).
         */
        restoreCheckedState: function() {
            const selectedIds = $('#hidden-user-checkboxes input:checked').map(function() {
                return parseInt($(this).val());
            }).get();

            if (selectedIds.length === 0) return;

            $('.user-checkbox').each(function() {
                const id = parseInt($(this).val());
                if (selectedIds.includes(id)) {
                    $(this).prop('checked', true);
                    // Remove from hidden container — it's now visible
                    $('#hidden-user-checkboxes input[value="' + id + '"]').remove();
                }
            });
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
                    
                    // First, check all visible checkboxes
                    $('.user-checkbox').prop('checked', true);
                    
                    // Get IDs of visible users
                    const visibleUserIds = $('.user-checkbox').map(function() {
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
            // Deselect all visible checkboxes
            $('.user-checkbox').prop('checked', false);
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
                    $('.user-checkbox').prop('checked', false);
                    
                    // Get IDs of visible users
                    const visibleUserIds = $('.user-checkbox').map(function() {
                        return parseInt($(this).val());
                    }).get();
                    
                    // Check visible users that match the role
                    $('.penalis-user-item').each(function() {
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
            // Count visible checkboxes
            const visibleChecked = $('.user-checkbox:checked').length;
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
        
        initAutosave: function() {
            // Check if auto-save is enabled
            if (!penalisAdmin.autosaveEnabled) {
                return;
            }
            
            // Start auto-save timer
            this.autosaveTimer = setInterval(this.performAutosave.bind(this), this.autosaveInterval);
            
            // Show auto-save indicator
            if ($('#autosave-indicator').length === 0) {
                $('.penalis-submit-actions').before(`
                    <div id="autosave-indicator" style="margin-bottom: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px; display: none;">
                        <span class="dashicons dashicons-backup" style="color: #2271b1;"></span>
                        <span id="autosave-message"></span>
                    </div>
                `);
            }
            
            // Clear timer on form submit
            $('#penalis-email-form').on('submit', function() {
                clearInterval(ComposeEmailHandler.autosaveTimer);
            });
        },
        
        performAutosave: function() {
            // Get current form data
            const currentData = this.getFormData();
            
            // Check if data has changed
            if (this.lastAutosaveData && JSON.stringify(currentData) === JSON.stringify(this.lastAutosaveData)) {
                return; // No changes, skip auto-save
            }
            
            // Check if form has any content
            if (!currentData.subject && !currentData.body && currentData.user_ids.length === 0) {
                return; // Empty form, skip auto-save
            }
            
            // Show saving indicator
            $('#autosave-indicator').show();
            $('#autosave-message').html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + (penalisAdmin.i18n.autoSaving || 'Auto-saving...'));
            
            // Perform auto-save
            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_autosave_draft',
                nonce: penalisAdmin.nonces.autosaveDraft,
                draft_id: $('#draft-id').val(),
                from_name: currentData.from_name,
                subject: currentData.subject,
                body: currentData.body,
                user_ids: currentData.user_ids
            }, function(response) {
                if (response.success) {
                    // Update draft ID if new draft was created
                    if (response.data.draft_id && !$('#draft-id').val()) {
                        $('#draft-id').val(response.data.draft_id);
                    }
                    
                    // Show success message
                    const timestamp = new Date(response.data.timestamp * 1000);
                    const timeString = timestamp.toLocaleTimeString();
                    $('#autosave-message').html('<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' + (penalisAdmin.i18n.autoSaved || 'Auto-saved at') + ' ' + timeString);
                    
                    // Update last saved data
                    ComposeEmailHandler.lastAutosaveData = currentData;
                    
                    // Hide indicator after 3 seconds
                    setTimeout(function() {
                        $('#autosave-indicator').fadeOut();
                    }, 3000);
                } else {
                    $('#autosave-message').html('<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ' + (response.data.message || 'Auto-save failed'));
                    
                    setTimeout(function() {
                        $('#autosave-indicator').fadeOut();
                    }, 5000);
                }
            }).fail(function() {
                $('#autosave-message').html('<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ' + (penalisAdmin.i18n.autoSaveFailed || 'Auto-save failed'));
                
                setTimeout(function() {
                    $('#autosave-indicator').fadeOut();
                }, 5000);
            });
        },
        
        getFormData: function() {
            // Get visible checkboxes
            const visibleUserIds = $('.user-checkbox:checked').map(function() {
                return parseInt($(this).val());
            }).get();
            
            // Get hidden checkboxes
            const hiddenUserIds = $('#hidden-user-checkboxes input:checked').map(function() {
                return parseInt($(this).val());
            }).get();
            
            // Combine and deduplicate
            const allUserIds = [...new Set([...visibleUserIds, ...hiddenUserIds])];
            
            return {
                from_name: $('#from_name').val(),
                subject: $('#subject').val(),
                body: $('#body').val(),
                user_ids: allUserIds
            };
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
                } else {
                    alert(response.data.message);
                }
            }).fail(function() {
                alert('An error occurred. Please try again.');
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
                    // If async (queued), start progress tracking instead of alert
                    if (response.data.queued && response.data.job_id) {
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            if ($('#drafts-table-body tr').length === 0) {
                                window.location.reload();
                            }
                        });
                        QueueProgressHandler.start(response.data.job_id);
                    } else {
                        alert(response.data.message);
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            if ($('#drafts-table-body tr').length === 0) {
                                window.location.reload();
                            }
                        });
                    }
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
     * Infinite Scroll Handler for the Recipients list.
     *
     * Uses a scroll event listener on the window to detect when the
     * sentinel element is near the viewport bottom, then fetches the
     * next batch of users via AJAX and appends them.
     *
     * IntersectionObserver was avoided because it does not reliably
     * fire when the sentinel is already visible on page load (no
     * "crossing" event occurs), and behaves inconsistently inside
     * overflow containers with max-height.
     */
    const InfiniteScrollHandler = {

        observer:     null,
        paused:       false,
        scrollBound:  null,  // reference to bound scroll handler for removal

        init: function() {
            const $list = $('#recipients-list');
            if (!$list.length) {
                return;
            }


            if (parseInt($list.data('has-more')) !== 1) {
                return;
            }

            this.attachScrollListener();

            // Trigger immediately in case sentinel is already visible
            // (e.g. when the initial batch is shorter than the viewport)
            this.checkSentinel();
        },

        /**
         * Attach scroll listener to both window and the list container.
         * The list has overflow-y: auto so it has its own scroll context.
         */
        attachScrollListener: function() {
            this.detachScrollListener();

            this.scrollBound = this.onScroll.bind(this);
            window.addEventListener('scroll', this.scrollBound, { passive: true });

            // Also listen to scroll inside the list container itself
            const listEl = document.getElementById('recipients-list');
            if (listEl) {
                listEl.addEventListener('scroll', this.scrollBound, { passive: true });
            } else {
            }
        },

        /**
         * Remove scroll listeners from window and list container.
         */
        detachScrollListener: function() {
            if (this.scrollBound) {
                window.removeEventListener('scroll', this.scrollBound);
                const listEl = document.getElementById('recipients-list');
                if (listEl) {
                    listEl.removeEventListener('scroll', this.scrollBound);
                }
                this.scrollBound = null;
            }
        },

        /**
         * Scroll handler — check if sentinel is near the bottom of its scroll container.
         */
        onScroll: function() {
            if (this.paused) return;
            this.checkSentinel();
        },

        /**
         * Check if the sentinel element is within 100px of the bottom of
         * its scroll container (the list div with overflow-y: auto).
         */
        checkSentinel: function() {
            const sentinel = document.getElementById('recipients-scroll-sentinel');
            if (!sentinel) {
                this.detachScrollListener();
                return;
            }

            const $list = $('#recipients-list');
            if (parseInt($list.data('has-more')) !== 1) {
                this.detachScrollListener();
                return;
            }

            const listEl         = document.getElementById('recipients-list');
            const scrollBottom    = listEl.scrollTop + listEl.clientHeight;
            const scrollHeight    = listEl.scrollHeight;
            const distanceToBottom = scrollHeight - scrollBottom;

            // Trigger when within 100px of the bottom of the scrollable container
            if (distanceToBottom <= 100) {
                this.loadNextBatch();
            }
        },

        /**
         * Pause infinite scroll (e.g. during search mode).
         */
        pause: function() {
            this.paused = true;
        },

        /**
         * Resume infinite scroll after search is cleared.
         */
        resume: function() {
            this.paused = false;
            this.attachScrollListener();
            // Check immediately in case sentinel is already visible
            this.checkSentinel();
        },

        /**
         * Fetch the next batch of users and append to the list.
         */
        loadNextBatch: function() {
            const $list = $('#recipients-list');

            // Guard: already loading
            if ($list.data('loading') === '1') {
                return;
            }
            // Guard: no more items
            if (parseInt($list.data('has-more')) !== 1) {
                return;
            }

            const offset = parseInt($list.data('offset')) || 0;

            // Mark as loading
            $list.data('loading', '1');
            $('#recipients-loading').show();

            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_load_recipients',
                nonce:  penalisAdmin.nonces.loadRecipients,
                offset: offset
            }, function(response) {
                $('#recipients-loading').hide();
                $list.data('loading', '0');

                if (!response.success || !response.data.html) {
                    InfiniteScrollHandler.markComplete();
                    return;
                }

                const $sentinel  = $('#recipients-scroll-sentinel');

                // Append new items before the sentinel
                $(response.data.html).insertBefore($sentinel);

                // Re-apply checked state for newly appended users
                ComposeEmailHandler.restoreCheckedState();
                ComposeEmailHandler.updateSelectedCount();

                // Update offset
                const newOffset = offset + response.data.count;
                $list.data('offset', newOffset);

                if (!response.data.has_more) {
                    InfiniteScrollHandler.markComplete();
                } else {
                    // Check again immediately — new items may still leave
                    // sentinel visible in the viewport
                    setTimeout(function() {
                        InfiniteScrollHandler.checkSentinel();
                    }, 100);
                }
            }).fail(function() {
                $('#recipients-loading').hide();
                $list.data('loading', '0');
            });
        },

        /**
         * All users loaded — remove sentinel, detach scroll listener.
         */
        markComplete: function() {
            const $list = $('#recipients-list');
            $list.data('has-more', 0);

            this.detachScrollListener();

            $('#recipients-scroll-sentinel').remove();
            $('#recipients-loading').remove();
        }
    };

    /**
     * Queue Monitor Page Handler
     *
     * Handles Cancel and Refresh actions on the Queue Monitor admin page.
     */
    const QueueMonitorHandler = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.penalis-cancel-job',  this.cancelJob.bind(this));
            $(document).on('click', '.penalis-refresh-job', this.refreshJob.bind(this));
        },

        cancelJob: function(e) {
            e.preventDefault();

            const button = $(e.currentTarget);
            const jobId  = button.data('job-id');
            const nonce  = button.data('nonce');

            if (!confirm('Cancel this job? Emails already sent will not be recalled.')) {
                return;
            }

            button.prop('disabled', true).text('Cancelling...');

            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_cancel_job',
                nonce:  nonce,
                job_id: jobId
            }, function(response) {
                if (response.success) {
                    // Remove the row from the active jobs table
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        // If table is now empty, reload to show "no active jobs"
                        if ($('.penalis-jobs-table tbody tr').length === 0) {
                            window.location.reload();
                        }
                    });
                } else {
                    alert(response.data.message || 'Failed to cancel job.');
                    button.prop('disabled', false).text('Cancel');
                }
            }).fail(function() {
                alert('An error occurred. Please try again.');
                button.prop('disabled', false).text('Cancel');
            });
        },

        refreshJob: function(e) {
            e.preventDefault();

            const button = $(e.currentTarget);
            const jobId  = button.data('job-id');
            const nonce  = button.data('nonce');
            const row    = button.closest('tr');

            button.prop('disabled', true).text('Refreshing...');

            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_get_queue_status',
                nonce:  nonce,
                job_id: jobId
            }, function(response) {
                button.prop('disabled', false).text('Refresh');

                if (!response.success) return;

                const d       = response.data;
                const total   = d.total   || 1;
                const sent    = d.sent    || 0;
                const pending = (d.pending || 0) + (d.processing || 0) + (d.failed || 0);
                const failed  = d.permanently_failed || 0;
                const pct     = Math.round((sent / total) * 100);

                // Update progress bar
                row.find('.penalis-progress-bar').css('width', pct + '%');
                row.find('.penalis-progress-pct').text(pct + '%');

                // Update sent cell (3rd td)
                row.find('td:nth-child(3)').html('<strong>' + sent + '</strong> / ' + total);

                // Update pending cell (4th td)
                row.find('td:nth-child(4)').text(pending);

                // Update failed cell (5th td)
                if (failed > 0) {
                    row.find('td:nth-child(5)').html('<span class="penalis-badge penalis-badge--red">' + failed + '</span>');
                } else {
                    row.find('td:nth-child(5)').html('<span class="penalis-muted">—</span>');
                }

                // If job completed, reload page to move it to "recently completed"
                if (d.overall === 'completed') {
                    setTimeout(function() { window.location.reload(); }, 1000);
                }
            }).fail(function() {
                button.prop('disabled', false).text('Refresh');
            });
        }
    };

    /**
     * Queue Progress Handler
     *
     * Polls the server for job status after emails are queued,
     * and renders a live progress bar in the admin notice area.
     *
     * Activated when:
     *   1. The page URL contains ?penalis_job_id=<id>  (after form submit redirect)
     *   2. JS receives a queued=true response from send_draft_ajax
     */
    const QueueProgressHandler = {

        jobId:        null,
        pollTimer:    null,
        pollInterval: 5000,   // ms between polls
        maxPolls:     120,    // stop after 10 minutes (120 × 5s) to avoid infinite loops
        pollCount:    0,

        /**
         * Start tracking a job.
         *
         * @param {string} jobId
         */
        start: function(jobId) {
            if (!jobId) return;

            this.jobId     = jobId;
            this.pollCount = 0;

            this.renderBanner();
            this.poll();
        },

        /**
         * Render the progress banner above the page content.
         */
        renderBanner: function() {
            // Remove any existing banner first
            $('#penalis-queue-banner').remove();

            const banner = $(
                '<div id="penalis-queue-banner" class="penalis-queue-banner">' +
                    '<div class="penalis-queue-banner-header">' +
                        '<span class="dashicons dashicons-email-alt penalis-queue-banner-icon"></span>' +
                        '<span class="penalis-queue-banner-title">' +
                            (penalisAdmin.i18n.queueProcessing || 'Sending in progress...') +
                        '</span>' +
                        '<span class="penalis-queue-banner-counts"></span>' +
                    '</div>' +
                    '<div class="penalis-progress-track">' +
                        '<div class="penalis-progress-bar" style="width:0%"></div>' +
                    '</div>' +
                    '<div class="penalis-queue-banner-meta"></div>' +
                '</div>'
            );

            // Insert after the <h1> on the page, or at the top of .wrap
            const $h1 = $('.wrap > h1').first();
            if ($h1.length) {
                $h1.after(banner);
            } else {
                $('.wrap').prepend(banner);
            }
        },

        /**
         * Poll the server for job status.
         */
        poll: function() {
            if (!this.jobId) return;

            this.pollCount++;

            if (this.pollCount > this.maxPolls) {
                this.stopPolling();
                this.updateBanner({
                    overall: 'timeout',
                    sent: 0, total: 0, pending: 0, failed: 0, permanently_failed: 0
                });
                return;
            }

            const self = this;

            $.post(penalisAdmin.ajaxUrl, {
                action: 'penalis_get_queue_status',
                nonce:  penalisAdmin.nonces.getQueueStatus,
                job_id: this.jobId
            }, function(response) {
                if (response.success) {
                    self.updateBanner(response.data);

                    if (response.data.overall === 'completed') {
                        self.stopPolling();
                    } else {
                        self.pollTimer = setTimeout(function() {
                            self.poll();
                        }, self.pollInterval);
                    }
                } else {
                    // Server error — retry after a longer delay
                    self.pollTimer = setTimeout(function() {
                        self.poll();
                    }, self.pollInterval * 2);
                }
            }).fail(function() {
                // Network error — retry
                self.pollTimer = setTimeout(function() {
                    self.poll();
                }, self.pollInterval * 2);
            });
        },

        /**
         * Update the progress banner with latest data.
         *
         * @param {Object} data  Job summary from server
         */
        updateBanner: function(data) {
            const $banner = $('#penalis-queue-banner');
            if (!$banner.length) return;

            const total     = data.total     || 0;
            const sent      = data.sent      || 0;
            const failed    = (data.permanently_failed || 0);
            const pending   = (data.pending  || 0) + (data.processing || 0) + (data.failed || 0);
            const done      = sent + failed;
            const pct       = total > 0 ? Math.round((done / total) * 100) : 0;
            const overall   = data.overall   || 'in_progress';

            // Update progress bar width
            $banner.find('.penalis-progress-bar').css('width', pct + '%');

            // Update counts text
            const countsText = sent + ' / ' + total + ' sent';
            $banner.find('.penalis-queue-banner-counts').text(countsText);

            // Update meta line
            let metaText = '';
            if (pending > 0) {
                metaText = pending + ' remaining...';
            }
            if (failed > 0) {
                metaText += (metaText ? '  ·  ' : '') + failed + ' permanently failed';
            }
            $banner.find('.penalis-queue-banner-meta').text(metaText);

            if (overall === 'completed') {
                $banner.addClass('penalis-queue-banner--done');
                $banner.find('.penalis-queue-banner-title').text(
                    penalisAdmin.i18n.queueCompleted || 'All emails sent successfully.'
                );
                $banner.find('.penalis-progress-bar').css('width', '100%');

                // Auto-dismiss after 8 seconds
                setTimeout(function() {
                    $banner.fadeOut(600, function() { $(this).remove(); });
                }, 8000);

            } else if (overall === 'timeout') {
                $banner.addClass('penalis-queue-banner--warning');
                $banner.find('.penalis-queue-banner-title').text(
                    'Progress tracking timed out. Emails are still being sent in the background.'
                );
            }
        },

        /**
         * Stop the polling timer.
         */
        stopPolling: function() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },

        /**
         * Check URL for a job_id parameter and auto-start if found.
         * Called on page load.
         */
        checkUrlForJob: function() {
            const params = new URLSearchParams(window.location.search);
            const jobId  = params.get('penalis_job_id');
            if (jobId) {
                this.start(jobId);
            }
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
        
        if ($('#drafts-table-body').length) {
            DraftManagementHandler.init();
        }

        // Queue monitor page
        if ($('.penalis-queue-monitor').length) {
            QueueMonitorHandler.init();
        }

        // Auto-start queue progress tracking if job_id is in URL
        QueueProgressHandler.checkUrlForJob();
    });
    
})(jQuery);
