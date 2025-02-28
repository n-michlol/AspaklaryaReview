(function() {
    'use strict';

    mw.hook('wikipage.content').add(function() {
        initializeEventListeners();
    });

    function initializeEventListeners() {
        $(document).on('click', '.aspaklarya-action-remove', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).attr('data-id');
            if (!id) {
                mw.notify('Error: Cannot find item ID', {type: 'error'});
                return;
            }
            handleAction(id, 'remove');
        });

        $(document).on('click', '.aspaklarya-action-approve', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).attr('data-id');
            if (!id) {
                mw.notify('Error: Cannot find item ID', {type: 'error'});
                return;
            }
            handleAction(id, 'approve');
        });

        $(document).on('click', '.aspaklarya-action-edited', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).attr('data-id');
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
        
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: action,
            id: id
        }).done(function(response) {
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
                mw.notify('Error processing request', {type: 'error'});
            }
        }).fail(function(code, data) {
            $item.removeClass('is-loading');
            let errorMsg = 'Error';
            if (data && data.exception) {
                errorMsg += ': ' + data.exception;
            } else if (data && data.error && data.error.info) {
                errorMsg += ': ' + data.error.info;
            }
            mw.notify(errorMsg, {type: 'error'});
            console.error('API error:', code, data);
        });
    }
})();