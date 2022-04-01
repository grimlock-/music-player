The syntax is simply key value pairs separated by an equals sign (=). Keys, like favorite, that need no value can omit the equals sign. Whitespace will be ignored.

## Artist
``name``/``names``
  * Specifies artist name and aliases
  * Entries must be separated by the vertical bar character ``|``
  * First entry is primary artist name, subsequent entries are aliases

``country``/``countries``
  * Specifies artist country of origin
  * Entries must be separated by the vertical bar character ``|``

``location``/``locations``
  * Specifies other areas of interest related to the band (city, state, county, province, prefecture, etc.)

``description``
  * Artist description. Allows hyperlinks with link text in square brackets immediately followed by the link in parenthesis

``favorite``
  * Adds artist to favorites

``link``
  * External link to be displayed on artist page

``*_id``
  * IDs for other platforms (musicbrainz, discogs, etc.)
  * Displayed before general links

## Multiple Artists
``artists.nfo`` files specify details for multiple artists in a single file, primarily intented for artists I don't want to create an individual nfo file for since I only have a handful of songs from them and don't want the songs to show up as "no artist"

All artist fields are allowed

Each ``name`` field signifies the start of a new artist that all other following fields will be associated with

## Album
``name``/``names``
  * Specifies album name and aliases
  * Entries must be separated by a semi-colon
  * First entry is primary album name, subsequent entries are aliases

``comment``/``description``
  * Allows hyperlinks like artist file

``release_date``

``remaster_date``

``import_date``

``type``
  * String matching one of the strings specified during installation

``tags``
  * Tags are key value pairs seperated by vertical bar ``|``

``link``
  * External link to be displayed on album page

``*_id``
  * IDs for other platforms
  * Displayed before general links

``*_release_id``
  * ID for album version
  * Displayed before general links
