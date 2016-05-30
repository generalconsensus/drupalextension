<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context\Cache;

/**
 * A simple class to store globally unique aliases to specific items.
 */
class AliasCache extends ReferentialCache {
  const ALIAS_KEY_PREFIX   = '@';
  const ALIAS_VALUE_PREFIX = '@:';

  /**
   * Looks for a defined alias as a property of the passed object.  Unsets it
   * if found, and returns whatever the alias stored there is.
   *
   * @param object &$o
   *   An object
   *
   * @return string|NULL
   *         The string alias if one was found, or NULL if no alias key was
   *         present.
   */
  public static function extractAliasKey(&$o) {
    if(!is_object($o)){
      throw new \Exception(sprintf("%s::%s: Wrong argument type (%s) passed.", __CLASS__, __FUNCTION__, gettype($o)));
    }
    //TODO: check for multiple aliases set on one object
    $alias = NULL;
    if (is_object($o)) {
      if (property_exists($o, self::ALIAS_KEY_PREFIX)) {
        $alias = $o->{self::ALIAS_KEY_PREFIX};
        unset($o->{self::ALIAS_KEY_PREFIX});
      }
    }
    elseif (is_array($o)) {
      if (array_key_exists(self::ALIAS_KEY_PREFIX, $o)) {
        $alias = $o[self::ALIAS_KEY_PREFIX];
        unset($o[self::ALIAS_KEY_PREFIX]);
      }
    }
    else {
      throw new \Exception(sprintf("%s::%s: Invalid argument type: %s", __CLASS__, __FUNCTION__, gettype($o)));
    }
    return $alias;
  }
  /**
   * Returns the cache name where the object is stored.  Should only ever be
   * called by RawDrupalContext.
   * @param  string $alias The alias for the stored object
   * @return string        The cache name where the object is stored
   * @throws  \Exception If the alias does not exist, or the cache where it
   *                    indicates its data is stored does not exist.
   */
  public function getCache($alias){
    if (!property_exists($this->cache, $alias)) {
      throw new \Exception(sprintf("%s::%s: No result found for key %s", __CLASS__, __FUNCTION__, $key));
    }
    $o = $this->cache->{$alias};
    return $o->cache;
  }
  /**
   * Converts alias values passed in from a feature into the value of the object and field the alias references.
   *
   * @param object $values
   *         The parameterized object that will be used to create a new Drupal object (node, user, what have you).
   *         This function is called primarily by the create[X] methods found in RawDrupalContext.
   */
  public function convertAliasValues(&$values) {
    // Translate dynamic values if present.
    if (empty($values)) {
      throw new \Exception(sprintf('%s::%s: An empty argument was passed.', get_class($this), __FUNCTION__));
    }
    if (!is_object($values)) {
      throw new \Exception(sprintf('%s::%s: Invalid argument type for function: %s', get_class($this), __FUNCTION__, gettype($values)));
    }
    foreach ($values as $field_name => $prospective_alias) {
      if (!is_string($prospective_alias)) {
        // We currently don't allow aliases to exist deeper than the first level.
        continue;
      }
      // Print sprintf("%s::%s: Prospective alias: %s\n", get_class($this), __FUNCTION__, $prospective_alias);.
      if (preg_match('|^' . self::ALIAS_VALUE_PREFIX . '|', $prospective_alias)) {
        // This should map to a value in the alias cache.
        $confirmed_alias_with_field = str_replace(self::ALIAS_VALUE_PREFIX, '', $prospective_alias);
        $av_components = explode('/', $confirmed_alias_with_field);
        if (count($av_components) < 2) {
          throw new \Exception(sprintf("%s::%s: Any alias passed as a value must have a field assigned to it.  The alias %s does not", get_class($this), __FUNCTION__, $v));
        }
        list($confirmed_alias, $referenced_field_name) = $av_components;
        // Print sprintf("%s::%s: Confirmed alias: %s, Field: %s\n", get_class($this), __FUNCTION__, $confirmed_alias, $referenced_field_name);.
        $o = $this->get($confirmed_alias);
        if (empty($o)) {
          throw new \Exception(sprintf('%s::%s: Attempt was made to dynamically reference the property of an item that was not yet created.', get_class($this), __FUNCTION__));
        }
        if (!property_exists($o, $referenced_field_name)) {
          $property_list = array_keys(get_object_vars($o));
          sort($property_list);
          throw new \Exception(sprintf("%s::%s: The field %s was  not found on the retrieved cache object: %s ", get_class($this), __FUNCTION__, $referenced_field_name, print_r($property_list, TRUE)));
        }
        $value = NULL;
        $entity_property_list = array_keys(get_object_vars($o));
        if (!in_array($referenced_field_name, $entity_property_list)) {
          throw new \Exception(sprintf("%s::%s: The field %s was  not found on the retrieved cache object.  Available properties: %s ", get_class($this), __FUNCTION__, $referenced_field_name, print_r($entity_property_list, TRUE)));
        }
        $value = $o->{$referenced_field_name};

        // Print sprintf("%s::%s: Retrieved value: %s\n", get_class($this), __FUNCTION__, ((is_scalar($value)) ? $value : '[obj/arr]'));.
        $values->{$field_name} = $value;
      }
    }
  }
}
