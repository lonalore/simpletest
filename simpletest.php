<?php

/**
 * @file
 * Provides e107TestCase, e107UnitTestCase, and e107WebTestCase classes.
 */

/**
 * Global variable that holds information about the tests being run.
 *
 * An array, with the following keys:
 *  - 'test_run_id': the ID of the test being run
 *  - 'in_child_site': TRUE if the current request is a cURL request from the parent site.
 *
 * @var array
 */
global $e107_test_info;


/**
 * Base class for e107 tests.
 *
 * Do not extend this class, use one of the subclasses in this file.
 */
abstract class e107TestCase
{

	/**
	 * The test run ID.
	 *
	 * @var string
	 */
	protected $testId;

	/**
	 * The database prefix of this test run.
	 *
	 * @var string
	 */
	protected $databasePrefix = null;

	/**
	 * The original file directory, before it was changed for testing purposes.
	 *
	 * @var string
	 */
	protected $originalFileDirectory = null;

	/**
	 * Time limit for the test.
	 */
	protected $timeLimit = 500;

	/**
	 * Current results of this test case.
	 *
	 * @var array
	 */
	public $results = array(
		'#pass'      => 0,
		'#fail'      => 0,
		'#exception' => 0,
		'#debug'     => 0,
	);

	/**
	 * Assertions thrown in that test case.
	 *
	 * @var array
	 */
	protected $assertions = array();

	/**
	 * This class is skipped when looking for the source of an assertion.
	 *
	 * When displaying which function an assert comes from, it's not too useful to see "e107WebTestCase->e107Login()',
	 * we would like to see the test that called it. So we need to skip the classes defining these helper methods.
	 */
	protected $skipClasses = array(__CLASS__ => true);

	/**
	 * Constructor for e107TestCase.
	 *
	 * @param $test_id
	 *   Tests with the same id are reported together.
	 */
	public function __construct($test_id = null)
	{
		$this->testId = $test_id;
	}

	/**
	 * Internal helper: stores the assert.
	 *
	 * @param mixed $status
	 *   Can be 'pass', 'fail', 'exception'. TRUE is a synonym for 'pass', FALSE for 'fail'.
	 * @param string $message
	 *   The message string.
	 * @param $group
	 *   Which group this assert belongs to.
	 * @param $caller
	 *   By default, the assert comes from a function whose name starts with 'test'. Instead, you can specify where
	 *   this assert originates from by passing in an associative array as $caller. Key 'file' is the name of the
	 *   source file, 'line' is the line number and 'function' is the caller function itself.
	 *
	 * @return bool
	 */
	protected function assert($status, $message = '', $group = 'Other', array $caller = null)
	{
		// Convert boolean status to string status.
		if(is_bool($status))
		{
			$status = $status ? 'pass' : 'fail';
		}

		// Increment summary result counter.
		$this->results['#' . $status]++;

		// Get the function information about the call to the assertion method.
		if(!$caller)
		{
			$caller = $this->getAssertionCall();
		}

		// Creation assertion array that can be displayed while tests are running.
		$this->assertions[] = $assertion = array(
			'test_id'       => $this->testId,
			'test_class'    => get_class($this),
			'status'        => $status,
			'message'       => $message,
			'message_group' => $group,
			'function'      => $caller['function'],
			'line'          => $caller['line'],
			'file'          => $caller['file'],
		);

		// Store assertion for display after the test has completed.
		e107::getDb()->insert('simpletest', array('data' => $assertion), false);

		// We do not use a ternary operator here to allow a breakpoint on test failure.
		if($status == 'pass')
		{
			return true;
		}

		return false;
	}

	/**
	 * Store an assertion from outside the testing context.
	 *
	 * This is useful for inserting assertions that can only be recorded after the test case has been destroyed, such
	 * as PHP fatal errors. The caller information is not automatically gathered since the caller is most likely
	 * inserting the assertion on behalf of other code. In all other respects the method behaves just like
	 * e107TestCase::assert() in terms of storing the assertion.
	 *
	 * @return int|bool
	 *   Message ID of the stored assertion.
	 *
	 * @see e107TestCase::assert()
	 * @see e107TestCase::deleteAssert()
	 */
	public static function insertAssert($test_id, $test_class, $status, $message = '', $group = 'Other', array $caller = array())
	{
		// Convert boolean status to string status.
		if(is_bool($status))
		{
			$status = $status ? 'pass' : 'fail';
		}

		$caller += array(
			'function' => 'Unknown', // FIXME - LANs...
			'line'     => 0,
			'file'     => 'Unknown', // FIXME - LANs...
		);

		$assertion = array(
			'test_id'       => $test_id,
			'test_class'    => $test_class,
			'status'        => $status,
			'message'       => $message,
			'message_group' => $group,
			'function'      => $caller['function'],
			'line'          => $caller['line'],
			'file'          => $caller['file'],
		);

		return e107::getDb()->insert('simpletest', array('data' => $assertion), false);
	}

