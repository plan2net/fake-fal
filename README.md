# fake-fal
Create missing files on the fly for testing/development

# What does it do?

Instead of keeping gigabytes of files in sync with your test/development system, the extension creates useful fake files.
It acts like a local file driver and creates missing files with the correct file signature (and in case of images in the original file's dimensions), so PHP's finfo (and others) return the correct mime type.

# Installation

via Composer:

```
composer require "plan2net/fake-fal" --dev
```

Activate the extension in the Extension Manager.

Change the type of your local storages.
Either in the backend or via SQL query:

```
UPDATE sys_file_storage SET driver = 'LocalFake' WHERE driver = 'Local';
```

Clear the processed files in the Install Tool (Clean up > Clear processed files).

# TODO

There are still a few corners and edges to iron out,
but currently works (tested) with TYPO3 CMS 6.2, 7.6 and 8.7
(and possibly 9/master).

