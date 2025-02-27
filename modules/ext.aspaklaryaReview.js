(function() {
    'use strict';

    mw.hook('wikipage.content').add(function() {
        if (!mw.config.get('wgIsArticle')) {
            return;
        }

        const portletLink = mw.util.addPortletLink(
            'p-tb',
            '#',
            mw.msg('aspaklarya-review-button'),
            't-aspaklarya-review',
            mw.msg('aspaklarya-review-tooltip')
        );

        if (portletLink) {
            $(portletLink).on('click', function(e) {
                e.preventDefault();
                showReviewDialog();
            });
        }
    });

    function showReviewDialog() {
        const images = [];
        $('img').each(function() {
            const $img = $(this);
            const filename = $img.attr('src').split('/').pop();
            
            images.push({
                filename: filename,
                element: $img,
                selected: false
            });
        });

        const dialog = new OO.ui.MessageDialog({
            size: 'large'
        });

        const imageCheckboxes = images.map(image => {
            return new OO.ui.CheckboxInputWidget({
                selected: false,
                label: image.filename
            });
        });

        const submitButton = new OO.ui.ButtonWidget({
            label: mw.msg('aspaklarya-review-submit'),
            flags: ['primary', 'progressive']
        });

        submitButton.on('click', function() {
            const selectedImages = images.filter((img, index) => 
                imageCheckboxes[index].isSelected()
            );
            
            submitForReview(selectedImages);
            dialog.close();
        });

        const windowManager = new OO.ui.WindowManager();
        $('body').append(windowManager.$element);
        windowManager.addWindows([dialog]);
        
        windowManager.openWindow(dialog, {
            title: mw.msg('aspaklarya-review-title'),
            message: $('<div>')
                .append(imageCheckboxes.map(checkbox => checkbox.$element))
                .append(submitButton.$element)
        });
    }

    function submitForReview(images) {
        const api = new mw.Api();
        
        images.forEach(image => {
            api.postWithToken('csrf', {
                action: 'aspaklaryareview',
                filename: image.filename,
                pageid: mw.config.get('wgArticleId')
            }).done(function() {
                image.element.addClass('aspaklarya-hidden');
                
                mw.notify(mw.msg('aspaklarya-review-success'));
            });
        });
    }
})();