	/**
	 * Delete an assertion record by message ID.
	 *
	 * @param $message_id
	 *   Message ID of the assertion to delete.
	 *
	 * @return bool
	 *   TRUE if the assertion was deleted, FALSE otherwise.
	 *
	 * @see e107TestCase::insertAssert()
	 */
	public static function deleteAssert($message_id)
	{
		return e107::getDb()->delete('simpletest', 'message_id = ' . (int) $message_id, false);
	}

	/**
	 * Cycles through backtrace until the first non-assertion method is found.
	 *
	 * @return array
	 *   Array representing the true caller.
	 */
	protected function getAssertionCall()
	{
		$backtrace = debug_backtrace();

		// The first element is the call. The second element is the caller.
		// We skip calls that occurred in one of the methods of our base classes or in an assertion function.
		while(($caller = $backtrace[1]) &&
			((isset($caller['class']) && isset($this->skipClasses[$caller['class']])) ||
				substr($caller['function'], 0, 6) == 'assert'))
		{
			// We remove that call.
			array_shift($backtrace);
		}

		return simpletest_get_last_caller($backtrace);
	}

	/**
	 * Check to see if a value is not false (not an empty string, 0, NULL, or FALSE).
	 *
	 * @param $value
	 *   The value on which the assertion is to be done.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertTrue($value, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is TRUE.', array(
			'x' => var_export($value, true),
		));

		return $this->assert((bool) $value, $message ? $message : $default_message, $group);
	}

	/**
	 * Check to see if a value is false (an empty string, 0, NULL, or FALSE).
	 *
	 * @param $value
	 *   The value on which the assertion is to be done.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertFalse($value, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is FALSE.', array(
			'x' => var_export($value, true),
		));

		return $this->assert(!$value, $message ? $message : $default_message, $group);
	}

	/**
	 * Check to see if a value is NULL.
	 *
	 * @param $value
	 *   The value on which the assertion is to be done.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertNull($value, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is NULL.', array(
			'x' => var_export($value, true),
		));

		return $this->assert(!isset($value), $message ? $message : $default_message, $group);
	}

	/**
	 * Check to see if a value is not NULL.
	 *
	 * @param $value
	 *   The value on which the assertion is to be done.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertNotNull($value, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is not NULL.', array(
			'x' => var_export($value, true),
		));

		return $this->assert(isset($value), $message ? $message : $default_message, $group);
	}

	/**
	 * Check to see if two values are equal.
	 *
	 * @param $first
	 *   The first value to check.
	 * @param $second
	 *   The second value to check.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertEqual($first, $second, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is equal to value [y].', array(
			'x' => var_export($first, true),
			'y' => var_export($second, true),
		));

		return $this->assert($first == $second, $message ? $message : $default_message, $group);
	}

	/**
	 * Check to see if two values are not equal.
	 *
	 * @param $first
	 *   The first value to check.
	 * @param $second
	 *   The second value to check.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertNotEqual($first, $second, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is not equal to value [y].', array(
			'x' => var_export($first, true),
			'y' => var_export($second, true),
		));

		return $this->assert($first != $second, $message ? $message : $default_message, $group);
	}

	/**
	 * Check to see if two values are identical.
	 *
	 * @param $first
	 *   The first value to check.
	 * @param $second
	 *   The second value to check.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertIdentical($first, $second, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is identical to value [y].', array(
			'x' => var_export($first, true),
			'y' => var_export($second, true),
		));

		return $this->assert($first === $second, $message ? $message : $default_message, $group);
	}

	/**
	 * Check to see if two values are not identical.
	 *
	 * @param $first
	 *   The first value to check.
	 * @param $second
	 *   The second value to check.
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertNotIdentical($first, $second, $message = '', $group = 'Other')
	{
		// FIXME - LANs...
		$default_message = e107::getParser()->lanVars('Value [x] is not identical to value [y].', array(
			'x' => var_export($first, true),
			'y' => var_export($second, true),
		));

		return $this->assert($first !== $second, $message ? $message : $default_message, $group);
	}

	/**
	 * Fire an assertion that is always positive.
	 *
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 *
	 * @return bool
	 *   TRUE.
	 */
	protected function pass($message = null, $group = 'Other')
	{
		return $this->assert(true, $message, $group);
	}

	/**
	 * Fire an assertion that is always negative.
	 *
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 * @return bool
	 *   FALSE.
	 */
	protected function fail($message = null, $group = 'Other')
	{
		return $this->assert(false, $message, $group);
	}

