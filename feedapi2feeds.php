<?php
// $Id$

/**
 * @file
 * FeedAPI to Feeds migration business logic
 *
 * Provides public class functions to migrate from old feedapi content-types to
 * Feeds importers
 */

class FeedAPI2Feeds {

  private $messages = array();

  // (Old FeedAPI submodule module name) => (Feeds Class name, must be a parser or processor class)
  private $dictionary = array(
    'parser_simplepie' => 'FeedsSimplePieParser',
    'parser_common_syndication' => 'FeedsSyndicationParser',
    'parser_ical' => 'FeedsIcalDateParser',
    'feedapi_node' => 'FeedsNodeProcessor',
    'feedapi_fast' => 'FeedsDataProcessor',
  );

  // Default mapping, suitable for nodes especially
  private $default_mapping = array(
    '0' => array(
      'source' => 'title',
      'target' => 'title',
      'unique' => FALSE,
    ),
    '1' => array(
       'source' => 'description',
      'target' => 'body',
      'unique' => FALSE,
    ),
    '2' => array(
      'source' => 'timestamp',
      'target' => 'created',
      'unique' => FALSE,
    ),
    '3' => array(
      'source' => 'url',
      'target' => 'url',
      'unique' => TRUE,
    ),
    '4' => array(
      'source' => 'guid',
      'target' => 'guid',
      'unique' => TRUE,
    ),
  );

  /**
   * Fill w/ specific mapping migration info if needed
   * For example:
   *  'a:2:{i:0;s:7:"options";i:1;s:4:"tags";}' => 'tags'
   *  (FeedAPI field name, serialized array) => Feeds field name
   * Utilized for both source and target fields
   */
  private $mapping_lookup_table = array(
  );

  /**
   * Specifiy some custom mapping equivalency.
   *
   * @param $feedapi
   *   Serialized feedapi mapping target/source string
   * @param $feeds
   *   The equivalent feeds mapping target/source string
   */
  public function extendMappigLookupTable($feedapi, $feeds) {
    if (is_string($feedapi) && is_string($feed)) {
      if (!empty($feedapi) && !empty($feeds)) {
        $this->mapping_lookup_table[$feedapi] = $feeds;
      }
    }
  }

  /**
   * REPLACES default mapping, see <var>private $default_mapping</var> above.
   * @param $mapping
   *   Mapping array. If not properly formatted, an Exception will be thrown.
   */
  public function setDefaultMapping($mapping) {
    // Sanity check
    if (is_array($mapping) && !empty($mapping)) {
      foreach ($mapping as $k => $entry) {
        if (!is_numeric($k)) {
          throw new Exception("Mapping array has numeric keys.");
        }
        if (!isset($entry['source']) || !isset($entry['target']) || !isset($entry['unique'])) {
          throw new Exception("Mapping entry must have source, target and unique properties.");
        }
      }
      $this->default_mapping = $mapping;
    }
  }

  /**
   * Extend/overwrite module->Class dictionary
   *
   * @param $old_module
   *   FeedAPI submodule name
   * @param $newClass
   *   Parser/Processor class of Feeds
   */
  public function extendDictionary($old_module, $newClass) {
    if (is_string($feedapi) && is_string($feed)) {
      if (!empty($feedapi) && !empty($feeds)) {
        $this->mapping_lookup_table[$old_module] = $newClass;
      }
    }
  }

  /**
   * List the FeedAPI-enabled types, requirements: enabled, have a parser and a processor
   *
   * @return
   *   List of content-type names
   */
  public function getTypesToMigrate() {
    $to_migrate = array();
    $types = node_get_types('names');
    // Sanity check
    if (!function_exists('feedapi_get_settings')) {
      module_load_include('inc', 'feedapi2feeds', 'feedapi2feeds.legacy');
    }

    // Gather legacy feedapi-enabled node types.
    $processors = $importers = $processed_types = array();
    foreach ($types as $type => $name) {
      $settings = feedapi_get_settings($type);
      if (is_array($settings) && count($settings['parsers']) > 0 && count($settings['processors']) > 0 && $settings['enabled'] == TRUE) {
        $to_migrate[] = $type;
      }
    }

    // If any of the existing importers are attached to content types,
    // these should be migration options as well.
    $importers = feeds_importer_load_all();
    foreach ($importers as $importer) {
      if (!empty($importer->config['content_type'])) {
        $to_migrate[] = $importer->config['content_type'];
      }
    }

    return array_unique($to_migrate);
  }

