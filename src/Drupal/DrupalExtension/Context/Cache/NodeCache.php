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
  public function get($key, Context &$context) {
    if (!property_exists($this->cache, $key)) {
      throw new \Exception(sprintf("%s::%s: No node result found for key %s", __CLASS__, __FUNCTION__, $key));
    }
    return $context->getDriver()->getCore()->nodeLoad($key);
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
  public function clean(Context &$context) {
    if ($this->count() === 0) {
      return TRUE;
    }
    $nids = array_keys(get_object_vars($this->cache));
    foreach($nids as $nid){
      $node = new \stdClass();
      $node->nid = $nid;
      $context->getDriver()->nodeDelete($node);
    }
    $this->resetCache();
  }

}
