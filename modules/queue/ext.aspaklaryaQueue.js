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
            var fileUrl = $this.data('file-url');
            
            window.open(fileUrl, '_blank');
            return false;
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
            showMessage(mw.msg('aspaklarya-queue-file-blocked'), 'success');
            
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