	/**
	 * Fire an error assertion.
	 *
	 * @param $message
	 *   The message to display along with the assertion.
	 * @param $group
	 *   The type of assertion - examples are "Browser", "PHP".
	 * @param $caller
	 *   The caller of the error.
	 *
	 * @return bool
	 *   FALSE.
	 */
	protected function error($message = '', $group = 'Other', array $caller = null)
	{
		if($group == 'User notice')
		{
			// Since 'User notice' is set by trigger_error() which is used for debug set the message to a status of
			// 'debug'.
			return $this->assert('debug', $message, 'Debug', $caller);
		}

		return $this->assert('exception', $message, $group, $caller);
	}

	/**
	 * Logs verbose message in a text file.
	 *
	 * The link to the verbose message will be placed in the test results via
	 * as a passing assertion with the text '[verbose message]'.
	 *
	 * @param $message
	 *   The verbose message to be stored.
	 *
	 * @see simpletest_verbose()
	 */
	protected function verbose($message)
	{
		if($id = simpletest_verbose($message))
		{
			// FIXME - create a web-accessible URL...
			$url = $this->originalFileDirectory . '/simpletest/verbose/' . get_class($this) . '-' . $id . '.html';

			// FIXME - LANs...
			$link = '<a href="' . $url . '" target="_blank">' . 'Verbose message' . '</a>';

			$this->error($link, 'User notice');
		}
	}

	/**
	 * Run all tests in this class.
	 *
	 * Regardless of whether $methods are passed or not, only method names starting with "test" are executed.
	 *
	 * @param $methods
	 *   (optional) A list of method names in the test case class to run; e.g., array('testFoo', 'testBar').
	 *   By default, all methods of the class are taken into account, but it can be useful to only run a few selected
	 *   test methods during debugging.
	 */
	public function run(array $methods = array())
	{
		$prefs = e107::getPlugConfig('simpletest')->getPref();

		$original_file_directory = '';

		// Initialize verbose debugging.
		simpletest_verbose(null, $original_file_directory, get_class($this));

		// HTTP auth settings (<username>:<password>) for the simpletest browser
		// when sending requests to the test site.
		$this->httpauth_method = !empty($prefs['httpauth_method']) ? $prefs['httpauth_method'] : CURLAUTH_BASIC;

		$username = !empty($prefs['httpauth_username']) ? $prefs['httpauth_username'] : null;
		$password = !empty($prefs['httpauth_password']) ? $prefs['httpauth_password'] : null;

		if($username && $password)
		{
			$this->httpauth_credentials = $username . ':' . $password;
		}

		set_error_handler(array($this, 'errorHandler'));

		$class = get_class($this);

		// Iterate through all the methods in this class, unless a specific list of methods to run was passed.
		$class_methods = get_class_methods($class);

		if($methods)
		{
			$class_methods = array_intersect($class_methods, $methods);
		}

		foreach($class_methods as $method)
		{
			// If the current method starts with "test", run it - it's a test.
			if(strtolower(substr($method, 0, 4)) == 'test')
			{
				// Insert a fail record. This will be deleted on completion to ensure that testing completed.
				$method_info = new ReflectionMethod($class, $method);

				$caller = array(
					'file'     => $method_info->getFileName(),
					'line'     => $method_info->getStartLine(),
					'function' => $class . '->' . $method . '()',
				);

				// FIXME - LANs...
				$message = 'The test did not complete due to a fatal error.';

				$completion_check_id = e107TestCase::insertAssert($this->testId, $class, false, $message, 'Completion check', $caller);

				$this->setUp();

				try
				{
					$this->$method();
					// Finish up.
				} catch(Exception $e)
				{
					$this->exceptionHandler($e);
				}

				$this->tearDown();

				// Remove the completion check record.
				e107TestCase::deleteAssert($completion_check_id);
			}
		}

		// Clear out the error messages and restore error handler.
		e107::getMessage()->reset();
		restore_error_handler();
	}

	/**
	 * Handle errors during test runs.
	 *
	 * Because this is registered in set_error_handler(), it has to be public.
	 *
	 * @see set_error_handler
	 */
	public function errorHandler($severity, $message, $file = null, $line = null)
	{
		if($severity & error_reporting())
		{
			$error_map = array(
				E_STRICT            => 'Run-time notice',
				E_WARNING           => 'Warning',
				E_NOTICE            => 'Notice',
				E_CORE_ERROR        => 'Core error',
				E_CORE_WARNING      => 'Core warning',
				E_USER_ERROR        => 'User error',
				E_USER_WARNING      => 'User warning',
				E_USER_NOTICE       => 'User notice',
				E_RECOVERABLE_ERROR => 'Recoverable error',
			);

			$backtrace = debug_backtrace();

			$this->error($message, $error_map[$severity], simpletest_get_last_caller($backtrace));
		}

		return true;
	}

