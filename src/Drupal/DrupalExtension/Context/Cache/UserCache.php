<?php

namespace Drupal\DrupalExtension\Context\Cache;

use Behat\Behat\Context\TranslatableContext as Context;

/**
 * A simple class to store cached copies of created Drupal items,
 *  with indexing.
 */
class UserCache extends CacheBase {
  /**
   * Metadata in this case is data that cannot be adequately retrieved once it
   * is stored in a user object in the drupal system. The most prominent
   * example is the user's password, which we need to log this user in more
   * than once, but there may be others.
   *
   * Metadata is added during the add method, and retrieved during the get
   * method.
   *
   * @var null
   */
  private $metadata = NULL;

  /**
   * Constructor.
   */
  public function __construct() {
    parent::__construct();
    $this->metadata = new \stdClass();
  }

  /**
   * {@InheritDoc}.
   *
   * Extend the base implementation, as we need to pass in the
   * full user object for value. We extract any metadata properties, and then
   * pass to the parent for normal processing.
   */
  public function add($index, $value = NULL) {

    if (empty($value)) {
      throw new \Exception(sprintf("%s::%s: A user object must be passed to the add method for this cache..", get_class($this), __FUNCTION__));
    }
    $metadata = array(
      'pass' => $value->pass,
    );
    $this->addMetaData($index, $metadata);
    return parent::add($index);
  }

  /**
   * Adds metadata about a stored cache item.  User metadata is data that
   * cannot be retrieved when retrieving the user object.
   *
   * @param int/string $index
   *   The index of the user object in the cache (uid)
   * @param array $metadataAn
   *   array of key/value pairs to store for that index
   *   An array of key/value pairs to store for that index
   */
  private function addMetadata($index, $metadata = array()) {
    if (empty($metadata)) {
      return;
    }
    $index = strval($index);
    $this->metadata->{$index} = (object) $metadata;
  }

  /**
   * Adds metadata about a stored cache item.  User metadata is data that
   * cannot be retrieved when retrieving the user object.
   *
   * @param int/string $index
   *   The index of the user object in the cache (uid)
   * @param string $key
   *   The metadata key to retrieve.  Returns entire
   *   metadata object if key is null.
   */
  private function getMetadata($index, $key) {
    $index = strval($index);
    if (!property_exists($this->metadata, $index)) {
      return FALSE;
    }
    if (!property_exists($this->metadata->{$index}, $key)) {
      // Throw new \Exception(sprintf("%s::%s: line %s: The metadata with key %s was never set for this user", get_class($this), __FUNCTION__, __LINE__, $key));.
      return FALSE;
    }
    return $this->metadata->{$index}->{$key};
  }

  /**
   * {@InheritDoc}.
   *
   * WARNING: leverages the D7 api to directly retrieve a result.  This
   * eventually needs to be rewritten to use drivers.
   * Note: we are overwriting the hashed password retrieved from the db
   * with the value stored prior to the initial adding, so we can use
   * it to log this user in again.
   */
  public function get($key) {
    if (!property_exists($this->cache, $key)) {
      throw new \Exception(sprintf("%s::%s: No user result found for key %s", __CLASS__, __FUNCTION__, $key));
    }
    $user = user_load($key);
    $user->pass = $this->getMetaData($user->uid, 'pass');
    return $user;
  }

  /**
   * {@InheritDoc}.
   */
  public function find(array $values = array()) {
    $results = entity_load('user', array_keys(get_object_vars($this->cache)));
    if (empty($results)) {
      throw new \Exception(sprintf("%s::%s: The cached users couldn't be retrieved!", get_class($this), __FUNCTION__));
    }
    $matches = array();
    foreach ($results as $uid => $entity) {
      $e_wrapped = entity_metadata_wrapper('user', $entity);
      $match = TRUE;
      foreach ($values as $k => $v) {
        // TODO: This is ugly. I need to resolve aliases elsewhere.
        if ($k === '@') {
          continue;
        }
        if (get_class($e_wrapped->{$k}) === 'EntityListWrapper' && !is_array($v)) {
          $v = array($v);
        }
        $old_value = $e_wrapped->{$k}->value();
        // Stringify for printing in debug messages.
        if ($old_value !== $v) {
          $match = FALSE;
          break;
        }
      }
      if ($match) {
        $matches[] = $this->get($e_wrapped->getIdentifier());
      }
    }
    return $matches;
  }

  /**
   * {@InheritDoc}.
   */
  public function clean(Context &$context) {
    if ($this->count() === 0) {
      return TRUE;
    }
    $uids = array_keys(get_object_vars($this->cache));
    $context->getDriver()->userDeleteMultiple($uids);
    $this->resetCache();
    return TRUE;
  }

}
