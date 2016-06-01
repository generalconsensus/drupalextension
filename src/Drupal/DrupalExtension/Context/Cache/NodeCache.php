<?php

namespace Drupal\DrupalExtension\Context\Cache;

use Behat\Behat\Context\TranslatableContext as Context;

/**
 * Stores references to nodes created during drupal testing.
 *
 * This cache stores nodes by node id.  The get method will actively load
 * the node object.
 *
 * WARNING: This class implements D7 specific methods.  This needs to be
 * fixed.
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
    return node_load($key);
  }

  // /**
  //  * {@InheritDoc}.
  //  */
  // public function find(array $values = array()) {
  //   foreach ($values as $k => $v) {
  //     if (!is_scalar($v)) {
  //       throw new \Exception(sprintf("%s::%s: Does not yet support non-scalar finding", get_class($this), __FUNCTION__));
  //     }
  //   }
  //   $nids = array_keys(get_object_vars($this->cache));
  //   $results = entity_load('node', $nids);
  //   if (empty($results)) {
  //     return array();
  //   }
  //   $matches = array();
  //   foreach ($results as $nid => $entity) {
  //     $e_wrapped = entity_metadata_wrapper('node', $entity, array("bundle" => $entity->type));
  //     $match = TRUE;
  //     foreach ($values as $k => $v) {
  //       // TODO: This is ugly. I need to resolve aliases elsewhere.
  //       if ($k === '@') {
  //         continue;
  //       }
  //       if (get_class($e_wrapped->{$k}) === 'EntityListWrapper' && !is_array($v)) {
  //         $v = array($v);
  //       }
  //       $old_value = $e_wrapped->{$k}->value();
  //       // Stringify for printing in debug messages.
  //       if ($old_value !== $v) {
  //         $match = FALSE;
  //         break;
  //       }
  //     }
  //     if ($match) {
  //       $matches[] = $this->get($e_wrapped->getIdentifier());
  //     }
  //   }
  //   // Print sprintf("%s::%s: Matches: %s", get_class($this), __FUNCTION__, implode("\n", $matches));.
  //   return $matches;
  // }

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
  public function clean(Context &$context) {
    if ($this->count() === 0) {
      return TRUE;
    }
    $nids = array_keys(get_object_vars($this->cache));
    $context->getDriver()->nodeDeleteMultiple($nids);
    $this->resetCache();
  }

}
