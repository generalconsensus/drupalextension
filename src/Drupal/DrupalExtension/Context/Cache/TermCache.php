<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class TermCache extends CacheBase {
  protected $primary_key = 'tid';
  /**
   * {@InheritDoc}
   */
  public function clean(&$context){
    if(empty($this->cache)){
      return TRUE;
    }
    foreach ($this->cache as $term) {
      $context->getDriver()->termDelete($term);
    }
    return $this->resetCache();
  }
}
