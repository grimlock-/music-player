## Media file metadata
The ``albumartist`` field in songs and videos is used as a fallback in case ``artist`` is empty, it is otherwise unused

The ``album`` field in songs and videos is ignored

For all files in an artist directory, the ``artist`` field is ignored

Prioritises a ``tags`` metadata field for song tags. Fallbacks are ``comment`` and ``description`` in that order, but only if they start with the string ``tags:``

Tags must be separated by the vertical bar character ``|``

Song and video import dates are determined in this order
  * import_date tag in file metadata
  * import_date field in album.nfo (if present)
  * last file modification date if option was enabled
  * current date

Additionaly, the actual date a song/video is imported is recorded to the ``true_import_date`` field

Import and release dates must be in one of these three formats: YYYY, YYYY-MM, YYYY-MM-DD. Dates of the first two formats will have the missing info filled with zeros while non-conforming dates will be overwritten with 0000-00-00
  * In MySQL, the sql_mode flags ``NO_ZERO_IN_DATE`` and ``NO_ZERO_DATE`` must be removed or else any item with an incomplete date will not be added to the databse (WAMP had these flags enabled by default for me). Other database systems may have similar variables.
