music-player
=============
My opinionated HTML music player that organizes content based on manually edited .nfo files

## Features
Gapless playback

Current browsing methods
  * import order
  * album list
    * can filter by configurable album types like ``studio album`` or ``compilation``
  * random song/album selection

User-defined tagging for songs and albums

Lots of configuration options

## General Notes
This application is _NOT_ secure

There are no user accounts. Preferences, config options, playlist entries, etc. will be saved client-side using the Local Storage API

Transcoding is not supported. Files are sent to clients as-is

Directories (and children) containing a .nomedia file are ignored

You will need to download getID3 yourself and place the files in a 'getid3' directory alongside the files from this repository

In Windows, the library scan/import and thumbnail generation scripts must be manually executed. The scripts must be passed the directories.txt file created by library.php for the ``-f/--directories-file`` argument, which must follow the ``--`` argument so the PHP binary won't treat it as its own argument

## Dependencies:
  * php-mysql
  * php-gd
    * Package name might have version info like ``php7.0-gd``
  * php-zip
  * [getID3](http://getid3.sourceforge.net/)
    * When looking at track metadata, only the first element in each array is analyzed
  * mysql
    * Only tested with MariaDB v15.1, might not work with others

## Installation and Setup
  * Set up database accounts and permissions
  * Open ``install.html`` in browser and fill out information
    * To use the ``reinstall`` option the DB user account must have DROP and CREATE permissions
  * After being redirected to ``library.php``, add all directories containing media you want to import
  * Begin import by clicking the ``Import`` button
    * In Windows you must execute the script manually
  * When the buttons are re-enabled click the ``Generate Thumbnails`` button
    * In Windows you must execute the script manually
