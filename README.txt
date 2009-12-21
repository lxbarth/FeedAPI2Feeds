FeedAPI2Feeds migration script
------------------------------

Source: FeedAPI 1.8 or later in 1.x branch
Destination: Feeds 1.0 (built for 6.x-1.0-alpha9)

Purpose
-------

This module helps you to migrate your settings and data from FeedAPI to Feeds.
Be aware that Feeds does not support per-feed configuration at all, so you will
definitely LOSE all your PER-FEED configuration. You need to manually create
additional importer configuration when a specific per-feed configuration is essential.

Scope
-----
Supported: FeedAPI Node, FeedAPI Fast, Parser Common Syndication, Parser SimplePie, Parser Ical
Partially supported: FeedAPI Mapper (1.x only!)

How to use
----------

First, make a backup and put your site into maintenance mode.
After enabling FeedAPI2Feeds, go to admin/build/feeds/migrate.
If you don't have other considerations, just leave all the checkboxes
checked as you want to migrate all the FeedAPI content-types, just
submit the form.
For users who have shell access to their sites, there are drush commands
to use, refer "drush help" for more information.

What the script will do?
------------------------

It creates one importer for each FeedAPI-enabled content-type,
selects and configure the appropiate parser and processor for that.
After this, it disables FeedAPI from the content-type.
Then copies all your feeds' and feed items' metadata in the proper Feeds database table.
ALL your already existing data remains untouched. If you're satisfied with the migration
you need to manually disable and uninstall the modules.
The module saves the content-type configuration into renamed variables when it detaches
from the type, so after you're sure you do not need the old configurations, you need to delete them:

mysql> DELETE FROM variable where NAME like "_backup_feedapi_settings%";

It tries to convert the mappings as well from FeedAPI Mapper, but be aware, per-feed mappings
are not supported. The mapping support is very limited cause in FeedAPI, most of the users
have to configure per-feed mapping as the majority of the feed item fields are available
there.

