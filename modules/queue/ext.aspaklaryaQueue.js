(function() {
    'use strict';

    const pendingRequests = {};

    function initializeEventListeners() {
        $(document).on('click', '.aspaklarya-action-remove, .aspaklarya-action-approve, .aspaklarya-action-edited', function(e) {
            e.preventDefault();
            const $button = $(this);
            const id = $button.data('id') || $button.closest('[data-id]').attr('data-id');
            
            if (!id) {
                mw.notify(mw.msg('aspaklarya-queue-error-processing') + ': Cannot find item ID', {type: 'error'});
                return;
            }
            
            if (pendingRequests[id]) {
                console.log('Request already in progress for ID:', id);
                return;
            }
            
            let action;
            if ($button.hasClass('aspaklarya-action-remove')) {
                action = 'remove';
            } else if ($button.hasClass('aspaklarya-action-approve')) {
                action = 'approve';
            } else if ($button.hasClass('aspaklarya-action-edited')) {
                action = 'edited';
            }
            
            handleAction(id, action);
        });

        $(document).on('click', '.aspaklarya-queue-image-link', function(e) {
            if (e.ctrlKey || e.metaKey || e.which === 2) {
                return true;
            }

            e.preventDefault();
            var $this = $(this);
            var fileUrl = $this.data('file-url') || $this.attr('href');
            
            window.open(fileUrl, '_blank');
            return false;
        });

        $(document).on('click', '#aspaklarya-queue-nav-go', function(e) {
            e.preventDefault();
            const $form = $('#aspaklarya-queue-nav-form');
            if ($form.length) {
                $form[0].submit();
            } else {
                console.error('Navigation form not found');
                mw.notify('Error: Could not submit navigation form', {type: 'error'});
            }
        });
    }

    function handleAction(id, action) {
        console.log('Handling action:', action, 'for ID:', id);
        const $item = $(`[data-id="${id}"].aspaklarya-queue-item`);
        $item.addClass('is-loading');
        
        pendingRequests[id] = true;
        
        const api = new mw.Api();
        
        const params = {
            action: 'aspaklaryareview',
            do: action,
            id: id,
            format: 'json',
            errorformat: 'plaintext',
            errorlang: 'en',
            formatversion: 2
        };
        
        console.log('Sending API request with params:', params);
        
        api.postWithToken('csrf', params)
            .done(function(response) {
                console.log('API response:', response);
                
                delete pendingRequests[id];
                
                if (response.success) {
                    if (action === 'remove') {
                        showDiffPreview(response, id, $item);
                    } else {
                        showActionSuccessMessages(response, action);
                        
                        setTimeout(function() {
                            $item.slideUp(function() {
                                $item.remove();
                                if ($('.aspaklarya-queue-item').length === 0) {
                                    $('.aspaklarya-queue-list').html('<div class="aspaklarya-queue-empty">' + 
                                        mw.msg('aspaklarya-queue-empty') + '</div>');
                                }
                            });
                        }, 3000);
                        
                        if (response.notification) {
                            try {
                                api.postWithToken('csrf', {
                                    action: 'echomarkread',
                                    list: [response.notification]
                                });
                            } catch(e) {
                                console.error('Failed to mark notification as read', e);
                            }
                        }
                    }
                } else {
                    $item.removeClass('is-loading');
                    let errorMsg = mw.msg('aspaklarya-queue-error-processing');
                    if (response.error && response.error.info) {
                        errorMsg += ': ' + response.error.info;
                    }
                    mw.notify(errorMsg, {type: 'error'});
                }
            })
            .fail(function(code, data) {
                console.error('API error code:', code);
                console.error('API error data:', data);
                
                delete pendingRequests[id];
                
                $item.removeClass('is-loading');
                let errorMsg = mw.msg('aspaklarya-queue-error-processing');
                
                if (data && data.exception) {
                    errorMsg += ': ' + data.exception;
                } else if (data && data.error && data.error.info) {
                    errorMsg += ': ' + data.error.info;
                } else if (data && data.textStatus) {
                    errorMsg += ': ' + data.textStatus;
                }
                
                mw.notify(errorMsg, {type: 'error'});
            });
    }

    function showDiffPreview(response, queueId, $item) {
        const diffData = response.diffData || [];
        const filename = response.filename;
        const pageId = response.pageId;
        const requesterId = response.requesterId;
        const fileModified = response.fileModified;
        
        if (!diffData.length && !fileModified) {
            mw.notify(mw.msg('aspaklarya-queue-error-processing') + ': No changes to review', {type: 'error'});
            $item.removeClass('is-loading');
            return;
        }

        const dialogContent = $('<div class="aspaklarya-diff-dialog"></div>');
        const diffContainer = $('<div class="aspaklarya-diff-container"></div>');

        if (fileModified) {
            showMessage(mw.msg('aspaklarya-queue-file-blocked'), 'success');
        }

        if (diffData.length === 0) {
            confirmRemoval(queueId, [], $item, filename, []);
            return;
        }

        diffData.forEach(function(diff, index) {
            const diffItem = $('<div class="aspaklarya-diff-item"></div>');
            diffItem.append($('<h3></h3>').text(diff.title));
            
            const diffHtml = generateDiffHtml(diff.originalText, diff.newText);
            const diffDisplay = $('<div class="aspaklarya-diff-display"></div>').html(diffHtml);
            diffItem.append(diffDisplay);
            
            const textarea = $('<textarea class="aspaklarya-diff-textarea"></textarea>')
                .val(diff.newText)
                .attr('data-page-id', diff.pageId)
                .attr('data-title', diff.title);
            diffItem.append($('<label></label>').text(mw.msg('aspaklarya-queue-diff-edit-label')));
            diffItem.append(textarea);
            
            diffContainer.append(diffItem);
        });

        dialogContent.append(diffContainer);

        const confirmButton = new OO.ui.ButtonWidget({
            label: mw.msg('aspaklarya-queue-diff-confirm'),
            flags: ['primary', 'progressive']
        });

        const cancelButton = new OO.ui.ButtonWidget({
            label: mw.msg('aspaklarya-queue-diff-cancel'),
            flags: ['destructive']
        });

        const buttonsContainer = $('<div class="aspaklarya-diff-buttons"></div>')
            .append(confirmButton.$element)
            .append(cancelButton.$element);
        
        dialogContent.append(buttonsContainer);

        const dialog = new OO.ui.MessageDialog({
            size: 'larger',
            classes: ['aspaklarya-wide-dialog']
        });

        const windowManager = new OO.ui.WindowManager();
        $('body').append(windowManager.$element);
        windowManager.addWindows([dialog]);
        
        windowManager.openWindow(dialog, {
            title: mw.msg('aspaklarya-queue-diff-title'),
            message: dialogContent,
            actions: []
        });

        setTimeout(function() {
        $('.aspaklarya-wide-dialog .oo-ui-window-frame').css({
            'width': '95%',
            'max-width': '95vw'
        });
        
        $('.aspaklarya-wide-dialog .oo-ui-dialog-content, .aspaklarya-wide-dialog .oo-ui-window-body').css({
            'width': '100%',
            'max-width': '100%'
        });
        }, 50);

        confirmButton.on('click', function() {
            const edits = [];
            $('.aspaklarya-diff-textarea').each(function() {
                const $textarea = $(this);
                edits.push({
                    title: $textarea.attr('data-title'),
                    pageId: $textarea.attr('data-page-id'),
                    newText: $textarea.val()
                });
            });

            confirmRemoval(queueId, edits, $item, filename, diffData);
            dialog.close();
            windowManager.$element.remove();
        });

        cancelButton.on('click', function() {
            dialog.close();
            windowManager.$element.remove();
            $item.removeClass('is-loading');
            
            showPersistentNotification(filename, diffData);
        });
    }

    function generateDiffHtml(oldText, newText) {
        const oldLines = oldText.split('\n');
        const newLines = newText.split('\n');
        let html = '<table class="diff diff-contentalign-left"><tbody>';
        
        let i = 0, j = 0;
        while (i < oldLines.length || j < newLines.length) {
            if (i < oldLines.length && j < newLines.length && oldLines[i] === newLines[j]) {
                html += '<tr>' +
                        '<td class="diff-marker"></td>' +
                        '<td class="diff-context">' + escapeHtml(oldLines[i]) + '</td>' +
                        '</tr>';
                i++;
                j++;
            } else {
                if (i < oldLines.length) {
                    html += '<tr>' +
                            '<td class="diff-marker">-</td>' +
                            '<td class="diff-deletedline">' + escapeHtml(oldLines[i]) + '</td>' +
                            '</tr>';
                    i++;
                }
                if (j < newLines.length) {
                    html += '<tr>' +
                            '<td class="diff-marker">+</td>' +
                            '<td class="diff-addedline">' + escapeHtml(newLines[j]) + '</td>' +
                            '</tr>';
                    j++;
                }
            }
        }
        
        html += '</tbody></table>';
        return html;
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function confirmRemoval(queueId, edits, $item, filename, diffData) {
        const api = new mw.Api();
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: 'confirmRemove',
            id: queueId,
            edits: JSON.stringify(edits)
        }).done(function(response) {
            if (response.success) {
                showActionSuccessMessages(response, 'remove');
                
                setTimeout(function() {
                    $item.slideUp(function() {
                        $item.remove();
                        if ($('.aspaklarya-queue-item').length === 0) {
                            $('.aspaklarya-queue-list').html('<div class="aspaklarya-queue-empty">' + 
                                mw.msg('aspaklarya-queue-empty') + '</div>');
                        }
                    });
                }, 3000);
                
                if (response.notification) {
                    try {
                        api.postWithToken('csrf', {
                            action: 'echomarkread',
                            list: [response.notification]
                        });
                    } catch(e) {
                        console.error('Failed to mark notification as read', e);
                    }
                }
            } else {
                mw.notify(mw.msg('aspaklarya-queue-error-processing'), {type: 'error'});
                showPersistentNotification(filename, diffData);
            }
        }).fail(function(code, data) {
            let errorMsg = mw.msg('aspaklarya-queue-error-processing');
            if (data && data.error && data.error.info) {
                errorMsg += ': ' + data.error.info;
            }
            mw.notify(errorMsg, {type: 'error'});
            showPersistentNotification(filename, diffData);
        });
    }

    function showPersistentNotification(filename, diffData) {
        const pages = diffData.map(diff => diff.title);
        const message = pages.length > 0
            ? mw.msg('aspaklarya-queue-manual-remove', filename, pages.join(', '))
            : mw.msg('aspaklarya-queue-manual-remove-file', filename);
        
        const notificationContainer = $('<div class="aspaklarya-persistent-notification"></div>');
        const notificationContent = $('<div class="aspaklarya-notification-content"></div>').text(message);
        
        const confirmButton = new OO.ui.ButtonWidget({
            label: mw.msg('aspaklarya-queue-notification-confirm'),
            flags: ['primary', 'progressive']
        });
        
        notificationContainer.append(notificationContent).append(confirmButton.$element);
        
        if (!$('.aspaklarya-action-messages').length) {
            $('body').append('<div class="aspaklarya-action-messages"></div>');
        }
        
        $('.aspaklarya-action-messages').append(notificationContainer);
        
        confirmButton.on('click', function() {
            notificationContainer.fadeOut(function() {
                notificationContainer.remove();
            });
        });
    }

    function showActionSuccessMessages(response, action) {
        if (!$('.aspaklarya-action-messages').length) {
            $('body').append('<div class="aspaklarya-action-messages"></div>');
        }
        
        if (response.notification) {
            showMessage(mw.msg('aspaklarya-queue-notification-sent'), 'success');
        } else {
            showMessage(mw.msg('aspaklarya-queue-notification-error'), 'error');
        }
        
        if (action === 'remove' && response.pagesModified) {
            if (Array.isArray(response.pagesModified)) {
                response.pagesModified.forEach(function(page) {
                    showMessage(mw.msg('aspaklarya-queue-image-removed', page), 'success');
                });
            }
        }
    }

    function showMessage(message, type) {
        const $message = $('<div>')
            .addClass('aspaklarya-message')
            .addClass('aspaklarya-message-' + type)
            .text(message)
            .appendTo('.aspaklarya-action-messages');
        
        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
            });
        }, 5000);
    }

    $(function() {
        initializeEventListeners();
    });

    mw.hook('wikipage.content').add(function() {
        initializeEventListeners();
    });
})();