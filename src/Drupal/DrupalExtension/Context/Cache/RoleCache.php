<?php

namespace Drupal\DrupalExtension\Context\Cache;

use Behat\Behat\Context\TranslatableContext as Context;

/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class RoleCache extends CacheBase {

  /**
   * {@InheritDoc}.
   *
   * WARNING: leverages the D7 api to directly retrieve a result.  This
   * eventually needs to be rewritten to use drivers.
   */
  public function get($key, Context &$context) {
    if (!property_exists($this->cache, $key)) {
      throw new \Exception(sprintf("%s::%s: No role result found for key %s", __CLASS__, __FUNCTION__, $key));
    }
    return $this->getDriver()->getCore()->roleLoad($key);
  }

  /**
   * {@InheritDoc}.
   */
  public function clean(Context &$context) {
    if ($this->count() === 0) {
      return TRUE;
    }
    foreach ($this->cache as $rid) {
      $context->getDriver()->roleDelete($rid);
    }
    $this->resetCache();
    // Do not need to delete contexts; just remove references.
    return $this->resetCache();
  }

}