	/**
	 * Handle exceptions.
	 *
	 * @see set_exception_handler
	 */
	protected function exceptionHandler($exception)
	{
		$backtrace = $exception->getTrace();

		// Push on top of the backtrace the call that generated the exception.
		array_unshift($backtrace, array(
			'line' => $exception->getLine(),
			'file' => $exception->getFile(),
		));

		$decoded = simpletest_decode_exception($exception);

		// The exception message is run through simpletest_check_plain() by simpletest_decode_exception().
		$message = e107::getParser()->lanVars('[type]: [message] in [function] (line [line] of [file]).', array(
			'type'     => $decoded['type'],
			'message'  => $decoded['message'],
			'function' => $decoded['function'],
			'line'     => $decoded['line'],
			'file'     => $decoded['file'],
		));

		$this->error($message, 'Uncaught exception', simpletest_get_last_caller($backtrace));
	}

	/**
	 * Generates a random string of ASCII characters of codes 32 to 126.
	 *
	 * The generated string includes alpha-numeric characters and common misc characters. Use this method when testing
	 * general input where the content is not restricted.
	 *
	 * @param int $length
	 *   Length of random string to generate.
	 *
	 * @return string
	 *   Randomly generated string.
	 */
	public static function randomString($length = 8)
	{
		$str = '';

		for($i = 0; $i < $length; $i++)
		{
			$str .= chr(mt_rand(32, 126));
		}

		return $str;
	}

	/**
	 * Generates a random string containing letters and numbers.
	 *
	 * The string will always start with a letter. The letters may be upper or lower case. This method is better for
	 * restricted inputs that do not accept certain characters. For example, when testing input fields that require
	 * machine readable values (i.e. without spaces and non-standard characters) this method is best.
	 *
	 * @param int $length
	 *   Length of random string to generate.
	 *
	 * @return string
	 *   Randomly generated string.
	 */
	public static function randomName($length = 8)
	{
		$values = array_merge(range(65, 90), range(97, 122), range(48, 57));
		$max = count($values) - 1;
		$str = chr(mt_rand(97, 122));

		for($i = 1; $i < $length; $i++)
		{
			$str .= chr($values[mt_rand(0, $max)]);
		}

		return $str;
	}

	/**
	 * Converts a list of possible parameters into a stack of permutations.
	 *
	 * Takes a list of parameters containing possible values, and converts all of them into a list of items containing
	 * every possible permutation.
	 *
	 * Example:
	 * @code
	 * $parameters = array(
	 *   'one' => array(0, 1),
	 *   'two' => array(2, 3),
	 * );
	 *
	 * $permutations = $this->permute($parameters);
	 *
	 * // Result:
	 * $permutations == array(
	 *   array('one' => 0, 'two' => 2),
	 *   array('one' => 1, 'two' => 2),
	 *   array('one' => 0, 'two' => 3),
	 *   array('one' => 1, 'two' => 3),
	 * )
	 * @endcode
	 *
	 * @param array $parameters
	 *   An associative array of parameters, keyed by parameter name, and whose values are arrays of parameter values.
	 *
	 * @return array
	 *   A list of permutations, which is an array of arrays. Each inner array contains the full list of parameters
	 *   that have been passed, but with a single value only.
	 */
	public static function generatePermutations($parameters)
	{
		$all_permutations = array(array());

		foreach($parameters as $parameter => $values)
		{
			$new_permutations = array();

			// Iterate over all values of the parameter.
			foreach($values as $value)
			{
				// Iterate over all existing permutations.
				foreach($all_permutations as $permutation)
				{
					// Add the new parameter value to existing permutations.
					$new_permutations[] = $permutation + array($parameter => $value);
				}
			}

			// Replace the old permutations with the new permutations.
			$all_permutations = $new_permutations;
		}

		return $all_permutations;
	}

}


/**
 * Test case for e107 unit tests.
 *
 * These tests can not access the database nor files. Calling any e107 function that needs the database will throw
 * exceptions.
 */
class e107UnitTestCase extends e107TestCase
{

	/**
	 * The original prefix for MySQL connections.
	 *
	 * @var string
	 */
	protected $originalMySQLPrefix;

	/**
	 * Constructor for e107UnitTestCase.
	 */
	function __construct($test_id = null)
	{
		parent::__construct($test_id);

		$this->skipClasses[__CLASS__] = true;
	}

