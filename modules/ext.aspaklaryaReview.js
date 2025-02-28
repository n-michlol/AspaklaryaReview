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
            let src = $img.attr('src');
            let match = src.match(/\/([^\/]+\.(jpg|jpeg|png|gif|svg))(?:\?|$)/i);
            
            if (!match) {
                return;
            }
            
            let filename = decodeURIComponent(match[1]);
            filename = filename.replace(/^\d+px-/, '');
            
            images.push({
                filename: filename,
                element: $img,
                src: src
            });
        });

        if (images.length === 0) {
            mw.notify('No images found on this page.');
            return;
        }

        const dialogContent = $('<div class="aspaklarya-review-dialog"></div>');
        const imagesContainer = $('<div class="aspaklarya-review-images"></div>');
        
        images.forEach(function(image, index) {
            const imageItem = $('<div class="aspaklarya-review-image-item"></div>');
            imageItem.append($('<img>').attr('src', image.src).attr('alt', image.filename));
            
            const checkbox = new OO.ui.CheckboxInputWidget({
                selected: false,
                value: index
            });
            
            imageItem.append($('<div></div>').append(
                $('<label></label>').text(image.filename).prepend(checkbox.$element)
            ));
            
            imagesContainer.append(imageItem);
            image.checkbox = checkbox;
        });
        
        dialogContent.append(imagesContainer);

        const submitButton = new OO.ui.ButtonWidget({
            label: mw.msg('aspaklarya-review-submit'),
            flags: ['primary', 'progressive']
        });

        const cancelButton = new OO.ui.ButtonWidget({
            label: mw.msg('aspaklarya-review-cancel'),
            flags: ['destructive']
        });

        const buttonsContainer = $('<div class="aspaklarya-review-buttons"></div>')
            .append(submitButton.$element)
            .append(cancelButton.$element);
        
        dialogContent.append(buttonsContainer);

        const dialog = new OO.ui.MessageDialog({
            size: 'large'
        });

        const windowManager = new OO.ui.WindowManager();
        $('body').append(windowManager.$element);
        windowManager.addWindows([dialog]);
        
        windowManager.openWindow(dialog, {
            title: mw.msg('aspaklarya-review-title'),
            message: dialogContent
        });

        submitButton.on('click', function() {
            const selectedImages = images.filter(img => img.checkbox.isSelected());
            
            if (selectedImages.length === 0) {
                mw.notify('No images selected.');
                return;
            }
            
            submitForReview(selectedImages);
            dialog.close();
        });

        cancelButton.on('click', function() {
            dialog.close();
        });
    }

    function submitForReview(images) {
        const api = new mw.Api();
        let successCount = 0;
        
        images.forEach(image => {
            api.postWithToken('csrf', {
                action: 'aspaklaryareview',
                do: 'submit',
                filename: image.filename,
                pageid: mw.config.get('wgArticleId')
            }).done(function(response) {
                if (response.success) {
                    const $parent = image.element.parent();
                    if (!$parent.hasClass('aspaklarya-image-wrapper')) {
                        image.element.wrap('<div class="aspaklarya-image-wrapper"></div>');
                    }
                    
                    image.element.parent().addClass('aspaklarya-hidden');
                    successCount++;
                    
                    if (successCount === images.length) {
                        mw.notify(mw.msg('aspaklarya-review-success'), {type: 'success'});
                    }
                }
            }).fail(function(code, data) {
                console.error('Error submitting image:', code, data);
                if (data && data.exception) {
                    mw.notify('Error submitting image: ' + data.exception, {type: 'error'});
                } else {
                    mw.notify('Error submitting image: ' + image.filename, {type: 'error'});
                }
            });
        });
    }
})();