# fake-fal
Create missing files on the fly for testing/development

# What does it do?

Instead of keeping gigabytes of files in sync with your test/development system, the extension creates useful fake files.
It acts like a local file driver and creates missing files with the correct file signature (and in case of images in the original file's dimensions), so PHP's finfo (and others) return the correct mime type.

You can let extension create fake-files step by step when needed (when calling a page via frontend) or create fake files for all files which are physically not available on the fly - via command (backend -> scheduler or CLI)

# Installation

via Composer:

```
composer require "plan2net/fake-fal" --dev
```

Activate the extension in the Extension Manager.

Change the type of your local storages.
Either via Backend->Scheduler or via CLI:

```
fakestorage:setfakemode
```
will set all storages to LocalFake mode

```
fakestorage:setfakemode 2,14,99
```
will set provided storages (eg 2, 14 and 99) to LocalFake mode

# Features
Use Backend-Scheduler or CLI to make any changes.
Arguments required only for *fakestorage:createfakesforpath* command. You can provide any other command with commaseparated list of storages (UIDs) to process selected storages. If no arguments provided, all storages, matching criteria, well be processed.

##Available Commands:

```
fakestorage:setfakemode
```
set given storage(s) to LocalFake-mode: set driver-type to "LocalFake", backup information about original driver-type, clear processed files
```
fakestorage:setnormalmode
```
set given storage(s) back to normal mode: restore driver-type, delete backup information about original driver-type, clear processed files, delete created fake files (optionally: keep fake files)

```
fakestorage:createfakes
```
create fake files within given storage(s); the existing real files will be kept

```
fakestorage:createfakesforpath
```
create fake files within given storage + given path; the existing real files will be kept

```
fakestorage:deleteprocessedfiles
```
if you have any issues corresponding to processed files when you using extension fake_fal, use this command; the processed files and the records in *sys_file_processedfiles* for given storage(s) will be deleted

```
fakestorage:deletefakes
```
secure remove the created fake files from given (LocalFake) storage(s); only fake files well be deleted, the real files and the records in *sys_file* will be kept



# TODO

There are still a few corners and edges to iron out,
but currently works (tested) with TYPO3 CMS 6.2, 7.6 and 8.7
(and possibly 9/master).

