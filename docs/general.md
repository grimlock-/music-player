## General idea
The idea driving the design of this app is to have, in addition to some parts of the media files themselves, a collection of text files act as the authoratative source for a lot of the metadata. The main cause for this is that I wanted to be able to view a full timeline of when all pieces of music were added to my library, but I wanted to be able to manually set the import date 

## Bullet points
Album directories are designated by the presence of a ``album.nfo`` file. For nfo file syntax see ``nfo_files.md``

Each directory containing an ``album.nfo`` file is considered a unique album (filename is configurable)

Album directories cannot be nested (e.g. separate dir for each disc)

Albums are required to have a name and a release date
