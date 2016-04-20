<?php

/**
 * @file
 */

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
use Drupal\DrupalExtension\Hook\Scope\BeforeScenarioScope;
use Drupal\DrupalExtension\Hook\Scope\AfterScenarioScope;
use Drupal\DrupalExtension\Context\Cache as ExtensionCache;


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
   * Current authenticated user.
   *
   * A value of FALSE denotes an anonymous user.
   *
   * @var stdClass|bool
   */
  public $user = FALSE;

  /**
   * Keep track of nodes so they can be cleaned up.  Note that this has
   * been converted to a static variable, reflecting the fact that nodes
   * can be created by multiple contexts.
   *
   * @var array
   */
  protected static $nodes = NULL;

  /**
   * Keep track of all users that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $users = NULL;

  /**
   * Keep track of all terms that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $terms = NULL;

  /**
   * Keep track of any roles that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $roles = NULL;

  /**
   * Keep track of any other contexts run during this scenario.  If they do
   * not require shared state, I can use them.  This *may* need to be in
   * static context, so as to be available to the AfterFeature hook.
   *
   * @var array
   */
  protected static $contexts = NULL;

  /**
   * Keep track of any languages that are created so they can easily be removed.
   *
   * @var array
   */
  protected static $languages = array();

  /**
   * Static scenario variable objects that need to be refreshed between
   * feature invocations use this flag.
   *
   * @var boolean
   */
  protected static $feature_static_initialized = FALSE;
  /**
   * Static scenario variable objects that need to be refreshed between
   * scenario invocations use this flag.
   *
   * @var boolean
   */
  protected static $scenario_static_initialized = FALSE;
  /**
   *
   */
  public function __construct() {

  }
  /**
   * @BeforeScenario
   * Invoking this hook to gather references to other contexts established
   * during runtime.  We use this approach so we can ask questions of
   * other contexts that do not require our shared state.
   * See https://gist.github.com/stof/930e968829cd66751a3a.
   */
  public function gatherContexts($scope) {

    $this->user =& self::$users->current;
  }

  /**
   * @BeforeFeature
   *
   * Instantiates all cache objects that will be used to store drupal ... stuff.
   *
   * @param  Behat\Behat\Hook\Scope\BeforeFeatureScope $scope
   *   the behat surrounding scope
   * @return NULL
   */
  public static function beforeFeature(\Behat\Behat\Hook\Scope\BeforeFeatureScope $scope) {
    if (!self::$feature_static_initialized) {
      self::$users = new ExtensionCache\UserCache();
      self::$users->addIndices('roles', 'name');
      self::$nodes = new ExtensionCache\NodeCache();
      self::$nodes->addIndices('type');
      self::$languages = new ExtensionCache\LanguageCache();
      self::$terms = new ExtensionCache\TermCache();
      self::$roles = new ExtensionCache\RoleCache();
      self::$contexts = new ExtensionCache\ContextCache();
      self::$feature_static_initialized = TRUE;
    }
  }
  /**
   * @AfterFeature
   *
   * Invalidates all static cache objects to ensure proper garbage
   * collection.
   *
   * @param  Behat\Behat\Hook\Scope\AfterFeatureScope $scope
   *   the behat surrounding scope
   * @return NULL
   */
  public static function afterFeature(\Behat\Behat\Hook\Scope\AfterFeatureScope $scope) {
    if (self::$feature_static_initialized) {
      self::$users = NULL;
      self::$nodes = NULL;
      self::$languages = NULL;
      self::$terms = NULL;
      self::$roles = NULL;
      self::$contexts = NULL;
      self::$feature_static_initialized = FALSE;
    }

  }
  /**
   * @BeforeScenario
   *
   * Captures other contexts established during this scenario invocation,
   * and stores them for in-context use.
   * @param  \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   The behat scope
   *   [description]
   * @return [type]                                                       [description]
   */
  public function beforeScenario(\Behat\Behat\Hook\Scope\BeforeScenarioScope $scope) {
    if (!self::$scenario_static_initialized) {
      //print "BeforeScenario.  Constructing static caches...\n";
      // Print "Before scenario.  Adding contexts.\n";.
      $environment = $scope->getEnvironment();
      $settings    = $environment->getSuite()->getSettings();
      foreach ($settings['contexts'] as $context_name) {
        // Print "Adding $context_name\n";.
        $context = $environment->getContext($context_name);
        self::$contexts->add($context, array('key' => $context_name));
      }
      self::$scenario_static_initialized = TRUE;
    }
  }
  /**
   * @AfterScenario
   * Cleans all the cache objects.  This has the side effect of cleaning
   * any cached objects from the database.
   */
  public function afterScenario() {
    if (self::$scenario_static_initialized) {
      //print "Clearing static caches...\n";
      self::$users->clean($this);
      self::$nodes->clean($this);
      self::$languages->clean($this);
      self::$terms->clean($this);
      self::$roles->clean($this);
      self::$contexts->clean($this);
      self::$scenario_static_initialized = FALSE;
    }
  }
  /**
   * Utility function to create a node quickly and easily.
   *
   * @param array $valuesAn
   *   array of values to assign to the node.
   *   An array of values to assign to the node.
   *
   * @return The newly created drupal node
   */
  protected function _createNode($values = array()) {
    $cached = self::$nodes->find($values);
    //var_dump($values);
    if (!empty($cached)) {
      //print "Cached node found\n";
      return $cached;
    }

    // Create a serializable index from the unique values.
    // Assign defaults where possible.
    $values = $values + array(
      'body' => $this->getDriver()->getRandom()->string(255)
    );
    $values = (object) $values;
    $saved = $this->nodeCreate($values);
    return $saved;
  }
  /**
   * Utility function for the common job (in this context) of creating
   * a user.  This is a bit confusing with the presence of userCreate,
   * but this serves as a wrapper for that call to include caching
   * and role assignment.
   *
   * @param array $valuesAn
   *   array of key/value pairs that describe
   *   An array of key/value pairs that describe
   *   the values to be assigned to this user.
   *
   * @return $user         The newly created user.
   */
  protected function _createUser($values = array()) {
    $cached = self::$users->find($values);
    if (!empty($cached)) {
      return $cached;
    }
    // Create a serializable index from the unique values only.
    $values_hash = serialize($values);
    // Assign defaults where possible.
    $values = $values + array(
        'name' => $this->getDriver()->getRandom()->name(8),
        'pass' => $this->getDriver()->getRandom()->name(16),
        'roles' => array()
      );
    $values['mail'] = "$values[name]@example.com";
    $values = (object) $values;
    $saved = $this->userCreate($values);
    foreach ($values->roles as $role) {
      if (!in_array(strtolower($role), array('authenticated', 'authenticated user'))) {
        // Only add roles other than 'authenticated user'.
        $this->getDriver()->userAddRole($saved, $role);
      }
    }
    return $saved;
  }
  /**
   * {@inheritDoc}.
   */
  public function setDrupal(DrupalDriverManager $drupal) {
    $this->drupal = $drupal;
  }

  /**
   * {@inheritDoc}.
   */
  public function getDrupal() {
    return $this->drupal;
  }


  /**
   * {@inheritDoc}.
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
   *
   * @throws \Exception
   *
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
   * @param object $entity
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
    self::$nodes->add($saved);
    return $saved;
  }

  /**
   * Parse multi-value fields. Possible formats:
   *    A, B, C
   *    A - B, C - D, E - F.
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
   * Prints the cache contents of each cache.
   *
   * @param string $cache_name
   *   An optional string argument - prints only that
   *                            specific cache.
   *
   * @return NULL
   */
  public function displayCaches($cache_name = 'all') {
    if (empty($cache_name)) {
      throw new \Exception("Invalid argument for " . __FUNCTION__);
    }
    if ($cache_name === 'all') {
      print self::$users . "\n";
      print self::$roles . "\n";
      print self::$nodes . "\n";
      print self::$terms . "\n";
      print self::$languages . "\n";
      print self::$contexts . "\n";
      return;
    }
    if (!property_exists($this, $cache_name)) {
      throw new \Exception($cache_name . " is not a valid cache for this context");
    }
    print (self::$$cache_name) . "\n";

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
    $saved = $this->getDriver()->userCreate($user);
    $this->dispatchHooks('AfterUserCreateScope', $user);
    self::$users->add($user);
    return $user;
  }

  /**
   * Create a term. Note: does this deal with multiple taxonomies? It
   * doesn't appear so.
   *
   * @return object
   *   The created term.
   */
  public function termCreate($term) {
    $this->dispatchHooks('BeforeTermCreateScope', $term);
    $this->parseEntityFields('taxonomy_term', $term);
    $saved = $this->getDriver()->createTerm($term);
    $this->dispatchHooks('AfterTermCreateScope', $saved);
    self::$terms->add($term);
    return $saved;
  }
  /**
   * Extracted from DrupalContext's assertLoggedInWithPermissions,
   * this moves the functionality of creating a possibly shared role
   * into the parent class.
   *
   * @param string $permissions
   *   A comma-separated list of permissinos
   *
   * @return int              The role id of the newly created role.
   */
  public function roleCreate($permissions) {
    $role_name = $this->getDriver()->roleCreate($permissions);
    $role = new \stdClass();
    $role->rid = $role_name;
    $role->permissions = $permissions;
    self::$roles->add($role);
    return $role_name;
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
      self::$languages->add($language->langcode, $language);
    }
    return $language;
  }
  /**
   * Returns the currently logged in user, or NULL, if no login action has
   * yet happened.
   *
   * @return object|NULL
   *         The logged in user, or NULL if no user is currently logged in.
   */
  public function getCurrentUser() {
    if (!$this->loggedIn()) {
      return NULL;
    }
    if (empty(self::$users->current)) {
      throw new \Exception('The drupal session is logged in, but no
        current user is recorded in the context.  This is an invalid state, and
        shouldn\'t have happened.');
    }
    return self::$users->current;
  }
  /**
   * Returns the named user if he/she has been created.
   *
   * @param string $name
   *   The name of the user
   *
   * @return The user | FALSE
   *                  Returns FALSE if the named user has not yet been
   *                  created (in this scenario - doesn't check the db)
   */
  public function getNamedUser($name) {
    $result = self::$users->find(array('name' => $name));
    if (!empty($results)) {
      return FALSE;
    }
    return reset($results);
  }
  /**
   * Assigns the user $name to be the currently logged in user.  This will log
   * $user in as the current user as a side-effect.  Note:
   *   This user must have been created in a prior step.
   *
   * @param string $name
   *   The name assigned to the user.
   */
  public function setNamedUser($name) {
    $user = $this->getNamedUser($name);
    if (empty($user)) {
      throw new \Exception(sprintf('No user with %s name is registered with the driver.', $name));
    }
    // Change internal current user.
    self::$users->current = $user;
    $this->login();
  }

  /**
   * Log-in the current user.
   */
  public function login() {
    // Check if logged in.
    if ($this->loggedIn()) {
      $this->logout();
    }

    if (empty(self::$users->current)) {
      throw new \Exception('Tried to login without a current user.');
    }

    $this->getSession()->visit($this->locatePath('/user'));
    $element = $this->getSession()->getPage();
    $element->fillField($this->getDrupalText('username_field'), self::$users->current->name);
    $element->fillField($this->getDrupalText('password_field'), self::$users->current->pass);
    $submit = $element->findButton($this->getDrupalText('log_in'));
    if (empty($submit)) {
      throw new \Exception(sprintf("No submit button at %s", $this->getSession()->getCurrentUrl()));
    }

    // Log in.
    $submit->click();

    if (!$this->loggedIn()) {
      throw new \Exception(sprintf("Failed to log in as user '%s' with role '%s'", self::$users->current->name, self::$users->current->role));
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
    $result = $element->findLink($this->getDrupalText('log_out'));
    if ($result) {
      // Add assertion here to detect a violation of the 'current user'
      // contract as soon as possible.
      if (empty(self::$users->current)) {
        throw new \Exception("Invalid state - current user is empty");
      }
    }
    return $result;
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
    // TODO: Refactor to allow for multiple roles.
    return $this->loggedIn() && isset(self::$users->current->role) && self::$users->current->role == $role;
  }

  /**
   * Convenience method.  Invokes a method on another context object.
   *
   * @param string $context_name
   *   The name of the other context.  This cannot be
   *                             arbitrary -  said context must have been
   *                             explicitly stored in the class during the
   *                             BeforeScenario hook (see gatherContexts).
   * @param string $method
   *   The name of the method to invoke.
   *
   * @return mixed              The results of the callback from the invoked
   *                                method.
   *
   * @throws \Exception   If the passed method does not exist on the requested
   *                       context, or if the named context does not exist.
   */
  public function callContext($context_name, $method) {
    $other_context = self::$contexts->get($context_name);
    if (empty($other_context)) {
      throw new \Exception("$context_name context not available from within " . get_class($this) . '.  Available contexts: ' . self::$contexts);
    }
    if (!method_exists($other_context, $method)) {
      throw new \Exception("The method $method does not exist in the $context_name context");
    }
    $args = array_slice(func_get_args(), 2);
    return call_user_func_array(array($other_context, $method), $args);
  }

}
