<?php

namespace Drupal\DrupalExtension\Context;

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\HookDispatcher;

use Drupal\DrupalDriverManager;

use Drupal\DrupalExtension\Hook\Scope\AfterLanguageEnableScope;
use Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope;
use Drupal\DrupalExtension\Hook\Scope\AfterTermCreateScope;
use Drupal\DrupalExtension\Hook\Scope\AfterUserCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BaseEntityScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeLanguageEnableScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeUserCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeTermCreateScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;


/**
 * Provides the raw functionality for interacting with Drupal.
 */
class RawDrupalContext extends RawMinkContext implements DrupalAwareInterface {

  /**
   * Drupal driver manager.
   *
   * @var \Drupal\DrupalDriverManager
   */
  private $drupal;

  /**
   * Test parameters.
   *
   * @var array
   */
  private $drupalParameters;

  /**
   * Event dispatcher object.
   *
   * @var \Behat\Testwork\Hook\HookDispatcher
   */
  protected $dispatcher;

  /**
   * Keep track of nodes so they can be cleaned up.  Note that this has
   * been converted to a static variable, reflecting the fact that nodes
   * can be created by multiple contexts.
   *
   * @var array
   */
  protected static $nodes = array();

  /**
   * Current authenticated user.
   *
   * A value of FALSE denotes an anonymous user.
   *
   * @var stdClass|bool
   */
  public static $user = FALSE;

  /**
   * Keep track of all users that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $users = array();

  /**
   * Keep track of all terms that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $terms = array();

  /**
   * Keep track of any roles that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $roles = array();
  /**
   * Keep track of any other contexts run during this scenario.  If they do
   * not require shared state, I can use them.
   * @var array
   */
  protected static $contexts = array();

  /**
   * Keep track of any languages that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $languages = array();

  /**
   *
   * Invoking this hook to gather references to other contexts established
   * during runtime.  We use this approach so we can ask questions of
   * other contexts that do not require our shared state.
   * See https://gist.github.com/stof/930e968829cd66751a3a
   */
  public function beforeScenario(BeforeScenarioScope $scope)
  {
    $environment = $scope->getEnvironment();
    $settings    = $environment->getSuite()->getSettings();
    foreach ($settings['contexts'] as $context_name) {
      self::$contexts[$context_name] = $environment->getContext($context_name);
    }
    // $this->subContexts.
  }

  /**
   * Invoked after Behat scenario completion.
   *
   * See also:
   * https://github.com/Behat/docs/pull/65.
   *
   * @param AfterScenaroScope $scope
   *   The afterscenario scope.
   */
  public function afterScenario(AfterScenaroScope $scope)
  {
    //make sure references are removed to allow cleanup.  NOt sure if this
    //is strictly necessary, but better safe than sorry.
    self::$contexts = array();
  }

  /**
   * {@inheritDoc}
   */
  public function setDrupal(DrupalDriverManager $drupal) {
    $this->drupal = $drupal;
  }

  /**
   * {@inheritDoc}
   */
  public function getDrupal() {
    return $this->drupal;
  }


  /**
   * {@inheritDoc}
   */
  public function setDispatcher(HookDispatcher $dispatcher) {
    $this->dispatcher = $dispatcher;
  }

  /**
   * Set parameters provided for Drupal.
   */
  public function setDrupalParameters(array $parameters) {
    $this->drupalParameters = $parameters;
  }

  /**
   * Returns a specific Drupal parameter.
   *
   * @param string $name
   *   Parameter name.
   *
   * @return mixed
   */
  public function getDrupalParameter($name) {
    return isset($this->drupalParameters[$name]) ? $this->drupalParameters[$name] : NULL;
  }

  /**
   * Returns a specific Drupal text value.
   *
   * @param string $name
   *   Text value name, such as 'log_out', which corresponds to the default 'Log
   *   out' link text.
   * @throws \Exception
   * @return
   */
  public function getDrupalText($name) {
    $text = $this->getDrupalParameter('text');
    if (!isset($text[$name])) {
      throw new \Exception(sprintf('No such Drupal string: %s', $name));
    }
    return $text[$name];
  }

  /**
   * Get active Drupal Driver.
   */
  public function getDriver($name = NULL) {
    return $this->getDrupal()->getDriver($name);
  }

  /**
   * Get driver's random generator.
   */
  public function getRandom() {
    return $this->getDriver()->getRandom();
  }

  /**
   * Remove any created nodes.
   *
   * @AfterScenario
   */
  public function cleanNodes() {
    // Remove any nodes that were created.
    foreach (self::$nodes as $node) {
      $this->getDriver()->nodeDelete($node);
    }
    self::$nodes = array();
  }

