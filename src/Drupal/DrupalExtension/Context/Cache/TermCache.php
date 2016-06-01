<?php

namespace Drupal\DrupalExtension\Context\Cache;

use Behat\Behat\Context\TranslatableContext as Context;

/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class TermCache extends CacheBase {

  /**
   * {@InheritDoc}.
   *
   * WARNING: leverages the D7 api to directly retrieve a result.  This
   * eventually needs to be rewritten to use drivers.
   */
  public function get($key) {
    if (!property_exists($this->cache, $key)) {
      throw new \Exception(sprintf("%s::%s: No term result found for key %s", __CLASS__, __FUNCTION__, $key));
    }
    return taxonomy_term_load($key);
  }

  /**
   * {@InheritDoc}.
   */
  public function clean(Context &$context) {
    if ($this->count() === 0) {
      return TRUE;
    }
    foreach ($this->cache as $term) {
      $context->getDriver()->termDelete($term);
    }
    return $this->resetCache();
  }

}