	/**
	 * Sets up unit test environment.
	 *
	 * Unlike e107WebTestCase::setUp(), e107UnitTestCase::setUp() does not install plugins because tests are performed
	 * without accessing the database. Any required files must be explicitly included by the child class setUp() method.
	 */
	protected function setUp()
	{
		global $mySQLprefix;

		// Store necessary current values before switching to the test environment.
		$this->originalFileDirectory = ''; // TODO get public directory for user files.

		// Generate temporary prefixed database to ensure that tests have a clean starting point.
		$this->databasePrefix = 'simpletest' . mt_rand(1000, 1000000);

		// Create test directory.
		$public_files_directory = $this->originalFileDirectory . '/simpletest/' . substr($this->databasePrefix, 10);
		simpletest_file_prepare_directory($public_files_directory, 1);

		// Replace MySQL prefix on DB instances.
		$this->originalMySQLPrefix = $mySQLprefix;
		// Change MySQL prefix on the global variable, which is set in e107_config.php file.
		$mySQLprefix = $this->databasePrefix;
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Get original instance ID.
				$id = str_replace('core/e107/singleton/db', '', $instance_id);
				// Change MySQL prefix.
				e107::getDb($id)->mySQLPrefix = $mySQLprefix;
			}
		}

		// Set user agent to be consistent with web test case.
		$_SERVER['HTTP_USER_AGENT'] = $this->databasePrefix;
	}

	protected function tearDown()
	{
		global $mySQLprefix;

		// Get back to the original connection prefix.
		// Change MySQL prefix on the global variable, which is set in e107_config.php file.
		$mySQLprefix = $this->originalMySQLPrefix;
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Get original instance ID.
				$id = str_replace('core/e107/singleton/db', '', $instance_id);
				// Change MySQL prefix.
				e107::getDb($id)->mySQLPrefix = $mySQLprefix;
			}
		}
	}

}


/**
 * Test case for typical e107 tests.
 */
class e107WebTestCase extends e107TestCase
{

	/**
	 * The original prefix for MySQL connections.
	 *
	 * @var string
	 */
	protected $originalMySQLPrefix;

	/**
	 * The current user logged in using the internal browser.
	 *
	 * @var bool
	 */
	protected $loggedInUser = false;

	/**
	 * Additional cURL options.
	 *
	 * e107WebTestCase itself never sets this but always obeys what is set.
	 */
	protected $additionalCurlOptions = array();

	/**
	 * Constructor for e107WebTestCase.
	 */
	function __construct($test_id = null)
	{
		parent::__construct($test_id);

		$this->skipClasses[__CLASS__] = true;
	}

	/**
	 * Generates a random database prefix, runs the install scripts on the prefixed database and enable the specified
	 * plugins. After installation many caches are flushed and the internal browser is setup so that the page requests
	 * will run on the new prefix. A temporary files directory is created with the same name as the database prefix.
	 *
	 * @param ...
	 *   List of modules to enable for the duration of the test. This can be either a single array or a variable number
	 *   of string arguments.
	 */
	protected function setUp()
	{
		global $mySQLprefix;

		// Generate a temporary prefixed database to ensure that tests have a clean starting point.
		$this->databasePrefix = 'simpletest' . mt_rand(1000, 1000000);

		$update = array(
			'data'  => array(
				'last_prefix' => $this->databasePrefix,
			),
			'WHERE' => 'test_id = ' . $this->testId,
		);

		e107::getDb()->update('simpletest_test_id', $update, false);

		// Replace MySQL prefix on DB instances.
		$this->originalMySQLPrefix = $mySQLprefix;
		// Change MySQL prefix on the global variable, which is set in e107_config.php file.
		$mySQLprefix = $this->databasePrefix;
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Get original instance ID.
				$id = str_replace('core/e107/singleton/db', '', $instance_id);
				// Change MySQL prefix.
				e107::getDb($id)->mySQLPrefix = $mySQLprefix;
			}
		}

		// Store necessary current values.
		// TODO...

		// Save and clean shutdown callbacks array because it static cached and will be changed by the test run.
		// If we don't, then it will contain callbacks from both environments.
		// So testing environment will try to call handlers from original environment.
		// TODO...

		// Create test directory ahead of installation so fatal errors and debug information can be logged during
		// installation process. Use temporary files directory with the same prefix as the database.
		$public_files_directory = $this->originalFileDirectory . '/simpletest/' . substr($this->databasePrefix, 10);
		$temp_files_directory = $public_files_directory . '/temp';

		// Create the directories.
		simpletest_file_prepare_directory($public_files_directory, 1);
		simpletest_file_prepare_directory($temp_files_directory, 1);

		// Log fatal errors.
		ini_set('log_errors', 1);
		ini_set('error_log', $public_files_directory . '/error.log');

		// Set the test information for use in other parts of e107.
		$test_info = &$GLOBALS['e107_test_info'];
		$test_info['test_run_id'] = $this->databasePrefix;
		$test_info['in_child_site'] = false;

		$this->setUpInstall(func_get_args(), $public_files_directory, $temp_files_directory);

		// Rebuild caches.
		// TODO...

		// Run cron once in that environment.
		// TODO...

		// Log in with a clean $user.
		// TODO...

		// Set up languages.
		// TODO...

		// Use a test mail class instead of the default mail handler class.
		// TODO...

		set_time_limit($this->timeLimit);
	}

	/**
	 * Perform e107 installation.
	 */
	protected function setUpInstall(array $plugins, $public_files_directory, $temp_files_directory)
	{
		// TODO...
	}

	/**
	 * Delete created files and temporary files directory, delete the tables created by setUp(), and reset the
	 * database prefix.
	 */
	protected function tearDown()
	{
		global $mySQLprefix;

		// In case a fatal error occurred that was not in the test process read the log to pick up any fatal errors.
		simpletest_log_read($this->testId, $this->databasePrefix, get_class($this), true);

		$emailCount = 0; // TODO...
		if($emailCount)
		{
			$message = '[x] e-mails were sent during this test.';
			$this->pass($message, 'E-mail');
		}

		// Delete temporary files directory.
		simpletest_file_delete_recursive($this->originalFileDirectory . '/simpletest/' . substr($this->databasePrefix, 10));

		// Remove all prefixed tables.
		// TODO...

		// Get back to the original connection prefix.
		// Change MySQL prefix on the global variable, which is set in e107_config.php file.
		$mySQLprefix = $this->originalMySQLPrefix;
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Get original instance ID.
				$id = str_replace('core/e107/singleton/db', '', $instance_id);
				// Change MySQL prefix.
				e107::getDb($id)->mySQLPrefix = $mySQLprefix;
			}
		}

		// Restore original shutdown callbacks array to prevent original environment of calling handlers from test run.
		// TODO...

		// Return the user to the original one.
		// TODO...

		// Ensure that internal logged in variable and cURL options are reset.
		$this->loggedInUser = false;
		$this->additionalCurlOptions = array();

		// Rebuild caches.
		// TODO...

		// Reset language.
		// TODO...

		// Close the CURL handler.
		// TODO...
	}

}


