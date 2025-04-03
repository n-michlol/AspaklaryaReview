# AspaklaryaReview

A MediaWiki extension for reviewing and managing images on Hamichlol.

## Overview

AspaklaryaReview allows users to submit images for review by "Mehashrei Tmunot". Images submitted for review are blurred on the page until approved. Moderators can approve, remove, or mark images as edited through a dedicated queue interface.

## Features

- Image submission for review from any wiki page
- Special page (Special:AspaklaryaQueue) for reviewing submitted images
- Notifications for review decisions (approved, removed, edited)
- Permission-based access control with logging
- Support for Hebrew and English interfaces
- Automatic image removal from articles and file page redirection
- Previous review status display and re-review confirmation

## Requirements

- MediaWiki 1.39.0 or later
- Echo extension (for notifications)
- PHP 7.4 or later

## Installation

1. Download and place the extension in your MediaWiki extensions directory
2. Add the following to your LocalSettings.php:

```php
wfLoadExtension( 'AspaklaryaReview' );
```

3. Run the MediaWiki update script to create the necessary database tables:

```
php maintenance/update.php
```


## Usage

### For Users

1. When viewing a page with images, click on the "Review Images" link in the sidebar tools menu
2. Select images that need review
3. Click "Submit" to send them for review
4. Images submitted for review will be blurred on the page

### For "Mehashrei Tmunot"

1. Navigate to the Special:AspaklaryaQueue page
2. Review images in the queue
3. Choose to:
   - Approve: Mark the image as acceptable
   - Remove: Delete the image and replace references to it
   - Edited: Mark the image as having been modified

## Permissions

The extension defines the following permission:

- `aspaklarya-review`: Allows reviewing and acting on queued images (default: 'aspaklarya2' group)
- `aspaklarya-review-log`: Allows viewing the Aspaklarya review log (default: 'aspaklarya2' and 'sysop' groups)