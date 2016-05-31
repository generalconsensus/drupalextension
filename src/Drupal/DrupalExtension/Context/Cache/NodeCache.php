<?php

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class NodeCache extends CacheBase {

  /**
   * {@InheritDoc}.
   *
   * WARNING: leverages the D7 api to directly retrieve a result.  This
   * eventually needs to be rewritten to use drivers.
   */
  public function get($key) {
    if (!property_exists($this->cache, $key)) {
      throw new \Exception(sprintf("%s::%s: No node result found for key %s", __CLASS__, __FUNCTION__, $key));
    }
    // $e_node = entity_metadata_wrapper('node', $key);
    // $alias_properties = array_keys($e_node->getPropertyInfo());
    // sort($alias_properties);
    // print sprintf("%s::%s: ID: %s Bundle: %s, Resolved entity properties: \n%s\n", get_class($this), __FUNCTION__, $e_node->getIdentifier(), $e_node->getBundle(), implode("\n", $alias_properties));
    // throw new \Exception("DOC");.
    return node_load($key);
  }

  /**
   * {@InheritDoc}.
   */
  public function find(array $values = array()) {
    foreach ($values as $k => $v) {
      if (!is_scalar($v)) {
        throw new \Exception(sprintf("%s::%s: Does not yet support non-scalar finding", get_class($this), __FUNCTION__));
      }
    }
    $nids = array_keys(get_object_vars($this->cache));
    $results = entity_load('node', $nids);
    if (empty($results)) {
      // Print sprintf("%s::%s: No results found when searching for nodes with values: %s", get_class($this), __FUNCTION__, json_encode($values));
      return array();
    }
    $matches = array();
    foreach ($results as $nid => $entity) {
      $e_wrapped = entity_metadata_wrapper('node', $entity, array("bundle" => $entity->type));
      $match = TRUE;
      foreach ($values as $k => $v) {
        // TODO: This is ugly. I need to resolve aliases elsewhere.
        if ($k === '@') {
          continue;
        }
        if (get_class($e_wrapped->{$k}) === 'EntityListWrapper' && !is_array($v)) {
          $v = array($v);
        }
        $old_value = $e_wrapped->{$k}->value();
        // Stringify for printing in debug messages.
        if ($old_value !== $v) {
          $match = FALSE;
          break;
        }
      }
      if ($match) {
        $matches[] = $this->get($e_wrapped->getIdentifier());
      }
    }
    // Print sprintf("%s::%s: Matches: %s", get_class($this), __FUNCTION__, implode("\n", $matches));.
    return $matches;
  }

  /**
   *
   */
  private function doBreak() {
    fwrite(STDOUT, "\033[s \033[93m[Breakpoint] Press any key to continue\033[0m");
    fgets(STDIN, 1024);
    fwrite(STDOUT, "\033[u");
  }

  /**
   * {@InheritDoc}.
   */
  public function clean(&$context) {
    if ($this->count() === 0) {
      return TRUE;
    }
    $nids = array_keys(get_object_vars($this->cache));
    $context->getDriver()->nodeDeleteMultiple($nids);
    $this->resetCache();
  }

}
