<?php
namespace Drupal\DrupalExtension\Hook\Scope;


/**
 * Represents an Entity hook scope.
 */
final class BeforeNodeCreateScope extends NodeScope {

  /**
   * Return the scope name.
   *
   * @return string
   */
  public function getName() {
    return self::BEFORE;
  }

}
