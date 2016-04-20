<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class UserCache extends CacheBase {
  protected $primary_key = 'uid';
  //store the currently logged in user
  public $current = FALSE;
  /**
   * {@InheritDoc}
   */
  public function clean(&$context){
    if(empty($this->cache)){
      return TRUE;
    }
    foreach ($this->cache as $user) {
      $context->getDriver()->userDelete($user);
    }
    $context->getDriver()->processBatch();
    $this->resetCache();
    return TRUE;
  }
}
