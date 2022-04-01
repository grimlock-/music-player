## Import Script
* This thing is a mess and can probably be drastically sped up. It also makes a shit ton of queries to the database which I'd prefer cutting down on. The act of removing songs from my library is going to be a rare occurrance, so maybe it shouldn't automatically handle that?
* Multi-artist files can generate a lot of unnecessary edits for the artists table. Restructuring the artist hashs to be based on the config text instead of the multiartist file as a whole should take care of this.

## Database
Artist and Album table entries don't get automatically deleted
