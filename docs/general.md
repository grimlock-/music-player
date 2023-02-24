## General idea
The idea driving the design of this app is to have, in addition to some parts of the media files themselves, a collection of text files act as the authoratative source for a lot of the metadata. The main cause for this is that I wanted to be able to view a full timeline of when all pieces of music were added to my library, but I wanted to be able to manually set the import date. In addition though, binding albums to a single directory elminiates the ambiguity that trips up some other systems with albums that have the same name.

## Bullet points
Album directories are designated by the presence of a ``album.nfo`` file. For nfo file syntax see ``nfo_files.md``

Each directory containing an ``album.nfo`` file is considered a unique album (filename is configurable)

Album directories cannot be nested (e.g. separate dir for each disc)

Albums are required to have a name and a release date

Artist directories are designated by a ``artist.nfo`` file. For songs and videos contained inside an artist directory, the artist metadata field is ignored

For songs and videos NOT inside an artist directory, the import script will attempt to resolve the specified artist names. If multiple artists have the same name, whichever artist the database puts first in the result set is chosen

The artist metadata field can contain multiple artists delimited by the vertical bar character ``|``
