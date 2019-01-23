# plan2net/fake-fal

Create missing files on the fly for testing/development.

# What does it do?

Instead of keeping gigabytes of files in sync with your test/development system, the extension creates useful fake files.
It acts like a local file driver and creates missing files with the correct file signature (and in case of images in the original file's dimensions) and folders, so PHP's finfo (and others) return the correct mime type.

You can let the extension create fake files on the fly when used (when visiting a page in the browser) or create fake files for all files which are not available on disk at once via a command (Backend > Scheduler or command line).

# Installation

Composer:

    composer require "plan2net/fake-fal" --dev

Activate the extension in the Extension Manager.
You can deactivate writing image dimensions on the fake images here (it's active by default).
 
    writeImageDimensions = 0

Activate the fake mode for your local storages.
Either via Backend (by editing the storage record) or via command line command:

    fakestorage:togglefakemode
    
will set all local storages to fake mode.

    fakestorage:togglefakemode 2,14,99
    
will set given storages (e.g. 2, 14 and 99) to fake mode.

## Available Commands:

    fakestorage:togglefakemode
    
Set given storage(s) to fake mode: check flag for fake mode, clear processed files

    fakestorage:createfakefiles
    
Create fake files within given storage(s); the existing real files will be kept

# Compatibility

The extension is tested and works with TYPO3 CMS LTS 8.7 and 9.5 and PHP > 7.1.
Support for TYPO3 CMS 6 and 7 and PHP < 7.1 has been dropped deliberatly.

# Alternatives

There's the filefill extension from Nicole Cordes.

Here's the story: I had the idea for fake_fal for quite a while and there was a Fedex Day (a day where we explore new ideas and create cool things in our company) I wanted to create this extension. The result after one day of coding was the first working version. 

This was around two weeks after Nicole published her extension. I didn't know anything about it. A week later a colleague said "Hey, I heard about an extension that sounds like yours!" At first I was dissappointed, but gladly there's quite a difference.

Our extension works without an Internet connection and creates the files locally. Additionally the file dimensions are written into the fake images by default.
And if you download a fake PDF it will behave like a real document.