/**
 * Clone an existing database and use it for testing.
 */
class e107CloneTestCase extends e107WebTestCase
{

	// TODO...

}


/**
 * Gets the last caller from a backtrace.
 *
 * @param $backtrace
 *   A standard PHP backtrace.
 *
 * @return mixed
 *   An associative array with keys 'file', 'line' and 'function'.
 */
function simpletest_get_last_caller($backtrace)
{
// Errors that occur inside PHP internal functions do not generate information about file and line.
	// Ignore black listed functions.
	$blacklist = array('debug'); // TODO is there any?

	while(($backtrace && !isset($backtrace[0]['line'])) ||
		(isset($backtrace[1]['function']) && in_array($backtrace[1]['function'], $blacklist)))
	{
		array_shift($backtrace);
	}

	// The first trace is the call itself. It gives us the line and the file of the last call.
	$call = $backtrace[0];

	// The second call give us the function where the call originated.
	if(isset($backtrace[1]))
	{
		if(isset($backtrace[1]['class']))
		{
			$call['function'] = $backtrace[1]['class'] . $backtrace[1]['type'] . $backtrace[1]['function'] . '()';
		}
		else
		{
			$call['function'] = $backtrace[1]['function'] . '()';
		}
	}
	else
	{
		$call['function'] = 'main()';
	}

	return $call;
}


/**
 * Logs verbose message in a text file.
 *
 * If verbose mode is enabled then page requests will be dumped to a file and presented on the test result screen.
 * The messages will be placed in a file located in the simpletest directory in the original file system.
 *
 * @param $message
 *   The verbose message to be stored.
 * @param $original_file_directory
 *   The original file directory, before it was changed for testing purposes.
 * @param $test_class
 *   The active test case class.
 *
 * @return int
 *   The ID of the message to be placed in related assertion messages.
 *
 * @see e107TestCase->originalFileDirectory
 * @see e107WebTestCase->verbose()
 */
function simpletest_verbose($message, $original_file_directory = null, $test_class = null)
{
	static $file_directory = null, $class = null, $id = 1, $verbose = null;

	// Will pass first time during setup phase, and when verbose is TRUE.
	if(!isset($original_file_directory) && !$verbose)
	{
		return false;
	}

	if($message && $file_directory)
	{
		$message = '<hr />ID #' . $id . ' (<a href="' . $class . '-' . ($id - 1) . '.html">Previous</a> | <a href="' . $class . '-' . ($id + 1) . '.html">Next</a>)<hr />' . $message;
		file_put_contents($file_directory . "/simpletest/verbose/$class-$id.html", $message, FILE_APPEND);
		return $id++;
	}

	if($original_file_directory)
	{
		$prefs = e107::getPlugConfig('simpletest')->getPref();

		$file_directory = $original_file_directory;
		$class = $test_class;
		$verbose = isset($prefs['verbose']) ? $prefs['verbose'] : true;
		$directory = $file_directory . '/simpletest/verbose';
		$writable = simpletest_file_prepare_directory($directory, 1);

		if($writable && !file_exists($directory . '/.htaccess'))
		{
			file_put_contents($directory . '/.htaccess', "<IfModule mod_expires.c>\nExpiresActive Off\n</IfModule>\n");
		}

		return $writable;
	}

	return false;
}

