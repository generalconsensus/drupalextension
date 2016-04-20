<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class NodeCache extends CacheBase {
  protected $primary_key = 'nid';
  /**
   * {@InheritDoc}
   */
  public function clean(&$context){
    foreach ($this->cache as $node) {
      $context->getDriver()->nodeDelete($node);
    }
    $this->resetCache();
  }
}
