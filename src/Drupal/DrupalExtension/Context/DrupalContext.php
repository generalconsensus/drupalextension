<?php

/**
 * @file
 */

namespace Drupal\DrupalExtension\Context;

use Behat\Behat\Context\TranslatableContext;
use Behat\Gherkin\Node\TableNode;

/**
 * Provides pre-built step definitions for interacting with Drupal.
 */
final class DrupalContext extends RawDrupalContext implements TranslatableContext {

  /**
   * Definition for steps:.
   *
   * @Given I am an anonymous user
   * @Given I am not logged in
   */
  public function assertAnonymousUser() {
    // Verify the user is logged out.
    if ($this->loggedIn()) {
      $this->logout();
    }
  }
  /**
   * Creates a user.
   * A note on aliases:.
   *
   * @param TableNode $table
   *   The field information for the new user.
   *
   * @return object $user The created drupal user.
   *
   * Definition for steps:
   *
   * @Given the user:
   *
   * Data provided in the form:
   * | name      | Example user     |
   * | mail      | user@example.com |
   * | status    | 1                |
   * | @         | test_user        |
   */
  public function theUser(TableNode $table) {
    $options = self::convertTableNodeToArray($table);
    return $this->_createUser($options);
  }
  /**
   * Convenience step to demonstrate how aliasing works. The @ symbol
   * above is special syntax that defines an 'alias' for a given creation. You
   * can retrieve aliased entries during subsequent steps for modification or
   * deletion.  See `resolveAlias` in RawDrupalContext for more information.
   *
   * @param string $aliasThe
   *   alias you wish to assign to the new user
   *   The alias you wish to assign to the new user
   * @param TableNode $table
   *   The field information for the new user.  Note: any
   *                         alias passed in the table will be overwritten by
   *                         the one passed for $alias - There is no multiple
   *                         assignation occurring.
   *
   * @return object $user The created drupal user.
   *
   * Definition for steps:
   *
   * @Given the user with alias :alias:
   *
   * Data provided in the form:
   * | name      | Example user     |
   * | mail      | user@example.com |
   * | status    | 1                |
   * | @         | test_user        |
   */
  public function theAliasedUser($alias, TableNode $table) {
    $options = self::convertTableNodeToArray($table);
    $options['@'] = $alias;
    return $this->_createUser($options);
  }

  /**
   * Retrives a previously created (and aliased) user from the database.
   * Aliases are established using the @ symbol in the table data.  See
   * 'Given the user' step for more information.
   *
   * @param string $alias
   *   The named alias assigned to the user when they
   *                       were created.
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I am the named user :alias
   */
  public function iAmTheNamedUser($alias) {
    $user = $this->resolveAlias($alias);
    $this->login($user);
  }
  /**
   * Creates a new user and logs them in.
   *
   * @param string $table
   *   A table of data that defines the new user
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I am the user:
   *
   * Data provided in the form:
   * | name      | Example user     |
   * | mail      | user@example.com |
   * | status    | 1                |
   * | ...       | ...              |
   */
  public function iAmTheUser(TableNode $table) {
    $user = $this->theUser($table);
    $this->login($user);
  }

  /**
   * Creates and authenticates a user with the given role(s).
   *
   * @param string $roles
   *   A comma-separated list of roles for the new user.
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I am logged in as a user with the :role role(s)
   * @Given I am a user with the :role role(s)
   * @Given I am logged in as a/an :role
   */
  public function assertAuthenticatedByRole($roles) {
    if ($this->loggedInWithRoles($roles)) {
      return TRUE;
    }
    $user = $this->_createUser(array('roles' => $roles));
    $this->login($user);
  }

  /**
   * Creates and authenticates a user with the given role(s) and given fields.
   *
   * @param string $role
   *   A comma-separated list of roles for the new user.
   * @param string $fields
   *   A table of data that defines the new user
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I am logged in as a user with the :role role(s) and I have the following fields:
   *
   * Data provided in the form:
   * | field_user_name     | John  |
   * | field_user_surname  | Smith |
   * | ...                 | ...   |.
   */
  public function assertAuthenticatedByRoleWithGivenFields($role, TableNode $fields) {
    // Check if a user with this role is already logged in.
    if (!$this->loggedInWithRole($role)) {
      $values = array(
        'roles' => $role,
      );
      foreach ($fields->getRowsHash() as $field => $value) {
        $values[$field] = $value;
      }
      $user = $this->_createUser($values);

      // Login.
      $this->login($user);
    }
  }