/**
 * Checks that the directory exists and is writable.
 *
 * @param string $directory
 *   A string reference containing the name of a directory path or URI. A trailing slash will be trimmed from a path.
 * @param int $options
 *   A bitmask to indicate if the directory should be created if it does not exist (1) or made writable if it is
 *   read-only (2).
 *
 * @return bool
 *   TRUE if the directory exists (or was created) and is writable. FALSE otherwise.
 */
function simpletest_file_prepare_directory(&$directory, $options = 2)
{
	$directory = e107::getParser()->replaceConstants($directory);

	$directory = rtrim($directory, '/\\');

	// Check if directory exists.
	if(!is_dir($directory))
	{
		// Let mkdir() recursively create directories and use the default directory permissions.
		if(($options & 1) && @simpletest_mkdir($directory, null, true))
		{
			return simpletest_chmod($directory);
		}

		return false;
	}

	// The directory exists, so check to see if it is writable.
	$writable = is_writable($directory);

	if(!$writable && ($options & 2))
	{
		return simpletest_chmod($directory);
	}

	return $writable;
}

/**
 * Sets the permissions on a file or directory.
 *
 * @param string $path
 *   A string containing a file, or directory path.
 * @param int $mode
 *   Integer value for the permissions. Consult PHP chmod() documentation for more information.
 *
 * @return bool
 *   TRUE for success, FALSE in the event of an error.
 */
function simpletest_chmod($path, $mode = null)
{
	if(!isset($mode))
	{
		if(is_dir($path))
		{
			$mode = 0775;
		}
		else
		{
			$mode = 0664;
		}
	}

	if(@chmod($path, $mode))
	{
		return true;
	}

	return false;
}

/**
 * Creates a directory.
 *
 * Compatibility: normal paths and stream wrappers.
 *
 * @param string $path
 *   A string containing a file path.
 * @param int $mode
 *   Mode is used.
 * @param bool $recursive
 *   Default to FALSE.
 * @param null $context
 *   Refer to http://php.net/manual/ref.stream.php
 *
 * @return bool
 *   Boolean TRUE on success, or FALSE on failure.
 */
function simpletest_mkdir($path, $mode = null, $recursive = false, $context = null)
{
	if(!isset($mode))
	{
		$mode = 0775;
	}

	if(!isset($context))
	{
		return mkdir($path, $mode, $recursive);
	}
	else
	{
		return mkdir($path, $mode, $recursive, $context);
	}
}

/**
 * Deletes all files and directories in the specified filepath recursively.
 *
 * If the specified path is a directory then the function will call itself recursively to process the contents. Once
 * the contents have been removed the directory will also be removed.
 *
 * If the specified path is a file then it will be passed to simpletest_file_delete().
 *
 * Note that this only deletes visible files with write permission.
 *
 * @param string $path
 *   A string containing a file path.
 *
 * @return bool
 *   TRUE for success or if path does not exist, FALSE in the event of an error.
 */
function simpletest_file_delete_recursive($path)
{
	if(is_dir($path))
	{
		$dir = dir($path);

		while(($entry = $dir->read()) !== false)
		{
			if($entry == '.' || $entry == '..')
			{
				continue;
			}

			$entry_path = $path . '/' . $entry;

			simpletest_file_delete_recursive($entry_path);
		}

		$dir->close();

		return simpletest_rmdir($path);
	}

	return simpletest_file_delete($path);
}

/**
 * Deletes a file.
 *
 * @param string $path
 *   A string containing a file path.
 *
 * @return bool
 *   TRUE for success or path does not exist, or FALSE in the event of an error.
 */
function simpletest_file_delete($path)
{
	if(is_dir($path))
	{
		return false;
	}

	if(is_file($path))
	{
		return simpletest_unlink($path);
	}

	// Return TRUE for non-existent file, but log that nothing was actually deleted, as the current state is the
	// intended result.
	if(!file_exists($path))
	{
		return true;
	}

	// We cannot handle anything other than files and directories.
	return false;
}

/**
 * Deletes a file.
 *
 * @param string $path
 *   A string containing a file path.
 * @param null $context
 *   Refer to http://php.net/manual/ref.stream.php
 *
 * @return bool
 *   Boolean TRUE on success, or FALSE on failure.
 */
