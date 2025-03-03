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
        
        checkSubmittedImages();
    });
    
    function checkSubmittedImages() {
        const api = new mw.Api();
        const pageId = mw.config.get('wgArticleId');
        
        const filenames = [];
        const imageElements = {};
        
        $('img').each(function() {
            const $img = $(this);
            let src = $img.attr('src');
            let match = src.match(/\/([^\/]+\.(jpg|jpeg|png|gif|svg))(?:\?|$)/i);
            
            if (!match) {
                return;
            }
            
            let filename = decodeURIComponent(match[1]);
            filename = filename.replace(/^\d+px-/, '');
            
            filenames.push(filename);
            
            if (!imageElements[filename]) {
                imageElements[filename] = [];
            }
            imageElements[filename].push($img);
        });
        
        if (filenames.length === 0) {
            return;
        }
        
        api.get({
            action: 'query',
            list: 'aspaklaryareview',
            arqpageid: pageId
        }).done(function(response) {
            if (response.query && response.query.aspaklaryareview && 
                response.query.aspaklaryareview.length > 0) {
                
                const pendingImages = new Set();
                response.query.aspaklaryareview.forEach(function(item) {
                    pendingImages.add(item.filename);
                });
                
                const hasReviewPermission = mw.config.get('wgUserGroups', []).includes('aspaklarya2');
                
                Object.keys(imageElements).forEach(function(filename) {
                    if (pendingImages.has(filename)) {
                        imageElements[filename].forEach(function($img) {
                            const $parent = $img.parent();
                            if (!$parent.hasClass('aspaklarya-image-wrapper')) {
                                $img.wrap('<div class="aspaklarya-image-wrapper"></div>');
                            }
                            
                            if (!hasReviewPermission) {
                                $img.parent().addClass('aspaklarya-hidden');
                            }
                        });
                    }
                });
            }
        }).fail(function(error) {
            console.error('Failed to check submitted images:', error);
        });
    }

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
            
            if (!images.some(img => img.filename === filename)) {
                images.push({
                    filename: filename,
                    element: $img,
                    src: src
                });
            }
        });

        if (images.length === 0) {
            if (mw.notify) {
                mw.notify(mw.msg('aspaklarya-review-no-images'));
            } else {
                alert(mw.msg('aspaklarya-review-no-images'));
            }
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
            size: 'larger'
        });

        const windowManager = new OO.ui.WindowManager();
        $('body').append(windowManager.$element);
        windowManager.addWindows([dialog]);
        
        windowManager.openWindow(dialog, {
            title: mw.msg('aspaklarya-review-title'),
            message: dialogContent,
            actions: [] 
        });

        submitButton.on('click', function() {
            const selectedImages = images.filter(img => img.checkbox.isSelected());
            
            if (selectedImages.length === 0) {
                if (mw.notify) {
                    mw.notify(mw.msg('aspaklarya-review-no-selection'));
                } else {
                    alert(mw.msg('aspaklarya-review-no-selection'));
                }
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
        let errorCount = 0;
        
        const notificationSystem = mw.notification || {
            notify: function(message, options) {
                const notifier = { close: function() { /* nothing to do */ } };
                if (options && options.type === 'error') {
                    console.error(message);
                    alert(message);
                }
                return notifier;
            }
        };
        
        const notification = notificationSystem.notify(mw.msg('aspaklarya-review-submitting'), {autoHide: false});
        
        const promises = images.map(image => {
            return api.postWithToken('csrf', {
                action: 'aspaklaryareview',
                do: 'submit',
                filename: image.filename,
                pageid: mw.config.get('wgArticleId')
            }).then(function(response) {
                if (response.success) {
                    const $parent = image.element.parent();
                    if (!$parent.hasClass('aspaklarya-image-wrapper')) {
                        image.element.wrap('<div class="aspaklarya-image-wrapper"></div>');
                    }
                    
                    const hasReviewPermission = mw.config.get('wgUserGroups', []).includes('aspaklarya2');
                    if (!hasReviewPermission) {
                        image.element.parent().addClass('aspaklarya-hidden');
                    }
                    
                    successCount++;
                }
                return response;
            }).catch(function(code, data) {
                console.error('Error submitting image:', code, data);
                errorCount++;
                if (mw.notify) {
                    if (data && data.exception) {
                        mw.notify(mw.msg('aspaklarya-review-error', data.exception), {type: 'error'});
                    } else {
                        mw.notify(mw.msg('aspaklarya-review-error', image.filename), {type: 'error'});
                    }
                } else {
                    if (data && data.exception) {
                        alert(mw.msg('aspaklarya-review-error', data.exception));
                    } else {
                        alert(mw.msg('aspaklarya-review-error', image.filename));
                    }
                }
                return null;
            });
        });
        
        $.when.apply($, promises).always(function() {
            notification.close();
            
            if (successCount > 0) {
                if (mw.notify) {
                    mw.notify(mw.msg('aspaklarya-review-success'), {type: 'success'});
                } else {
                    alert(mw.msg('aspaklarya-review-success'));
                }
            }
            
            if (errorCount > 0) {
                if (mw.notify) {
                    mw.notify(mw.msg('aspaklarya-review-partial-error'), {type: 'error'});
                } else {
                    alert(mw.msg('aspaklarya-review-partial-error'));
                }
            }
        });
    }
})();