  /**
   * Remove any created users.
   *
   * @AfterScenario
   */
  public function cleanUsers() {
    // Remove any users that were created.
    if (!empty(self::$users)) {
      foreach (self::$users as $user) {
        $this->getDriver()->userDelete($user);
      }
      $this->getDriver()->processBatch();
      self::$users = array();
    }
  }

  /**
   * Remove any created terms.
   *
   * @AfterScenario
   */
  public function cleanTerms() {
    // Remove any terms that were created.
    foreach (self::$terms as $term) {
      $this->getDriver()->termDelete($term);
    }
    self::$terms = array();
  }

  /**
   * Remove any created roles.
   *
   * @AfterScenario
   */
  public function cleanRoles() {
    // Remove any roles that were created.
    foreach (self::$roles as $rid) {
      $this->getDriver()->roleDelete($rid);
    }
    self::$roles = array();
  }

  /**
   * Remove any created languages.
   *
   * @AfterScenario
   */
  public function cleanLanguages() {
    // Delete any languages that were created.
    foreach (self::$languages as $language) {
      $this->getDriver()->languageDelete($language);
      unset(self::$languages[$language->langcode]);
    }
  }

  /**
   * Clear static caches.
   *
   * @AfterScenario @api
   */
  public function clearStaticCaches() {
    $this->getDriver()->clearStaticCaches();
  }

  /**
   * Dispatch scope hooks.
   *
   * @param string $scope
   *   The entity scope to dispatch.
   * @param stdClass $entity
   *   The entity.
   */
  protected function dispatchHooks($scopeType, \stdClass $entity) {
    $fullScopeClass = 'Drupal\\DrupalExtension\\Hook\\Scope\\' . $scopeType;
    $scope = new $fullScopeClass($this->getDrupal()->getEnvironment(), $this, $entity);
    $callResults = $this->dispatcher->dispatchScopeHooks($scope);

    // The dispatcher suppresses exceptions, throw them here if there are any.
    foreach ($callResults as $result) {
      if ($result->hasException()) {
        $exception = $result->getException();
        throw $exception;
      }
    }
  }

  /**
   * Create a node.
   *
   * @return object
   *   The created node.
   */
  public function nodeCreate($node) {
    $this->dispatchHooks('BeforeNodeCreateScope', $node);
    $this->parseEntityFields('node', $node);
    $saved = $this->getDriver()->createNode($node);
    $this->dispatchHooks('AfterNodeCreateScope', $saved);
    self::$nodes[] = $saved;
    return $saved;
  }

  /**
   * Parse multi-value fields. Possible formats:
   *    A, B, C
   *    A - B, C - D, E - F
   *
   * @param string $entity_type
   *   The entity type.
   * @param \stdClass $entity
   *   An object containing the entity properties and fields as properties.
   */
  public function parseEntityFields($entity_type, \stdClass $entity) {
    $multicolumn_field = '';
    $multicolumn_fields = array();

    foreach ($entity as $field => $field_value) {
      // Reset the multicolumn field if the field name does not contain a column.
      if (strpos($field, ':') === FALSE) {
        $multicolumn_field = '';
      }
      // Start tracking a new multicolumn field if the field name contains a ':'
      // which is preceded by at least 1 character.
      elseif (strpos($field, ':', 1) !== FALSE) {
        list($multicolumn_field, $multicolumn_column) = explode(':', $field);
      }
      // If a field name starts with a ':' but we are not yet tracking a
      // multicolumn field we don't know to which field this belongs.
      elseif (empty($multicolumn_field)) {
        throw new \Exception('Field name missing for ' . $field);
      }
      // Update the column name if the field name starts with a ':' and we are
      // already tracking a multicolumn field.
      else {
        $multicolumn_column = substr($field, 1);
      }

      $is_multicolumn = $multicolumn_field && $multicolumn_column;
      $field_name = $multicolumn_field ?: $field;
      if ($this->getDriver()->isField($entity_type, $field_name)) {
        // Split up multiple values in multi-value fields.
        $values = array();
        foreach (explode(', ', $field_value) as $key => $value) {
          $columns = $value;
          // Split up field columns if the ' - ' separator is present.
          if (strstr($value, ' - ') !== FALSE) {
            $columns = array();
            foreach (explode(' - ', $value) as $column) {
              // Check if it is an inline named column.
              if (!$is_multicolumn && strpos($column, ': ', 1) !== FALSE) {
                list ($key, $column) = explode(': ', $column);
                $columns[$key] = $column;
              }
              else {
                $columns[] = $column;
              }
            }
          }
          // Use the column name if we are tracking a multicolumn field.
          if ($is_multicolumn) {
            $multicolumn_fields[$multicolumn_field][$key][$multicolumn_column] = $columns;
            unset($entity->$field);
          }
          else {
            $values[] = $columns;
          }
        }
        // Replace regular fields inline in the entity after parsing.
        if (!$is_multicolumn) {
          $entity->$field_name = $values;
        }
      }
    }

    // Add the multicolumn fields to the entity.
    foreach ($multicolumn_fields as $field_name => $columns) {
      $entity->$field_name = $columns;
    }
  }