  /**
   * Creates and logs in a user with the given permission set.
   *
   * @param string $permissions
   *   A comma-separated list of permissions for
   *                            the newly defined user.
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I am logged in as a user with the :permissions permission(s)
   */
  public function assertLoggedInWithPermissions($permissions) {
    // Create user.
    $user = $this->_createUser();

    // Create and assign a temporary role with given permissions.
    $permissions = explode(',', $permissions);
    $rid         = $this->roleCreate($permissions);
    $this->getDriver()->userAddRole($user, $rid);

    // Login.
    $this->login($user);
  }

  /**
   * Find text in a table row containing given text.
   *
   * @param string $text
   *   The visible text we are searching for
   * @param string $rowText
   *   Some text within a table row that also contains the
   *                         link.
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Then I should see (the text ):text in the :rowText row
   */
  public function assertTextInTableRow($text, $rowText) {
    $row = $this->getTableRow($this->getSession()->getPage(), $rowText);
    if (empty($row)) {
      throw new \Exception(sprintf("Couldn't find the row with text \"%s\".", $rowText));
    }
    if (strpos($row->getText(), $text) === FALSE) {
      throw new \Exception(sprintf('Found a row containing "%s", but it did not contain the text "%s".', $rowText, $text));
    }
  }

  /**
   * Attempts to find a link in a table row containing giving text. This is for
   * administrative pages such as the administer content types screen found at
   * `admin/structure/types`.
   *
   * @param string $link
   *   The visible text for a given link tag
   * @param string $rowText
   *   Some text within a table row that also contains the
   *                         link.
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I click :link in the :rowText row
   *
   * @Then I (should )see the :link in the :rowText row
   */
  public function assertClickInTableRow($link, $rowText) {
    $page = $this->getSession()->getPage();
    if ($link_element = $this->getTableRow($page, $rowText)->findLink($link)) {
      // Click the link and return.
      $link_element->click();
      return;
    }
    throw new \Exception(sprintf('Found a row containing "%s", but no "%s" link on the page %s', $rowText, $link, $this->getSession()->getCurrentUrl()));
  }

  /**
   * Definition for steps:.
   *
   * @Given the cache has been cleared
   */
  public function assertCacheClear() {
    $this->getDriver()->clearCache();
  }

  /**
   * Definition for steps:.
   *
   * @Given I run cron
   */
  public function assertCron() {
    $this->getDriver()->runCron();
  }

  /**
   * Creates content of the given type.
   *
   * @param string $type
   *   The node (bundle) type to create
   * @param string $title
   *   The title of the newly created node
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I am viewing a/an :type (content )with the title :title
   * @Given a/an :type (content )with the title :title
   */
  public function createNode($type, $title) {
    // @todo make this easily extensible.
    $values = array(
      'title' => $title,
    );
    $saved = $this->_createNode($values);
    // Set internal page on the new node.
    $this->getSession()->visit($this->locatePath('/node/' . $saved->nid));
  }

  /**
   * Creates content authored by the current user.
   *
   * @param string $type
   *   The node (bundle) type to create
   * @param string $title
   *   The title of the newly created node
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Given I am viewing my :type (content )with the title :title
   */
  public function createMyNode($type, $title) {
    if (!$this->loggedIn()) {
      throw new \Exception(sprintf('There is no current logged in user to create a node for.'));
    }

    $values = array(
      'title' => $title,
      'type'  => $type,
      'uid'   => $this->getLoggedInUser()->uid,
    );
    $saved = $this->_createNode($values);

    // Set internal page on the new node.
    $this->getSession()->visit($this->locatePath('/node/' . $saved->nid));
  }