  /**
   * Loops through the content-types and migrate each
   *
   * @return
   *   List of error messages
   */
  public function migrateAll() {
    $err = '';
    $to_migrate = $this->getTypesToMigrate();
    foreach ($to_migrate as $type) {
      // If one of the type fails, try the other ones
      try {
        $this->migrateType($type);
      } catch (Exception $e) {
        $err .= ($type . ': '. $e->getMessage() . "\n");
      }
    }
    return $err;
  }

  /**
   * Migrates the given type to a Feeds importer
   *
   * @param $type
   *   Content-type machine name
   */
  public function migrateType($type) {
    // First attempt to retrieve an existing importer for this content type.
    // If it can be retrieved, assume that it is configured correctly.
    $importers = feeds_importer_load_all();
    foreach ($importers as $potential_importer) {
      if (!empty($potential_importer->config['content_type']) && $potential_importer->config['content_type'] === $type) {
        $importer = $potential_importer;
        if ($class = get_class($importer->processor)) {
          $processor = array_search($class, $this->dictionary);
        }
        break;
      }
    }
    // Otherwise, create a new importer from the legacy FeedAPI configuration.
    if (!isset($importer)) {
      if (!function_exists('feedapi_get_settings')) {
        module_load_include('inc', 'feedapi2feeds', 'feedapi2feeds.legacy');
      }
      $settings = feedapi_get_settings($type);

      // 1) Create new importer and configure it

      // Generate a name for importer
      $importer_name = $type;
      $collision = TRUE;
      $i = 0;
      do {
        $importer = feeds_importer($importer_name);
        if (!ctools_export_load_object('feeds_importer', 'conditions', array('id' => $importer_name))) {
          $collision = FALSE;
        }
        else {
          $importer_name = $type . '_' . ($i++);
        }
      } while ($collision);

      // Enable given parsers, processors w/ configuration, Feeds do not support multi parser, processor
      if (!empty($settings)) {
        $parser = $this->getActive($settings, 'parsers');
        $processor = $this->getActive($settings, 'processors');
      }
      if (empty($parser) || empty($processor)) {
        throw new Exception($type . ' content-type cannot migrated because there is no enabled parser or processor for it.');
        break;
      }
      if (!isset($this->dictionary[$parser])) {
        throw new Exception($parser . ' parser is not supported by this migration script, skipping '. $type);
        break;
      }
      if (!isset($this->dictionary[$processor])) {
        throw new Exception($parser . ' processor is not supported by this migration script, skipping '. $type);
        break;
      }

      // Create the new importer
      $this->createImporter($importer_name, $type);
      $importer = feeds_importer_load($importer_name);
      $importer->setPlugin($this->dictionary[$parser]);
      $importer->setPlugin($this->dictionary[$processor]);

      if ($settings['upload_method'] == 'upload') {
        $importer->setPlugin('FeedsFileFetcher');
      }

      // Apply per-submodule settings
      foreach (array($parser, $processor) as $module) {
        $config_func = 'feedapi2feeds_configure_'. $module;
        if (method_exists($this, $config_func)) {
          $ret = $this->$config_func($settings, $importer);
          if (is_array($ret) && !empty($ret)) {
            $this->default_mapping = $ret;
          }
        }
        else {
          $this->messages[] = t('The settings at @type for @submodule were not migrated.', array('@type' => $type, '@submodule' => $module));
        }
      }

      // Supporting FeedAPI Mapper 1.x style mappings, only per-content-type
      $custom_mapping = variable_get('feedapi_mapper_mapping_'. $type, array());
      if (!empty($custom_mapping) && is_array($custom_mapping)) {
        $sources = $importer->parser->getMappingSources();
        $targets = $importer->processor->getMappingTargets();
        foreach ($custom_mapping as $source => $target) {
          $matched_source = $this->match($source, $sources);
          $matched_target = $this->match($target, $targets);
          if (!empty($matched_source) && !empty($matched_target)) {
            $importer->processor->addMapping($matched_source, $matched_target, FALSE);
          }
          else {
            $this->messages[] = t('Failed to migrate this mapping (@type): @source - @target', array('@type' => $type, '@source' => $source, '@target' => $target));
          }
        }
      }

      // See what's abandoned
      $count = db_result(db_query("SELECT COUNT(*) FROM {feedapi_mapper} m LEFT JOIN {node} n on m.nid = n.nid WHERE n.type = '%s'", $type));
      if ($count > 0) {
        $this->messages[] = t('@num feed nodes were detected with custom mapping (@type), these mappings were skipped, you need to manually migrate them!', array('@type' => $type, '@num' => $count));
      }

      // We have default mapping for these processors
      if ($processor == 'feedapi_node' || $processor == 'feedapi_fast') {
        foreach ($this->default_mapping as $mapping) {
          $importer->processor->addMapping($mapping['source'], $mapping['target'], $mapping['unique']);
        }
      }

      // Attach importer to content-type, disable FeedAPI for that content-type
      $importer->addConfig(array('content_type' => $type));
      $importer->save();

      // Detach FeedAPI from that content-type
      variable_del('feedapi_settings_'. $type);
      variable_set('_backup_feedapi_settings_'. $type, $settings);
    }

    // 2) Migrate feeds

    // Join on vid because of the revision support
    if (db_table_exists('feedapi')) {
      $result = db_query("SELECT f.url, f.nid FROM {feedapi} f LEFT JOIN {node} n on f.vid = n.vid WHERE n.type='%s'", $type);
      while ($feed = db_fetch_array($result)) {
        if (empty($feed['url'])) {
          break;
        }
        $source = feeds_source($importer->id, $feed['nid']);
        $config = $source->getConfig();
        $config['source'] = $config[get_class($importer->fetcher)]['source'] = $feed['url'];
        $source->setConfig($config);
        $source->save();
      }
    }


    // 3) Migrate items
    $item_func = 'feedapi2feeds_item_'. $processor;
    if (method_exists($this, $item_func)) {
      $this->{$item_func}($type, $importer);
    }
  }

