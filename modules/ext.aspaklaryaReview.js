(function() {
    'use strict';

    mw.hook('wikipage.content').add(function() {
        if (!mw.config.get('wgIsArticle')) {
            return;
        }

        /*
        Add the module only for logged-in users - I thought it was better that way.
        If there is a different decision, it will require thinking about what to do with 
        the messages to the user who sent the image for review.
        */
        if (mw.user.isAnon()) {
            checkSubmittedImages();
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
                                
                                const $link = $img.closest('a');
                                if ($link.length) {
                                    const $wrapper = $('<div class="aspaklarya-disabled-link"></div>');
                                    $wrapper.html($link.html());
                                    $link.replaceWith($wrapper);
                                }
                                
                                $img.on('click mousedown touchstart pointerdown mouseup touchend pointerup', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                });
                                
                                $(document).on('click mousedown touchstart pointerdown mouseup touchend pointerup', '.aspaklarya-hidden img, .aspaklarya-disabled-link', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                });
                                
                                setTimeout(function() {
                                    $('.aspaklarya-hidden .mw-mmv-trigger').removeClass('mw-mmv-trigger');
                                }, 100);
                            }
                        });
                    }
                });
                
                if (!hasReviewPermission) {
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'attributes' || mutation.type === 'childList') {
                                $('.aspaklarya-hidden .mw-mmv-trigger').removeClass('mw-mmv-trigger');
                                
                                $('.aspaklarya-hidden img').each(function() {
                                    const $img = $(this);
                                    const $link = $img.closest('a:not(.aspaklarya-observed)');
                                    if ($link.length) {
                                        const $wrapper = $('<div class="aspaklarya-disabled-link"></div>');
                                        $wrapper.html($link.html());
                                        $link.replaceWith($wrapper);
                                    }
                                });
                            }
                        });
                    });
                    
                    observer.observe(document.body, { 
                        childList: true, 
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['class']
                    });
                }
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
            
            if ($img.width() < 50 || $img.height() < 50) {
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

        const checkPromises = images.map(image => {
            const filename = image.filename;

            return api.postWithToken('csrf', {
                action: 'aspaklaryareview',
                do: 'checkprevious',
                filename: filename
            }).then(function(response) {
                if (response && response.previousReview) {
                    return {
                        image: image,
                        previousReview: response.previousReview
                    };
                }
                return { image: image, previousReview: null };
            }).catch(function(error, data) {
                return { image: image, previousReview: null };
            });
        });

        Promise.all(checkPromises).then(function(results) {
            const imagesToConfirm = [];
            const imagesToSubmit = [];

            results.forEach(function(result) {
                if (result.previousReview && result.previousReview !== false) {
                    imagesToConfirm.push(result);
                } else {
                    imagesToSubmit.push(result.image);
                }
            });

            if (imagesToConfirm.length > 0) {
                confirmPreviouslyReviewed(imagesToConfirm, imagesToSubmit).then(function(allImages) {
                    if (allImages.length > 0) {
                        processSubmission(allImages);
                    }
                });
            } else if (imagesToSubmit.length > 0) {
                processSubmission(imagesToSubmit);
            }
        });

        function confirmPreviouslyReviewed(imagesToConfirm, imagesToSubmit) {
            return new Promise(function(resolve) {
                const windowManager = new OO.ui.WindowManager();
                $('body').append(windowManager.$element);
                windowManager.addWindows([new OO.ui.MessageDialog()]);
        
                const confirmedImages = [];
                let currentIndex = 0;
                
                processNextImage();
                
                function processNextImage() {
                    if (currentIndex >= imagesToConfirm.length) {
                        windowManager.$element.remove();
                        resolve(confirmedImages.concat(imagesToSubmit));
                        return;
                    }
                    
                    const currentItem = imagesToConfirm[currentIndex];
                    currentIndex++;
                    
                    if (!currentItem || !currentItem.image || !currentItem.previousReview) {
                        processNextImage();
                        return;
                    }
                    
                    const image = currentItem.image;
                    const previousReview = currentItem.previousReview;
            
                    const timestamp = previousReview.timestamp || '';
                    let formattedDate = 'unknown date';
                    if (timestamp) {
                        const date = new Date(timestamp);
                        formattedDate = date.toLocaleString(
                            mw.config.get('wgUserLanguage'),
                            {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            }
                        );
                    }
                    
                    const status = previousReview.status || 'unknown';
                    const statusMsg = mw.msg('aspaklarya-status-' + status);
                    
                    const reviewer = previousReview.reviewer || 'unknown';
                    
                    const messageContent = $('<div></div>');
                    messageContent.append(
                        $('<p></p>').text(
                            mw.msg('aspaklarya-review-previously-reviewed', 
                                image.filename, 
                                statusMsg, 
                                formattedDate
                            ) + ' ' + mw.msg('aspaklarya-review-by-reviewer', reviewer)
                        )
                    );
                    
                    const dialog = windowManager.openWindow('message', {
                        title: mw.msg('aspaklarya-review-confirm-title'),
                        message: messageContent,
                        actions: [
                            {
                                action: 'cancel',
                                label: mw.msg('aspaklarya-review-confirm-no'),
                                flags: ['safe']
                            },
                            {
                                action: 'accept',
                                label: mw.msg('aspaklarya-review-confirm-yes'),
                                flags: ['primary', 'progressive']
                            }
                        ]
                    });
                    
                    dialog.closed.then(function(data) {
                        if (data && data.action === 'accept') {
                            confirmedImages.push(image);
                        }
                        
                        processNextImage();
                    }).catch(function(error) {
                        processNextImage();
                    });
                }
            });
        }

        function processSubmission(images) {
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
                            
                            const $link = image.element.closest('a');
                            if ($link.length) {
                                const $wrapper = $('<div class="aspaklarya-disabled-link"></div>');
                                $wrapper.html($link.html());
                                $link.replaceWith($wrapper);
                            }
                        }

                        successCount++;
                    }
                    return response;
                }).catch(function(code, data) {
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
    }
})();