  /**
   * Creates content of a given type.
   *
   * @param string $type
   *   The node (bundle) type to view
   * @param TableNode $fields
   *   The field data defining the node we are to view.
   *
   * @Given :type content:
   *
   * Data provided in the form:
   * | title    | author     | status | created           |
   * | My title | Joe Editor | 1      | 2014-10-17 8:00am |
   * | ...      | ...        | ...    | ...               |.
   */
  public function createNodes($type, TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $nodeHash['type'] = $type;
      $this->_createNode($nodeHash);
    }
  }

  /**
   * Creates content of the given type.
   *
   * @param string $type
   *   The node (bundle) type to view
   * @param TableNode $fields
   *   The field data defining the node we are to view.
   *
   * @Given I am viewing a/an :type( content):
   *
   * Data provided in the form:
   * | title     | My node        |
   * | Field One | My field value |
   * | author    | Joe Editor     |
   * | status    | 1              |
   * | ...       | ...            |.
   */
  public function assertViewingNode($type, TableNode $fields) {
    $values = array(
      'type' => $type,
    );
    foreach ($fields->getRowsHash() as $field => $value) {
      $values[$field] = $value;
    }

    $node = $this->_createNode($values);

    // Set internal browser on the node.
    $this->getSession()->visit($this->locatePath('/node/' . $node->nid));
  }

  /**
   * Asserts that a given content type is editable.
   *
   * @param string $type
   *   The node type the currently logged in user is expected
   *   to be able to edit.
   *
   * @Then I should be able to edit a/an :type( content)
   */
  public function assertEditNodeOfType($type) {
    if (is_null($this->getLoggedInUser())) {
      throw new \Exception(sprintf("%s::%s line %s: Cannot test node edit assertions without a preceding login step.", get_class($this), __FUNCTION__, __LINE__));
    }
    $saved = $this->_createNode(array('type' => $type));

    // Set internal browser on the node edit page.
    $this->getSession()->visit($this->locatePath('/node/' . $saved->nid . '/edit'));

    // Test status.
    $this->assertSession()->statusCodeEquals('200');
  }

  /**
   * Creates a term on an existing vocabulary.
   *
   * @param string $vocabulary
   *   The machine name of the taxonomy to create
   *                            the term for.
   * @param string $name
   *   The term to create.
   *
   * @Given I am viewing a/an :vocabulary term with the name :name
   * @Given a/an :vocabulary term with the name :name
   */
  public function createTerm($vocabulary, $name) {

    // @todo make this easily extensible.
    $term = (object) array(
      'name'                    => $name,
      'vocabulary_machine_name' => $vocabulary,
      'description'             => $this->getDriver()->getRandom()->name(255),
    );
    $saved = $this->termCreate($term);

    // Set internal page on the term.
    $this->getSession()->visit($this->locatePath('/taxonomy/term/' . $saved->tid));
  }

  /**
   * Creates multiple users.
   *
   * @param TableNode $usersTable
   *   The table listing users by row.
   *
   * @Given users:
   *
   * Provide user data in the following format:
   *
   * | name     | mail         | roles        |
   * | user foo | foo@bar.com  | role1, role2 |
   */
  public function createUsers(TableNode $usersTable) {
    foreach ($usersTable->getHash() as $userHash) {
      $this->theUser($userHash);
    }
  }
  /**
   * Creates one or more terms on an existing vocabulary.
   *
   * @param string $vocabulary
   *   The machine name of the vocabulary to add
   *   the terms to.
   * @param TableNode $termsTable
   *   The table listing terms by row.
   *
   * @Given :vocabulary terms:
   *
   * Provide term data in the following format:
   *
   * | name  | parent | description | weight | taxonomy_field_image |
   * | Snook | Fish   | Marine fish | 10     | snook-123.jpg        |
   * | ...   | ...    | ...         | ...    | ...                  |
   *
   * Required fields: 'name'.
   */
  public function createTerms($vocabulary, TableNode $termsTable) {
    foreach ($termsTable->getHash() as $termsHash) {
      $term                          = (object) $termsHash;
      $term->vocabulary_machine_name = $vocabulary;
      if (!isset($term->name)) {
        throw new \Exception(sprintf("%s::%s line %s: Table data contained no value for 'name'", get_class($this), __FUNCTION__, __LINE__));
      }
      $this->termCreate($term);
    }
  }

  /**
   * Creates one or more languages.
   *
   * @param TableNode $langcodesTable
   *   The table listing languages by their ISO code.
   *
   * @Given the/these (following )languages are available:
   *
   * Provide language data in the following format:
   *
   * | langcode |
   * | en       |
   * | fr       |
   */
  public function createLanguages(TableNode $langcodesTable) {
    foreach ($langcodesTable->getHash() as $row) {
      $language = (object) array(
        'langcode' => $row['languages'],
      );
      $this->languageCreate($language);
    }
  }
  /**
   * Retrieves the named object, and assigns new values to it.
   *
   * @Given I set the values of :alias to:
   */
  public function iSetTheValuesTo($alias, TableNode $table) {
    $o = $this->resolveAlias($alias);
    $type = $this->resolveAliasType($alias);
    $values = self::convertTableNodeToArray($table);
    switch($type){
      case 'node':
        $this->nodeAlter($o, $values);
      break;
      case 'user':
        $this->userAlter($o, $values);
      break;
      default:
        throw new \Exception(sprintf(':%s::%s: Alteration of %s types not yet supported: %s', get_class($this), __FUNCTION__, $type));
    }
  }
  /**
   * Pauses the scenario until the user presses a key. Useful when debugging a scenario.
   *
   * @return NULL
   *
   * Definition for steps:
   *
   * @Then (I )break
   */
  public function iPutABreakpoint() {
    fwrite(STDOUT, "\033[s \033[93m[Breakpoint] Press \033[1;93m[RETURN]\033[0;93m to continue, or 'q' to quit...\033[0m");
    do {
      $line = trim(fgets(STDIN, 1024));
      // Note: this assumes ASCII encoding.  Should probably be revamped to
      // handle other character sets.
      $charCode = ord($line);
      switch ($charCode) {
        // CR.
        case 0:
          // Y.
          case 121:
          // Y.
          case 89:
          break 2;

        // Case 78: //N
        // case 110: //n
        // q.
        case 113:
          // Q.
          case 81:
          throw new \Exception("Exiting test intentionally.");

        default:
            fwrite(STDOUT, sprintf("\nInvalid entry '%s'.  Please enter 'y', 'q', or the enter key.\n", $line));
          break;
      }
    } while (TRUE);
    fwrite(STDOUT, "\033[u");
  }
  /**
   * Retrieves the provided aliased object, and prints the value of the
   * indicated field.
   *
   * @param string $aliasfield
   *   An alias/field combination to display.  Entry
   *   must be of the form '@:' + alias_name + '/' + field_name, e.g.:
   *   '@:test_user/uid'
   *
   *
   * @Given I debug the alias( value) :alias
   */
  public function debugAliasValue($aliasfield) {

    // TODO: revisit this regex to ensure this can match any alias/field name
    // combination.
    @list($alias, $field) = explode('/', ltrim($aliasfield, '@:'));
    if(empty($alias)){
      throw new \Exception(sprintf("%s::%s line %s: No alias was found in the passed argument %s", get_class($this), __FUNCTION__, __LINE__, $aliasfield));
    }
    if(empty($field)){
      return $this->whenIDebugTheObjectNamed($alias, NULL);
    }
    $object = $this->resolveAlias($alias);
    $field_value = $object->{$field};
    $str_field_value = (is_scalar($field_value)) ? $field_value : print_r($field_value, TRUE);
    $str_field_value = implode("\n\t", explode("\n", $str_field_value));
    print sprintf("\n<%s>\n\tField: %s, Value: \n\t%s\n</%s>\n", $alias, $field, $str_field_value, $alias);

  }
  /**
   * Retrieves the aliased object, and prints it to the console. For debugging
   * purposes.
   *
   * @param string $alias
   *   The named alias of an already-created object
   * @param string $field
   *   (optional) The additional name of a field whose
   *   array/object value should be expanded (such types are collapsed by
   *   default for brevity. A value of 'all' in this field will expend the whole
   *   object).
   *
   * @Given I debug the object named :alias
   * @Given I debug the object named :alias and expand the value of :field
   */
  public function whenIDebugTheObjectNamed($alias, $field = NULL) {

    $object = $this->resolveAlias($alias);
    if (empty($object)) {
      throw new \Exception(sprintf("%s::%s: No value was found for the alias %s", get_class($this), __FUNCTION__, $alias));
    }

    if (!is_array($object) && !is_object($object)) {
      print $object;
      return;
    }
    $expand_field = ($field) ?: '';
    $expand_all   = (!empty($expand_field) && strtolower($expand_field) === 'all');
    $output       = "\n<$alias>\n";

    foreach ($object as $k => $v) {
      if (is_object($v) || is_array($v)) {
        if ($expand_all || (!empty($expand_field) && $k === $expand_field)) {
          $obj = print_r($v, TRUE);
          $obj = implode("\n", array_map(function ($value) {
            return "\t$value";
          }, explode("\n", $obj)));
          $output .= "\t$k: $obj\n";
          continue;
        }
        else {
          $output .= "\t$k: [Obj/Arr]\n";
          continue;
        }
      }
      $output .= "\t$k: \"$v\",\n";
    }
    $output .= "</$alias>\n";
    print $output;
  }
  /**
   * Provides a high level overview of cache state for debugging purposes.
   *
   * @param string $cache_name
   *   The name of the cache to inspect, or 'all',
   *                            to display the contents of all the caches.
   *
   * @Given I show/inspect the :cache_name cache
   * @Given I show/inspect the cache :cache_name
   * @Given I show/inspect the cache
   */
  public function iShowTheCache($cache_name = 'all') {
    return $this->displayCaches($cache_name);
  }

}