  /**
   * Create a user.
   *
   * @return object
   *   The created user.
   */
  public function userCreate($user) {
    $this->dispatchHooks('BeforeUserCreateScope', $user);
    $this->parseEntityFields('user', $user);
    $this->getDriver()->userCreate($user);
    $this->dispatchHooks('AfterUserCreateScope', $user);
    self::$users[$user->name] = self::$user = $user;
    return $user;
  }

  /**
   * Create a term.
   *
   * @return object
   *   The created term.
   */
  public function termCreate($term) {
    $this->dispatchHooks('BeforeTermCreateScope', $term);
    $this->parseEntityFields('taxonomy_term', $term);
    $saved = $this->getDriver()->createTerm($term);
    $this->dispatchHooks('AfterTermCreateScope', $saved);
    self::$terms[] = $saved;
    return $saved;
  }
    /**
   * Extracted from DrupalContext's assertLoggedInWithPermissions,
   * this moves the functionality of creating a possibly shared role
   * into the parent class.
   * @param  string $permissions A comma-separated list of permissinos
   * @return int              The role id of the newly created role.
   */
  public function roleCreate($permissions){
    $rid = $this->getDriver()->roleCreate($permissions);
    self::$roles []= $rid;
    return $rid;
  }
  /**
   * Creates a language.
   *
   * @param \stdClass $language
   *   An object with the following properties:
   *   - langcode: the langcode of the language to create.
   *
   * @return object|FALSE
   *   The created language, or FALSE if the language was already created.
   */
  public function languageCreate(\stdClass $language) {
    $this->dispatchHooks('BeforeLanguageCreateScope', $language);
    $language = $this->getDriver()->languageCreate($language);
    if ($language) {
      $this->dispatchHooks('AfterLanguageCreateScope', $language);
      self::$languages[$language->langcode] = $language;
    }
    return $language;
  }
  /**
   * Returns the currently logged in user, or NULL, if no login action has
   * yet happened
   * @return object|NULL
   *         The logged in user, or NULL if no user is currently logged in.
   */
  public function getCurrentUser(){
    if (!$this->loggedIn()) {
      return NULL;
    }
    return self::$user;
  }

  /**
   * Log-in the current user.
   */
  public function login() {
    // Check if logged in.
    if ($this->loggedIn()) {
      $this->logout();
    }

    if (!self::$user) {
      throw new \Exception('Tried to login without a user.');
    }

    $this->getSession()->visit($this->locatePath('/user'));
    $element = $this->getSession()->getPage();
    $element->fillField($this->getDrupalText('username_field'), self::$user->name);
    $element->fillField($this->getDrupalText('password_field'), self::$user->pass);
    $submit = $element->findButton($this->getDrupalText('log_in'));
    if (empty($submit)) {
      throw new \Exception(sprintf("No submit button at %s", $this->getSession()->getCurrentUrl()));
    }

    // Log in.
    $submit->click();

    if (!$this->loggedIn()) {
      throw new \Exception(sprintf("Failed to log in as user '%s' with role '%s'", self::$user->name, self::$user->role));
    }
  }

  /**
   * Logs the current user out.
   */
  public function logout() {
    $this->getSession()->visit($this->locatePath('/user/logout'));
  }

  /**
   * Determine if the a user is already logged in.
   *
   * @return boolean
   *   Returns TRUE if a user is logged in for this session.
   */
  public function loggedIn() {
    $session = $this->getSession();
    $session->visit($this->locatePath('/'));

    // If a logout link is found, we are logged in. While not perfect, this is
    // how Drupal SimpleTests currently work as well.
    $element = $session->getPage();
    return $element->findLink($this->getDrupalText('log_out'));
  }

  /**
   * User with a given role is already logged in.
   *
   * @param string $role
   *   A single role, or multiple comma-separated roles in a single string.
   *
   * @return boolean
   *   Returns TRUE if the current logged in user has this role (or roles).
   */
  public function loggedInWithRole($role) {
    return $this->loggedIn() && self::$user && isset(self::$user->role) && self::$user->role == $role;
  }

}
