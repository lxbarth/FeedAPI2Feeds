
FeedAPI2Feeds migration script
------------------------------

Source: FeedAPI 1.8 or later in 1.x branch
Destination: Feeds 1.0 (built for 6.x-1.0-alpha9)

http://drupal.org/project/feedapi
http://drupal.org/project/feedapi_mapper
http://drupal.org/project/feeds


Purpose
-------

This module migrates FeedAPI settings and data to Feeds.

Be aware that Feeds does not support per-feed configuration, so you will
definitely LOSE all your per-feed configuration. You need to manually create
additional Feeds importer configurations when a specific per-feed configuration
is essential.


Scope
-----

Supported:

- FeedAPI Node
- FeedAPI Fast
- Parser Common Syndication
- Parser SimplePie
- Parser Ical
  http://drupal.org/project/parser_ical (6.x dev version)

Partially supported:

- Feed Element Mapper:
 - only 1.x
 - only per-content-type mappings
 - only for mapping sources and targets that are available in Feeds

Not supported (known):

- Per Feed Feed Element Mapper mappings.

Beware that this script has not been extensively tested and depending on your
configuration, settings may be lost. A close review of the results of the
migration is indespensible. If you notice any unsupported settings, please
report on the issue queue.

http://github.com/lxbarth/FeedAPI2Feeds/issues

Usage
-----

1. Back up your site and put it into maintenance mode.
2. After enabling FeedAPI2Feeds, go to the migration form on
   admin/build/feeds/migrate.
3. Check the FeedAPI content types that you would like to migrate (choose only
   those that are fully supported).
4. Turn off FeedAPI modules (do not uninstall).
5. Review migration, verify full functionality.
6. Uninstall FeedAPI modules.

Alternatively, migration can be started with drush. Type "drush help" on the
command line for more information.

http://drupal.org/project/drush


What will the script do?
------------------------

The script creates one importer for each FeedAPI-enabled content-type, selects
and configures the appropiate parser and processor for it.

After this, it disables FeedAPI for the content-type. Then it copies all your
FeedAPI feeds' and feed items' metadata to the proper Feeds database table.

ALL your already existing data remains untouched. If you're satisfied with the
migration you need to manually disable and uninstall the modules.

The module saves the content-type configuration into renamed variables when it
detaches them from the type, so after you're sure you do not need the old
configurations, you need to delete them:

mysql> DELETE FROM variable where NAME like "_backup_feedapi_settings%";

The script tries to convert the mappings as well from Feed Element Mapper, but
beware, per-feed mappings are not supported.