function simpletest_unlink($path, $context = null)
{
	if(substr(PHP_OS, 0, 3) == 'WIN')
	{
		chmod($path, 0600);
	}

	if($context)
	{
		return unlink($path, $context);
	}
	else
	{
		return unlink($path);
	}
}

/**
 * Removes a directory.
 *
 * PHP's rmdir() is broken on Windows, as it can fail to remove a directory when it has a read-only flag set.
 *
 * @param string $path
 *   A string containing a file path.
 * @param null $context
 *   Refer to http://php.net/manual/ref.stream.php
 *
 * @return bool
 *   Boolean TRUE on success, or FALSE on failure.
 */
function simpletest_rmdir($path, $context = null)
{
	if(substr(PHP_OS, 0, 3) == 'WIN')
	{
		chmod($path, 0700);
	}

	if($context)
	{
		return rmdir($path, $context);
	}
	else
	{
		return rmdir($path);
	}
}

/**
 * Decodes an exception and retrieves the correct caller.
 *
 * @param $exception
 *   The exception object that was thrown.
 *
 * @return array
 */
function simpletest_decode_exception($exception)
{
	$message = $exception->getMessage();

	$backtrace = $exception->getTrace();

	// Add the line throwing the exception to the backtrace.
	array_unshift($backtrace, array(
		'line' => $exception->getLine(),
		'file' => $exception->getFile(),
	));

	// For PDOException errors, we try to return the initial caller, skipping internal functions of the database layer.
	if($exception instanceof PDOException)
	{
		// The first element in the stack is the call, the second element gives us the caller.
		// We skip calls that occurred in one of the classes of the database layer or in one of its global functions.
		$db_functions = array('db_Query', 'db_Query_all', 'db_QueryCount');

		while(!empty($backtrace[1]) && ($caller = $backtrace[1]) &&
			((isset($caller['class']) && (strpos($caller['class'], 'db') !== false || strpos($caller['class'], 'e_db_mysql') !== false || strpos($caller['class'], 'PDO') !== false)) ||
				in_array($caller['function'], $db_functions)))
		{
			// We remove that call.
			array_shift($backtrace);
		}

		if(isset($exception->query_string, $exception->args))
		{
			$message .= ": " . $exception->query_string . "; " . print_r($exception->args, true);
		}
	}

	$caller = simpletest_get_last_caller($backtrace);

	return array(
		'type'     => get_class($exception),
		// The standard PHP exception handler considers that the exception message is plain-text.
		// We mimic this behavior here.
		'message'  => simpletest_check_plain($message),
		'function' => $caller['function'],
		'file'     => $caller['file'],
		'line'     => $caller['line'],
	);
}

/**
 * Encodes special characters in a plain-text string for display as HTML. Also validates strings as UTF-8 to prevent
 * cross site scripting attacks on Internet Explorer 6.
 *
 * @param string $text
 *   The text to be checked or processed.
 *
 * @return string
 *   An HTML safe version of $text. If $text is not valid UTF-8, an empty string is returned and, on PHP < 5.4,
 *   a warning may be issued depending on server configuration (see https://bugs.php.net/bug.php?id=47494).
 */
function simpletest_check_plain($text)
{
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Read the error log and report any errors as assertion failures. The errors in the log should only be fatal errors
 * since any other errors will have been recorded by the error handler.
 *
 * @param int $test_id
 *   The test ID to which the log relates.
 * @param string $prefix
 *   The database prefix to which the log relates.
 * @param $test_class
 *   The test class to which the log relates.
 * @param bool $during_test
 *   Indicates that the current file directory path is a temporary file directory used during testing.
 *
 * @return bool
 */
function simpletest_log_read($test_id, $prefix, $test_class, $during_test = false)
{
	// TODO replace ... with the current path for public files.
	$log = '...' . ($during_test ? '' : '/simpletest/' . substr($prefix, 10)) . '/error.log';

	$found = false;

	if(file_exists($log))
	{
		foreach(file($log) as $line)
		{
			if(preg_match('/\[.*?\] (.*?): (.*?) in (.*) on line (\d+)/', $line, $match))
			{
				// Parse PHP fatal errors for example: PHP Fatal error: Call to undefined function break_me() in
				// /path/to/file.php on line 17
				$caller = array(
					'line' => $match[4],
					'file' => $match[3],
				);

				e107TestCase::insertAssert($test_id, $test_class, false, $match[2], $match[1], $caller);
			}
			else
			{
				// Unknown format, place the entire message in the log.
				e107TestCase::insertAssert($test_id, $test_class, false, $line, 'Fatal error');
			}

			$found = true;
		}
	}

	return $found;
}
