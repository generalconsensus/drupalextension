<?php
namespace Drupal\DrupalExtension\Hook\Scope;


/**
 * Represents an Entity hook scope.
 */
final class AfterTermCreateScope extends TermScope {

  /**
   * Return the scope name.
   *
   * @return string
   */
  public function getName() {
    return self::AFTER;
  }

}
