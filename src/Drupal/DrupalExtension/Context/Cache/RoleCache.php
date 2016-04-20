<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class RoleCache extends CacheBase {
  protected $primary_key = 'rid';

  /**
   * {@InheritDoc}
   */
  public function clean(&$context){
    if(empty($this->cache)){
      return TRUE;
    }
    foreach ($this->cache as $rid) {
      $context->getDriver()->roleDelete($rid);
    }
    $this->resetCache();
    //do not need to delete contexts; just remove references.
    return $this->resetCache();
  }
}
