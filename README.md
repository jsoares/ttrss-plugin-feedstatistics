ttrss-plugin-feedstatistics
===========================

Basic feed statistics for Tiny Tiny RSS. 

Adds a statistics pane to the feed preferences tab, including both a Google Reader-style one line summary and a table of statistics per feed.

Notes
-----

1) The table is sorted by posts/day. No way to change this in the preferences, but you can easily change it in the SQL query.

2) We do not keep track of actual feed activity, but instead only look at the current database. This has a few implications, e.g. we do not keep lifetime read counts. 
If you don't have purging enabled, you can still get generate them by modifying the default interval (30 days). 

3) The items per day calculation assumes all feeds were followed for the entire interval. If they were recently added, the value will be wrong.

Installation
------------

1) Upload the feedstatistics directory into [TT-RSS root directory]/plugins.

2) Set the correct permissions, if neeeded.

3) Enable the plugin by going to Preferences -> Plugins.

Changelog
---------

v1.0 - Initial Release (2014-07-05)

v1.1 - Made compatible with pgSQL (2014-07-05)

Links
-----

For discussion, please see the following thread in the Tiny Tiny RSS forums.

http://tt-rss.org/forum/viewtopic.php?f=22&t=3069