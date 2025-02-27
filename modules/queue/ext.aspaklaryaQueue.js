(function() {
    'use strict';

    $(document).ready(function() {
        $('.aspaklarya-action-remove').on('click', function() {
            const id = $(this).data('id');
            handleRemove(id);
        });

        $('.aspaklarya-action-approve').on('click', function() {
            const id = $(this).data('id');
            handleApprove(id);
        });

        $('.aspaklarya-action-edited').on('click', function() {
            const id = $(this).data('id');
            handleEdited(id);
        });
    });

    function handleRemove(id) {
        const api = new mw.Api();
        
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: 'remove',
            id: id
        }).done(function(response) {
            if (response.success) {
                $(`[data-id="${id}"]`).slideUp();
                
                api.postWithToken('csrf', {
                    action: 'echomarkread',
                    list: [response.notification]
                });
            }
        });
    }

    function handleApprove(id) {
        const api = new mw.Api();
        
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: 'approve',
            id: id
        }).done(function(response) {
            if (response.success) {
                $(`[data-id="${id}"]`).slideUp();
                
                api.postWithToken('csrf', {
                    action: 'echomarkread',
                    list: [response.notification]
                });
            }
        });
    }

    function handleEdited(id) {
        const api = new mw.Api();
        
        api.postWithToken('csrf', {
            action: 'aspaklaryareview',
            do: 'edited',
            id: id
        }).done(function(response) {
            if (response.success) {
                $(`[data-id="${id}"]`).slideUp();
                
                api.postWithToken('csrf', {
                    action: 'echomarkread',
                    list: [response.notification]
                });
            }
        });
    }
})();