  /**
   * Gets information messages. Utilize after the migration and show to the user.
   */
  public function getMessages() {
    return $this->messages;
  }

  /**
   * Creates the given importer configuration
   *
   * @param $name
   *   Name for the importer
   * @param $type
   *   Name of the content-type
   */
  private function createImporter($name, $type) {
    $status = module_load_include('inc', 'feeds_ui', 'feeds_ui.admin');
    if ($status === FALSE) {
      throw new Exception("Feeds UI is not installed. Please enable it before trying to migrate.");
    }
    $form_state = array();
    $form_state['values']['id'] = $form_state['values']['name'] = $name;
    $form_state['values']['description'] = 'From '. $type .' FeedAPI content-type by the FeedAPI2Feeds migration script.';
    $form_state['values']['op'] = t('Create');
    drupal_execute('feeds_ui_create_form', $form_state);
  }

  /**
   * Returns the first enabled parser/processor
   *
   * @param $settings
   *   the whole settings array for a content-type
   * @param $type
   *   'parsers' or 'processors'
   * @return
   *   Name of the parser/processor module
   */
  private function getActive($settings, $type) {
    foreach ($settings[$type] as $key => $s) {
      if ($s['enabled'] == TRUE) {
        return $key;
      }
    }
  }

  /**
   * A wild guess to match mapping sources / targets from the old system to Feeds
   */
  private function match($item, $current) {
    if (isset($this->mapping_lookup_table[$item])) {
      return $this->mapping_lookup_table[$item];
    }

    // Get the actual field name
    $item = unserialize($item);
    if (is_array($item)) {
      $item = array_pop($item);
    }
    $current = array_keys($current);
    foreach ($current as $name) {
      if (strstr($name, $item) !== FALSE) {
        return $name;
      }
    }
    return FALSE;
  }

