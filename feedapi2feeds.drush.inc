<?php
// $Id$

/**
 * @file
 * Drush commands for FeedAPI2Feeds migration.
 */

/**
 * Implementation of hook_drush_help().
 */
function feedapi2feeds_drush_help($section) {
  switch ($section) {
    case 'drush:feedapi2feeds migrateAll':
      return dt('Migrates all the FeedAPI content-types on the site to Feeds importer configurations');
    case 'drush:feedapi2feeds migrate':
      return dt('Migrates the given FeedAPI content-types on the site to Feeds importer configurations');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function feedapi2feeds_drush_command() {
  $items['feedapi2feeds migrateAll'] = array(
    'callback' => 'feedapi2feeds_drush_migrate_all',
    'description' => dt('Migrates all the FeedAPI content-types on the site to Feeds importer configurations')
  );
  $items['feedapi2feeds migrate'] = array(
    'callback' => 'feedapi2feeds_drush_migrate',
    'description' => dt('Migrates the given FeedAPI content-type to Feeds importer configurations'),
    'arguments' => array(
       'type' => 'Machine name of a FeedAPI content-type',
    ),
  );
  return $items;
}

/**
 * Loops through all the content-types and migrate to Feeds importer configuration.
 */
function feedapi2feeds_drush_migrate_all() {
  module_load_include('php', 'feedapi2feeds', 'feedapi2feeds');
  $migration = new FeedAPI2Feeds();
  $to_migrate = $migration->getTypesToMigrate();
  if (empty($to_migrate)) {
    return drush_set_error('DRUSH_FEEDAPI2FEEDS', dt('There is no FeedAPI-enabled content-type on the site.'));
  }
  try {
    $migration->migrateAll();
  } catch (Exception $e) {
    drush_print("The migration process has been completed with the following errors:\n". $e->getMessage());
  }
  $msgs = $migration->getMessages();
  foreach ($msgs as $msg) {
    drush_print($msg);
  }
  drush_print(dt("The migration has been completed."));
}

/**
 * Migrate one content-type to Feeds importer configuration.
 */
function feedapi2feeds_drush_migrate($type) {
  module_load_include('php', 'feedapi2feeds', 'feedapi2feeds');
  $migration = new FeedAPI2Feeds();
  $to_migrate = $migration->getTypesToMigrate();
  if (empty($to_migrate)) {
    return drush_set_error('DRUSH_FEEDAPI2FEEDS', dt('There is no FeedAPI-enabled content-type on the site.'));
  }
  if (!in_array($type, $to_migrate)) {
    return drush_set_error('DRUSH_FEEDAPI2FEEDS', dt('This is not a FeedAPI-enabled content-type, cannot migrate.'));
  }
  try {
    $migration->migrateType($type);
  } catch (Exception $e) {
    drush_print("The migration was not successful:\n". $e->getMessage());
  }
  $msgs = $migration->getMessages();
  foreach ($msgs as $msg) {
    drush_print($msg);
  }
  drush_print(dt("The migration has been completed."));
}
