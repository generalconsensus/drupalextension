<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class ContextCache extends CacheBase {
  protected $primary_key = NULL;
  /**
   * TODO: Need to establish primary key for contexts.
   * @param [type] &$value [description]
   */
  public function add($value, $options=array()){
    if(!isset($options['key'])){
      throw new \Exception("Context cache add method requires that a value be
        passed for 'key' in the second argument.");
    }
    return parent::add($value, $options);
  }
  /**
   * {@InheritDoc}
   */
  public function clean(&$context){
    if(empty($this->cache)){
      return TRUE;
    }
    //do not need to delete contexts; just remove references.
    return $this->resetCache();
  }
  /**
   * This cache does not implement this interface method, and will throw an
   * exception if called.
   */
  public function find($values=array()) {
    throw new \Exception(get_class($this).'::'.": does not implement the ".__FUNCTION__." method.");
  }
  /**
   * This cache does not implement this interface method, and will throw an
   * exception if called.
   */
  public function addIndices() {
    throw new \Exception(get_class($this).'::'.": does not implement the ".__FUNCTION__." method.");
  }
}
