<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class LanguageCache extends CacheBase {
  protected $primary_key = 'langcode';
  /**
   * {@InheritDoc}
   */
  public function clean(&$context){
    if(empty($this->cache)){
      return TRUE;
    }
    foreach ($this->cache as $term) {
      $context->getDriver()->languageDelete($term);
    }
    return $this->resetCache();
  }
}
