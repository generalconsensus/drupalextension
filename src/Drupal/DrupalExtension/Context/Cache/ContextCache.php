<?php

namespace Drupal\DrupalExtension\Context\Cache;
/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class ContextCache extends CacheBase {

  /**
   * {@InheritDoc}.
   */
  public function clean(&$context) {
    if ($this->count() === 0) {
      return TRUE;
    }
    // Do not need to delete contexts; just remove references.
    return $this->resetCache();
  }

  /**
   * This cache does not implement this interface method, and will throw an
   * exception if called.
   */
  public function addIndices() {
    throw new \Exception(get_class($this) . '::' . ": does not implement the " . __FUNCTION__ . " method.");
  }

  /**
   * This cache does not implement this interface method, and will throw an
   * exception if called.
   */
  public function find(array $values = array()) {
    $allowed_keys = array(
      'name' => function($v, $context_names) {
        // Print sprintf("%s::%s: Filtering by name: %s, context names: %s\n", get_class($this), __FUNCTION__, $v, print_r($context_names, TRUE));.
      $results = array();
        foreach ($context_names as $name) {
          if (stristr($name, $v) !== FALSE) {
            $results[] = $name;
          }
        }
        return $results;
      },
      'class' => function($v, $context_names) {
        // Print sprintf("%s::%s: Filtering by class: %s, context names: %s\n", get_class($this), __FUNCTION__, $v, print_r($context_names, TRUE));.
        $results = array();
        foreach ($context_names as $name) {
          $short_name = explode('\\', $name);
          $short_name = end($short_name);
          // Print sprintf("%s::%s: line %s: Name: %s, MATCH: %s\n", get_class($this), __FUNCTION__, __LINE__, $name, (stristr($name, $v) !== FALSE) ? "YES" : "NO");.
          if (stristr($short_name, $v) !== FALSE) {
            $results[] = $name;
          }
        }
        return $results;
      },
    );
    foreach ($values as $k => $v) {
      if (!is_scalar($v)) {
        throw new \Exception(sprintf("%s::%s: Does not yet support non-scalar finding", get_class($this), __FUNCTION__));
      }
    }
    if (count(array_intersect(array_keys($allowed_keys), array_keys($values))) === 0) {
      throw new \Exception(sprintf("%s::%s: The context cache does not support one or more of the passed keys.", get_class($this), __FUNCTION__));
    }
    $results = array();
    foreach ($allowed_keys as $k => $filtering_function) {
      if (isset($values[$k])) {
        // Store the results of filtering the cache with the provided key.
        $results[] = $filtering_function($values[$k], array_keys(get_object_vars($this->cache)));
        // Print sprintf("%s::%s: cache find results for context key: %s, value: %s, result: %s\n", get_class($this), __FUNCTION__, $k, $values[$k], print_r(end($results), TRUE));.
      }
    }
    $results = array_reduce($results, function($carry, $item) {
      if (is_null($carry)) {
        return $item;
      }
      return array_intersect($carry, $item);
    });
    // Print sprintf("%s::%s: cache find results for context: %s", get_class($this), __FUNCTION__, print_r($results, TRUE));.
    if (!empty($results)) {
      foreach ($results as $k => &$v) {
        // Replace the string key with the actual context object.
        $v = $this->cache->{$v};
      }
    }
    return $results;
  }

}
