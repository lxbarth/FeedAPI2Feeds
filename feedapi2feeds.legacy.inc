<?php

/**
 * Copy of feedapi_get_settings() from FeedAPI 1.8.
 */
function feedapi_get_settings($node_type, $vid = FALSE, $reset = FALSE) {
  static $node_settings;

  if (is_numeric($vid)) {
    if (!isset($node_settings[$vid]) || $reset) {
      if ($settings = db_fetch_object(db_query('SELECT settings FROM {feedapi} WHERE vid = %d', $vid))) {
        $settings = unserialize($settings->settings);
        // If parsers don't have any settings, create an empty array
        if (!isset($settings['parsers'])) {
          $settings['parsers'] = array();
        }
        // If processors don't have any settings, create an empty array
        if (!isset($settings['processors'])) {
          $settings['processors'] = array();
        }
      }
      if (is_array($settings) && count($settings['processors']) == 0 && count($settings['parsers']) == 0) {
        $settings = NULL;
      }
      $node_settings[$vid] = !empty($settings) && is_array($settings) ? $settings : FALSE;
    }
    if (!is_array($node_settings[$vid])) {
      if (empty($node_type)) {
        // In normal case, this shouldn't happen. This is an emergency branch
        $node_type = db_result(db_query("SELECT type FROM {node} WHERE vid = %d", $vid));
      }
    }
    else {
      return $node_settings[$vid];
    }
  }

  // Fallback: node_type.
  if (isset($node_type) && is_string($node_type)) {
    if (($settings = variable_get('feedapi_settings_'. $node_type, FALSE)) && ($settings['enabled'] == 1)) {
      // Sanitize data right now, tricky users may turned off the module
      foreach (array('parsers', 'processors') as $type) {
        if (isset($settings[$type]) && is_array($settings[$type])) {
          $modules = array_keys($settings[$type]);
          foreach ($modules as $module) {
            if (!module_exists($module)) {
              unset($settings['parsers'][$module]);
            }
          }
        }
        else {
          // Missing parser or processor, set error message.
          if (user_access('administer content types')) {
            drupal_set_message(t('There are no !type defined for this content type. Go to !edit_page and enable at least one.', array('!type' => $type, '!edit_page' => l('admin/content/node-type/'. $node_type, 'admin/content/node-type/'. $node_type))), 'error', FALSE);
          }
          else {
            drupal_set_message(t('There are no !type defined for this content type. Contact your site administrator.', array('!type' => $type)), 'error', FALSE);
          }
        }
      }
      return $settings;
    }
  }
  return FALSE;
}