  /**
   * FeedAPI Fast processor support for migration script
   *
   * Creates default Data table and configure the importer according to the old FeedAPI settings and the defaults
   *
   * @param array $settings
   *   Old FeedAPI settings
   * @param object $importer
   *   Importer object
   */
  private function feedapi2feeds_configure_feedapi_fast($settings, $importer) {
    if (!function_exists('data_get_table')) {
      throw new Exception("Migrating from feedapi_fast requires Data module");
    }
    // Set default importer config
    module_load_include('inc', 'feeds_defaults', 'feeds_defaults.features');
    $default_importer = feeds_defaults_feeds_importer_default();
    $own_settings = $settings['processors']['feedapi_node'];

    $mapping = $default_importer['feed_fast']->config['processor']['config']['mappings'];

    // Create default tables
    $tables = feeds_defaults_data_default();
    data_create_table('feeds_data_'. $importer->id, $tables['feeds_data_feed_fast']->table_schema, 'Table for ' . $importer->id);

    // These settings are not global anymore
    $importer->processor->addConfig(array('update_existing' => $settings['update_existing']));

    $expire = $settings['items_delete'];
    if ($expire == FEEDAPI_FEEDAPI_NEVER_DELETE_OLD) {
      $expire = FEEDS_EXPIRE_NEVER;
    }
    $importer->processor->addConfig(array('expire' => $expire));

    // Report not updated settings
    $old = array_keys($own_settings);
    $handled = array('enabled', 'weight');
    foreach ($old as $setting) {
      if (!in_array($setting, $handled)) {
        $this->messages[] = t('@name old setting was not migrated to @importer importer.', array('@name' => $setting, '@importer' => $importer->id));
      }
    }

    return $mapping;
  }

  /**
   * FeedAPI Fast processor support for migration script
   *
   * Migrate items
   *
   * @param string $type
   *   Old content-type of the feed nodes
   * @param object $importer
   *   Importer object
   */
  private function feedapi2feeds_item_feedapi_fast($type, $importer) {
    if (db_table_exists('feedapi_fast_item')) {
      db_query("INSERT INTO {feeds_data_" . $importer->id . "}
      (feed_nid, timestamp, title, description, url, guid)
      (SELECT fi.feed_nid, f.published, f.title, f.description, f.url, f.guid FROM {feedapi_fast_item} f
      LEFT JOIN {feedapi_fast_item_feed} fi ON f.fid = feed_item_fid
      LEFT JOIN {node} n ON fi.feed_nid = n.nid WHERE n.type='%s')", $type);
    }
  }

  /**
   * FeedAPI Node processor support for migration script
   *
   * Configure the importer according to the old FeedAPI settings
   *
   * @param array $settings
   *   Old FeedAPI settings
   * @param object $importer
   *   Importer object
   */
  private function feedapi2feeds_configure_feedapi_node($settings, $importer) {
    $own_settings = $settings['processors']['feedapi_node'];
    if (!empty($own_settings['content_type'])) {
      $importer->processor->addConfig(array('content_type' => $own_settings['content_type']));
    }

    // This setting is not global anymore
    $importer->processor->addConfig(array('update_existing' => $settings['update_existing']));

    $expire = $settings['items_delete'];
    if ($expire == FEEDAPI_FEEDAPI_NEVER_DELETE_OLD) {
      $expire = FEEDS_EXPIRE_NEVER;
    }
    $importer->processor->addConfig(array('expire' => $expire));

    // Report not updated settings
    $old = array_keys($own_settings);
    $handled = array('enabled', 'weight', 'content_type');
    foreach ($old as $setting) {
      if (!in_array($setting, $handled)) {
        $this->messages[] = t('@name old setting was not migrated to @importer importer.', array('@name' => $setting, '@importer' => $importer->id));
      }
    }
  }

  /**
   * FeedAPI Node processor support for migration script
   *
   * Migrate items
   *
   * @param string $type
   *   Old content-type of the feed nodes
   * @param object $importer
   *   Importer object
   */
  private function feedapi2feeds_item_feedapi_node($type, $importer) {
    $result = db_query("SELECT ni.nid as item_nid, n.nid as feed_nid, ni.url, ni.timestamp, ni.arrived, ni.guid FROM {feedapi_node_item} ni
    LEFT JOIN {feedapi_node_item_feed} nif on ni.nid = nif.feed_item_nid
    LEFT JOIN {node} n on nif.feed_nid = n.nid WHERE n.type = '%s'", $type);
    while ($old_item = db_fetch_array($result)) {
      $new_item = new stdClass();
      // No guess here
      $new_item->hash = '';
      $new_item->id = $importer->id;
      $new_item->imported = $old_item['arrived'];
      $new_item->feed_nid = $old_item['feed_nid'];
      $new_item->nid = $old_item['item_nid'];
      $new_item->url = $old_item['url'];
      $new_item->guid = $old_item['guid'];
      drupal_write_record('feeds_node_item', $new_item);
    }
  }

}
