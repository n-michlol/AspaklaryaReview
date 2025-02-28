(function() {
    'use strict';

    mw.hook('wikipage.content').add(function() {
        $(document).on('click', '.aspaklarya-action-remove', function() {
            const id = $(this).data('id');
            handleRemove(id);
        });

        $(document).on('click', '.aspaklarya-action-approve', function() {
            const id = $(this).data('id');
            handleApprove(id);
        });

        $(document).on('click', '.aspaklarya-action-edited', function() {
            const id = $(this).data('id');
            handleEdited(id);
        });
    });

    function handleRemove(id) {
        const $item = $(`[data-id="${id}"].aspaklarya-queue-item`);
        $item.addClass('is-loading');
        
        const api = new mw.Api();
        
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: 'remove',
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
                    api.postWithToken('csrf', {
                        action: 'echomarkread',
                        list: [response.notification]
                    });
                }
            }
        }).fail(function(error) {
            $item.removeClass('is-loading');
            mw.notify('Error: ' + error, {type: 'error'});
        });
    }

    function handleApprove(id) {
        const $item = $(`[data-id="${id}"].aspaklarya-queue-item`);
        $item.addClass('is-loading');
        
        const api = new mw.Api();
        
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: 'approve',
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
                    api.postWithToken('csrf', {
                        action: 'echomarkread',
                        list: [response.notification]
                    });
                }
            }
        }).fail(function(error) {
            $item.removeClass('is-loading');
            mw.notify('Error: ' + error, {type: 'error'});
        });
    }

    function handleEdited(id) {
        const $item = $(`[data-id="${id}"].aspaklarya-queue-item`);
        $item.addClass('is-loading');
        
        const api = new mw.Api();
        
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: 'edited',
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
                    api.postWithToken('csrf', {
                        action: 'echomarkread',
                        list: [response.notification]
                    });
                }
            }
        }).fail(function(error) {
            $item.removeClass('is-loading');
            mw.notify('Error: ' + error, {type: 'error'});
        });
    }
})();