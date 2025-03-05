(function() {
    'use strict';

    mw.hook('wikipage.content').add(function() {
        initializeEventListeners();
    });

    function initializeEventListeners() {
        $(document).on('click', '.aspaklarya-action-remove', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).closest('[data-id]').attr('data-id');
            if (!id) {
                mw.notify('Error: Cannot find item ID', {type: 'error'});
                return;
            }
            handleAction(id, 'remove');
        });

        $(document).on('click', '.aspaklarya-action-approve', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).closest('[data-id]').attr('data-id');
            if (!id) {
                mw.notify('Error: Cannot find item ID', {type: 'error'});
                return;
            }
            handleAction(id, 'approve');
        });

        $(document).on('click', '.aspaklarya-action-edited', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).closest('[data-id]').attr('data-id');
            if (!id) {
                mw.notify('Error: Cannot find item ID', {type: 'error'});
                return;
            }
            handleAction(id, 'edited');
        });
    }

    function handleAction(id, action) {
        console.log('Handling action:', action, 'for ID:', id);
        const $item = $(`[data-id="${id}"].aspaklarya-queue-item`);
        $item.addClass('is-loading');
        
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
                
                if (response.success) {
                    $item.slideUp(function() {
                        $item.remove();
                        if ($('.aspaklarya-queue-item').length === 0) {
                            $('.aspaklarya-queue-list').html('<div class="aspaklarya-queue-empty">' + 
                                mw.msg('aspaklarya-queue-empty') + '</div>');
                        }
                    });
                    
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
                    let errorMsg = 'Error processing request';
                    if (response.error && response.error.info) {
                        errorMsg += ': ' + response.error.info;
                    }
                    mw.notify(errorMsg, {type: 'error'});
                }
            })
            .fail(function(code, data) {
                console.error('API error code:', code);
                console.error('API error data:', data);
                
                $item.removeClass('is-loading');
                let errorMsg = 'Error processing request';
                
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
})();