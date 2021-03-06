<?php

/**
 * @file
 * Provides e107TestCase, e107UnitTestCase, and e107WebTestCase classes.
 */

e107_require_once(e_PLUGIN . 'simpletest/includes/helpers.php');
e107_require_once(e_PLUGIN . 'simpletest/includes/e107.php');

/**
 * Global variable that holds information about the tests being run.
 *
 * An array, with the following keys:
 *  - 'test_run_id': the ID of the test being run
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
	protected $originalSystemDirectory = null;

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
	protected function assert($status, $message = '', $group = 'Other', array $caller = null) // TODO lans.
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
		e107::getDb('SimpleTestE107TestCase')->insert('simpletest', $assertion, false);

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
	public static function insertAssert($test_id, $test_class, $status, $message = '', $group = 'Other', array $caller = array()) // TODO lans.
	{
		// Convert boolean status to string status.
		if(is_bool($status))
		{
			$status = $status ? 'pass' : 'fail';
		}

		$caller += array(
			'function' => 'Unknown', // TODO lans.
			'line'     => 0,
			'file'     => 'Unknown', // TODO lans.
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

		return e107::getDb('SimpleTestE107TestCase')->insert('simpletest', $assertion, false);
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
		return e107::getDb('SimpleTestE107TestCase')->delete('simpletest', 'message_id = ' . (int) $message_id, false);
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
		while(($caller = $backtrace[1]) && ((isset($caller['class']) && isset($this->skipClasses[$caller['class']])) || substr($caller['function'], 0, 6) == 'assert'))
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
	protected function assertTrue($value, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function assertFalse($value, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function assertNull($value, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function assertNotNull($value, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function assertEqual($first, $second, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function assertNotEqual($first, $second, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function assertIdentical($first, $second, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function assertNotIdentical($first, $second, $message = '', $group = 'Other') // TODO lans.
	{
		// TODO lans.
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
	protected function pass($message = null, $group = 'Other') // TODO lans.
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
	protected function fail($message = null, $group = 'Other') // TODO lans.
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
	protected function error($message = '', $group = 'Other', array $caller = null) // TODO lans.
	{
		if($group == 'User notice')
		{
			// Since 'User notice' is set by trigger_error() which is used for debug set the message to a status of
			// 'debug'.
			return $this->assert('debug', $message, 'Debug', $caller); // TODO lans... 'Debug'
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
		global $SYSTEM_DIRECTORY;

		if($id = simpletest_verbose($message))
		{
			$url = SITEURLBASE . '/' . $SYSTEM_DIRECTORY . 'simpletest/verbose/' . get_class($this) . '-' . $id . '.html';

			// TODO lans.
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

		$original_file_directory = rtrim(e_SYSTEM_BASE, '/');

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

				// TODO lans.
				$message = 'The test did not complete due to a fatal error.';

				$completion_check_id = e107TestCase::insertAssert($this->testId, $class, false, $message, 'Completion check', $caller); // TODO lans.

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
		e107::getMessage()->reset(false, false, true);
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
			// TODO lans.
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
		// TODO lans.
		$message = e107::getParser()->lanVars('[type]: [message] in [function] (line [line] of [file]).', array(
			'type'     => $decoded['type'],
			'message'  => $decoded['message'],
			'function' => $decoded['function'],
			'line'     => $decoded['line'],
			'file'     => $decoded['file'],
		));

		// TODO lans.
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
	 * @var
	 */
	protected $originalSiteHash;

	/**
	 * @var string
	 */
	protected $siteHash;

	/**
	 * Constructor for e107UnitTestCase.
	 */
	function __construct($test_id = null)
	{
		parent::__construct($test_id);

		global $mySQLprefix;

		// Replace MySQL prefix on DB instances.
		$this->originalMySQLPrefix = $mySQLprefix;
		$this->originalSiteHash = e107::getInstance()->site_path;

		$this->skipClasses[__CLASS__] = true;

		// Generate a temporary prefixed database to ensure that tests have a clean starting point.
		$this->databasePrefix = 'simpletest' . mt_rand(1000, 1000000);
		$this->siteHash = 'simpletest/' . substr($this->databasePrefix, 10);
	}

	/**
	 * Sets up unit test environment.
	 *
	 * Unlike e107WebTestCase::setUp(), e107UnitTestCase::setUp() does not install plugins because tests are performed
	 * without accessing the database. Any required files must be explicitly included by the child class setUp() method.
	 */
	protected function setUp()
	{
		// Get path for test (media) directory.
		$media_files_directory = rtrim(e_MEDIA_BASE, '/') . '/' . $this->siteHash;
		// Get path for test (system) directory.
		$system_files_directory = rtrim(e_SYSTEM_BASE, '/') . '/' . $this->siteHash;
		// Prepare directories.
		simpletest_file_prepare_directory($media_files_directory, 1);
		simpletest_file_prepare_directory($system_files_directory, 1);
		simpletest_rewrite_paths(array(
			$this->originalSiteHash => $this->siteHash,
		));

		// Replace table prefixes on DB instances in order to avoid any kind of data modification on the current DB.
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'SimpleTestE107TestCase') === false && strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Change MySQL prefix.
				$instance->mySQLPrefix = $this->databasePrefix . '_';
			}
		}
		simpletest_replace_constant('MPREFIX', $this->originalMySQLPrefix, $this->databasePrefix . '_');

		// Set user agent to be consistent with web test case.
		$_SERVER['HTTP_USER_AGENT'] = $this->databasePrefix;
	}

	protected function tearDown()
	{
		// Get back to the original connection prefix.
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'SimpleTestE107TestCase') === false && strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Change MySQL prefix.
				$instance->mySQLPrefix = $this->originalMySQLPrefix;
			}
		}
		simpletest_restore_constant('MPREFIX');

		// Get path for test (media) directory.
		$media_files_directory = rtrim(e_MEDIA_BASE, '/') . '/' . $this->siteHash;
		// Get path for test (system) directory.
		$system_files_directory = rtrim(e_SYSTEM_BASE, '/') . '/' . $this->siteHash;
		// Delete test files directories.
		simpletest_file_delete_recursive($media_files_directory);
		simpletest_file_delete_recursive($system_files_directory);
		simpletest_restore_paths();

		// Empty user agent.
		$_SERVER['HTTP_USER_AGENT'] = '';
	}

}


/**
 * Test case for typical e107 tests.
 */
class e107WebTestCase extends e107TestCase
{

	/**
	 * The new prefix for MySQL connections.
	 *
	 * @var string
	 */
	protected $databasePrefix;

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
	 * The current cookie file used by cURL.
	 *
	 * We do not reuse the cookies in further runs, so we do not need a file
	 * but we still need cookie handling, so we set the jar to NULL.
	 */
	protected $cookieFile = null;

	/**
	 * HTTP authentication method
	 */
	protected $httpauth_method = CURLAUTH_BASIC;

	/**
	 * HTTP authentication credentials (<username>:<password>).
	 */
	protected $httpauth_credentials = null;

	/**
	 * The handle of the current cURL connection.
	 *
	 * @var resource
	 */
	protected $curlHandle;

	/**
	 * The current session name, if available.
	 */
	protected $session_name = null;

	/**
	 * The current session ID, if available.
	 */
	protected $session_id = null;

	/**
	 * The headers of the page currently loaded in the internal browser.
	 *
	 * @var array
	 */
	protected $headers;

	/**
	 * @var
	 */
	protected $cookies;

	/**
	 * The number of redirects followed during the handling of a request.
	 */
	protected $redirect_count;

	/**
	 * The URL currently loaded in the internal browser.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * The content of the page currently loaded in the internal browser.
	 *
	 * @var string
	 */
	protected $content;

	/**
	 * The content of the page currently loaded in the internal browser (plain text version).
	 *
	 * @var string
	 */
	protected $plainTextContent;

	/**
	 * The value of the e107.settings JavaScript variable for the page currently loaded in the internal browser.
	 *
	 * @var array
	 */
	protected $e107Settings;

	/**
	 * The parsed version of the page.
	 *
	 * @var SimpleXMLElement
	 */
	protected $elements = null;

	/**
	 * @var
	 */
	protected $originalSiteHash;

	/**
	 * @var string
	 */
	protected $siteHash;

	/**
	 * @var bool
	 */
	protected $removeTables;

	/**
	 * @var SimpleTestE107
	 */
	protected $e107site;

	/**
	 * Constructor for e107WebTestCase.
	 */
	function __construct($test_id = null)
	{
		parent::__construct($test_id);

		global $mySQLprefix;

		// Replace MySQL prefix on DB instances.
		$this->originalMySQLPrefix = $mySQLprefix;

		$prefs = e107::getPlugConfig('simpletest')->getPref();

		$this->skipClasses[__CLASS__] = true;
		$this->originalSiteHash = e107::getInstance()->site_path;
		$this->removeTables = isset($prefs['remove_tables']) ? (bool) $prefs['remove_tables'] : true;

		// Generate a temporary prefixed database to ensure that tests have a clean starting point.
		$this->databasePrefix = 'simpletest' . mt_rand(1000, 1000000);
		$this->siteHash = 'simpletest/' . substr($this->databasePrefix, 10);
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
		$update = array(
			'last_prefix' => $this->databasePrefix,
			'WHERE'       => 'test_id = ' . $this->testId,
		);
		e107::getDb('SimpleTestE107TestCase')->update('simpletest_test_id', $update, false);

		// Replace table prefixes on DB instances in order to avoid any kind of data modification on the current DB.
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'SimpleTestE107TestCase') === false && strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Change MySQL prefix.
				$instance->mySQLPrefix = $this->databasePrefix . '_';
			}
		}
		simpletest_replace_constant('MPREFIX', $this->originalMySQLPrefix, $this->databasePrefix . '_');

		// Get path for test (media) directory.
		$media_files_directory = rtrim(e_MEDIA_BASE, '/') . '/' . $this->siteHash;
		// Get path for test (system) directory.
		$system_files_directory = rtrim(e_SYSTEM_BASE, '/') . '/' . $this->siteHash;
		// Prepare directories.
		simpletest_file_prepare_directory($media_files_directory, 1);
		simpletest_file_prepare_directory($system_files_directory, 1);
		simpletest_rewrite_paths(array(
			$this->originalSiteHash => $this->siteHash,
		));

		// TODO Log fatal errors.
		// ini_set('log_errors', 1);
		// ini_set('error_log', $system_files_directory . '/error.log');

		// Set the test information for use in other parts of e107.
		$test_info = &$GLOBALS['e107_test_info'];
		$test_info['test_run_id'] = $this->databasePrefix;

		// At this point, MPREFIX and other, path-specific constants are replaced along with MySQL prefixes
		// for DB instances, so we use the "test" website's database now.
		// The next step is resetting e107::getConfig() object before installing the "test" website, because
		// e107::getConfig() object is still containing the data for the "test runner" website.
		e107::getConfig()->reset();

		$this->setUpInstall(func_get_args());

		set_time_limit($this->timeLimit);
	}

	/**
	 * Perform e107 installation.
	 */
	protected function setUpInstall(array $plugins)
	{
		// Install plugins needed for this test. This could have been passed in as either a single array argument
		// or a variable number of string arguments.
		if(isset($plugins[0]) && is_array($plugins[0]))
		{
			$plugins = $plugins[0];
		}

		$config = array(
			'theme'          => 'bootstrap3',
			'sitename'       => 'My Test Website',
			'sitelanguage'   => 'English',
			'sitelang_init'  => 'English',
			'siteadmin'      => 'Administrator',
			'siteadminuser'  => 'admin',
			'siteadminpass'  => 'admin',
			'siteadminemail' => 'admin@simpletest.dev',
			'replyto_name'   => 'Administrator',
			'replyto_email'  => 'admin@simpletest.dev',
			'sitetag'        => 'e107 Website System',
			'siteurl'        => e_HTTP,
			'sitedisclaimer' => '',
			'install_date'   => time(),
		);

		$this->e107site = new SimpleTestE107($this->databasePrefix . '_', $this->siteHash);
		$this->e107site->createTables();
		$this->e107site->importDefaultConfig($config);

		foreach($plugins as $plugin)
		{
			$this->e107site->installPlugin($plugin, true);
		}
	}

	/**
	 * Delete created files and temporary files directory, delete the tables created by setUp(), and reset the
	 * database prefix.
	 */
	protected function tearDown()
	{
		global $mySQLdefaultdb;

		// In case a fatal error occurred that was not in the test process read the log to pick up any fatal errors.
		simpletest_log_read($this->testId, $this->databasePrefix, get_class($this), true);

		$emailCount = 0; // TODO...
		if($emailCount)
		{
			// TODO lans.
			$message = '[x] e-mails were sent during this test.';
			$this->pass($message, 'E-mail');
		}

		// Get path for test (media) directory.
		$media_files_directory = rtrim(e_MEDIA_BASE, '/') . '/' . $this->siteHash;
		// Get path for test (system) directory.
		$system_files_directory = rtrim(e_SYSTEM_BASE, '/') . '/' . $this->siteHash;
		// Delete test files directories.
		simpletest_file_delete_recursive($media_files_directory);
		simpletest_file_delete_recursive($system_files_directory);
		simpletest_restore_paths();

		// Remove all prefixed tables.
		if($this->removeTables)
		{
			$sql = e107::getDb('SimpleTestE107TestCase');
			$sql->gen("SELECT table_name FROM information_schema.tables WHERE table_schema='" . $mySQLdefaultdb . "' AND table_name LIKE 'simpletest%'");

			$tables = array();
			while($row = $sql->fetch())
			{
				$tables[] = $row['table_name'];
			}

			if(!empty($tables))
			{
				$sql->gen("DROP TABLE " . implode(', ', $tables));
			}
		}
		// Restore the original connection prefix.
		// Get all registered instances.
		$instances = e107::getRegistry('_all_');
		// Find DB instances and replace MySQL prefix on them.
		foreach($instances as $instance_id => $instance)
		{
			// If the instance is a DB instance.
			if(strpos($instance_id, 'SimpleTestE107TestCase') === false && strpos($instance_id, 'core/e107/singleton/db') === 0)
			{
				// Change MySQL prefix.
				$instance->mySQLPrefix = $this->originalMySQLPrefix;
			}
		}
		simpletest_restore_constant('MPREFIX');

		// Ensure that internal logged in variable and cURL options are reset.
		$this->loggedInUser = false;
		$this->additionalCurlOptions = array();

		// Close the CURL handler.
		$this->curlClose();

		// Reset e107::getConfig() object in order to remove the config belongs to the "test" website.
		e107::getConfig()->reset();
	}

	/**
	 * Initializes the cURL connection.
	 *
	 * If the httpauth_credentials variable is set, this function will add HTTP authentication headers.
	 * This is necessary for testing sites that are protected by login credentials from public access.
	 * See the description of $curl_options for other options.
	 */
	protected function curlInitialize()
	{
		global $base_url;

		if(!isset($this->curlHandle))
		{
			$this->curlHandle = curl_init();

			$curl_options = array(
				CURLOPT_COOKIEJAR      => $this->cookieFile,
				CURLOPT_URL            => $base_url,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false, // Required to make the tests run on https.
				CURLOPT_SSL_VERIFYHOST => false, // Required to make the tests run on https.
				CURLOPT_HEADERFUNCTION => array(&$this, 'curlHeaderCallback'),
				CURLOPT_USERAGENT      => $this->databasePrefix,
			);

			if(isset($this->httpauth_credentials))
			{
				$curl_options[CURLOPT_HTTPAUTH] = $this->httpauth_method;
				$curl_options[CURLOPT_USERPWD] = $this->httpauth_credentials;
			}

			curl_setopt_array($this->curlHandle, $this->additionalCurlOptions + $curl_options);

			// By default, the child session name should be the same as the parent.
			$this->session_name = session_name();
		}

		// We set the user agent header on each request so as to use the current time and a new unique ID.
		if(preg_match('/simpletest\d+/', $this->databasePrefix, $matches))
		{
			curl_setopt($this->curlHandle, CURLOPT_USERAGENT, simpletest_generate_test_ua($matches[0]));
		}
	}

	/**
	 * Initializes and executes a cURL request.
	 *
	 * @param $curl_options
	 *   An associative array of cURL options to set, where the keys are constants defined by the cURL library.
	 *   For a list of valid options, see http://www.php.net/manual/function.curl-setopt.php
	 * @param bool $redirect
	 *   FALSE if this is an initial request, TRUE if this request is the result of a redirect.
	 *
	 * @return string
	 *   The content returned from the call to curl_exec().
	 *
	 * @see curlInitialize()
	 */
	protected function curlExec($curl_options, $redirect = false)
	{
		$this->curlInitialize();

		$curl_options[CURLOPT_HTTPHEADER][] = 'SIMPLETEST_PREFIX: ' . $this->databasePrefix;

		// cURL incorrectly handles URLs with a fragment by including the fragment in the request to the server,
		// causing some web servers to reject the request citing "400 - Bad Request". To prevent this, we strip the
		// fragment from the request.
		// TODO: Remove this, since fixed in curl 7.20.0.
		if(!empty($curl_options[CURLOPT_URL]) && strpos($curl_options[CURLOPT_URL], '#'))
		{
			$original_url = $curl_options[CURLOPT_URL];
			$curl_options[CURLOPT_URL] = strtok($curl_options[CURLOPT_URL], '#');
		}

		$url = empty($curl_options[CURLOPT_URL]) ? curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL) : $curl_options[CURLOPT_URL];

		if(!empty($curl_options[CURLOPT_POST]))
		{
			// This is a fix for the Curl library to prevent Expect: 100-continue headers in POST requests, that may
			// cause unexpected HTTP response codes from some webservers (like lighttpd that returns a 417 error code).
			// It is done by setting an empty "Expect" header field that is not overwritten by Curl.
			$curl_options[CURLOPT_HTTPHEADER][] = 'Expect:';
		}
		curl_setopt_array($this->curlHandle, $this->additionalCurlOptions + $curl_options);

		if(!$redirect)
		{
			// Reset headers, the session ID and the redirect counter.
			$this->session_id = null;
			$this->headers = array();
			$this->redirect_count = 0;
		}

		$content = curl_exec($this->curlHandle);
		$status = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

		$maximum_redirects = 5;

		// cURL incorrectly handles URLs with fragments, so instead of letting cURL handle redirects we take of them
		// ourselves to to prevent fragments being sent to the web server as part of the request.
		// TODO: Remove this, since fixed in curl 7.20.0.
		if(in_array($status, array(300, 301, 302, 303, 305, 307)) && $this->redirect_count < $maximum_redirects)
		{
			if($this->e107GetHeaders('location'))
			{
				$this->redirect_count++;
				$curl_options = array();
				$curl_options[CURLOPT_URL] = $this->e107GetHeaders('location');
				$curl_options[CURLOPT_HTTPGET] = true;
				return $this->curlExec($curl_options, true);
			}
		}

		$this->e107SetContent($content, isset($original_url) ? $original_url : curl_getinfo($this->curlHandle, CURLINFO_EFFECTIVE_URL));
		$message_vars = array(
			'x' => !empty($curl_options[CURLOPT_NOBODY]) ? 'HEAD' : (empty($curl_options[CURLOPT_POSTFIELDS]) ? 'GET' : 'POST'),
			'y' => isset($original_url) ? $original_url : $url,
			'w' => $status,
			'z' => strlen($this->e107GetContent()),
		);
		// TODO lans.
		$message = e107::getParser()->lanVars('[x] [y] returned [w] ([z]).', $message_vars);
		$this->assertTrue($this->e107GetContent() !== false, $message, 'Browser'); // TODO lans.
		return $this->e107GetContent();
	}

	/**
	 * Reads headers and registers errors received from the tested site.
	 *
	 * @param $curlHandler
	 *   The cURL handler.
	 * @param $header
	 *   An header.
	 *
	 * @return int
	 */
	protected function curlHeaderCallback($curlHandler, $header)
	{
		$this->headers[] = $header;

		// Errors are being sent via X-E107-Assertion-* headers, generated in the exact form required by
		// e107WebTestCase::error().
		if(preg_match('/^X-E107-Assertion-[0-9]+: (.*)$/', $header, $matches))
		{
			// Call e107WebTestCase::error() with the parameters from the header.
			call_user_func_array(array(&$this, 'error'), unserialize(urldecode($matches[1])));
		}

		// Save cookies.
		if(preg_match('/^Set-Cookie: ([^=]+)=(.+)/', $header, $matches))
		{
			$name = $matches[1];
			$parts = array_map('trim', explode(';', $matches[2]));
			$value = array_shift($parts);
			$this->cookies[$name] = array('value' => $value, 'secure' => in_array('secure', $parts));
			if($name == $this->session_name)
			{
				if($value != 'deleted')
				{
					$this->session_id = $value;
				}
				else
				{
					$this->session_id = null;
				}
			}
		}

		// This is required by cURL.
		return strlen($header);
	}

	/**
	 * Close the cURL handler and unset the handler.
	 */
	protected function curlClose()
	{
		if(isset($this->curlHandle))
		{
			curl_close($this->curlHandle);
			unset($this->curlHandle);
		}
	}

	/**
	 * Gets the HTTP response headers of the requested page. Normally we are only interested in the headers returned
	 * by the last request. However, if a page is redirected or HTTP authentication is in use, multiple requests will
	 * be required to retrieve the page. Headers from all requests may be requested by passing TRUE to this function.
	 *
	 * @param $all_requests
	 *   Boolean value specifying whether to return headers from all requests instead of just the last request.
	 *   Defaults to FALSE.
	 *
	 * @return string
	 *   A name/value array if headers from only the last request are requested.
	 *   If headers from all requests are requested, an array of name/value arrays, one for each request.
	 *
	 *   The pseudonym ":status" is used for the HTTP status line.
	 *   Values for duplicate headers are stored as a single comma-separated list.
	 */
	protected function e107GetHeaders($all_requests = false)
	{
		$request = 0;
		$headers = array($request => array());
		foreach($this->headers as $header)
		{
			$header = trim($header);
			if($header === '')
			{
				$request++;
			}
			else
			{
				if(strpos($header, 'HTTP/') === 0)
				{
					$name = ':status';
					$value = $header;
				}
				else
				{
					list($name, $value) = explode(':', $header, 2);
					$name = strtolower($name);
				}
				if(isset($headers[$request][$name]))
				{
					$headers[$request][$name] .= ',' . trim($value);
				}
				else
				{
					$headers[$request][$name] = trim($value);
				}
			}
		}
		if(!$all_requests)
		{
			$headers = array_pop($headers);
		}
		return $headers;
	}

	/**
	 * Gets the value of an HTTP response header. If multiple requests were required to retrieve the page, only the
	 * headers from the last request will be checked by default. However, if TRUE is passed as the second argument,
	 * all requests will be processed from last to first until the header is found.
	 *
	 * @param $name
	 *   The name of the header to retrieve. Names are case-insensitive (see RFC 2616 section 4.2).
	 * @param $all_requests
	 *   Boolean value specifying whether to check all requests if the header is not found in the last request.
	 * Defaults to FALSE.
	 *
	 * @return string
	 *   The HTTP header value or FALSE if not found.
	 */
	protected function e107GetHeader($name, $all_requests = false)
	{
		$name = strtolower($name);
		$header = false;
		if($all_requests)
		{
			foreach(array_reverse($this->e107GetHeaders(true)) as $headers)
			{
				if(isset($headers[$name]))
				{
					$header = $headers[$name];
					break;
				}
			}
		}
		else
		{
			$headers = $this->e107GetHeaders();
			if(isset($headers[$name]))
			{
				$header = $headers[$name];
			}
		}
		return $header;
	}

	/**
	 * Gets the current raw HTML of requested page.
	 */
	protected function e107GetContent()
	{
		return $this->content;
	}

	/**
	 * Gets the value of the e107.settings JavaScript variable for the currently loaded page.
	 */
	protected function e107GetSettings()
	{
		return $this->e107Settings;
	}

	/**
	 * Sets the raw HTML content. This can be useful when a page has been fetched outside of the internal browser
	 * and assertions need to be made on the returned page.
	 */
	protected function e107SetContent($content, $url = '')
	{
		$this->content = $content;
		$this->url = $url;
		$this->plainTextContent = false;
		$this->elements = false;
		$this->e107Settings = array();

		if(preg_match('/jQuery\.extend\(e107\.settings, (.*?)\);/', $content, $matches))
		{
			$this->e107Settings = e107::getParser()->toJSON($matches[1]);
		}
	}

	/**
	 * Sets the value of the e107.settings JavaScript variable for the currently loaded page.
	 */
	protected function e107SetSettings($settings)
	{
		$this->e107Settings = $settings;
	}

	/**
	 * Parse content returned from curlExec using DOM and SimpleXML.
	 *
	 * @return SimpleXMLElement|boolean
	 *   A SimpleXMLElement or FALSE on failure.
	 */
	protected function parse()
	{
		if(!$this->elements)
		{
			// DOM can load HTML soup. But, HTML soup can throw warnings, suppress them.
			$htmlDom = new DOMDocument();
			@$htmlDom->loadHTML($this->e107GetContent());

			if($htmlDom)
			{
				// TODO lans.
				$message = e107::getParser()->lanVars('Valid HTML found on "[x]"', array('x' => $this->getUrl()));
				$this->pass($message, 'Browser'); // TODO lans.
				// It's much easier to work with simplexml than DOM, luckily enough
				// we can just simply import our DOM tree.
				$this->elements = simplexml_import_dom($htmlDom);
			}
		}
		if(!$this->elements)
		{
			// TODO lans.
			$this->fail('Parsed page successfully.', 'Browser');
		}

		return $this->elements;
	}

	/**
	 * Retrieves an e107 path or an absolute path.
	 *
	 * @param $url
	 *   URL to load into internal browser.
	 * @param $headers
	 *   An array containing additional HTTP request headers, each formatted as "name: value".
	 *
	 * @return string
	 *   The retrieved HTML string, also available as $this->e107GetContent()
	 */
	protected function e107Get($url, array $headers = array())
	{
		$options['absolute'] = true;

		// We re-using a CURL connection here. If that connection still has certain options set, it might change
		// the GET into a POST. Make sure we clear out previous options.
		$out = $this->curlExec(array(
			CURLOPT_HTTPGET    => true,
			CURLOPT_URL        => $url,
			CURLOPT_NOBODY     => false,
			CURLOPT_HTTPHEADER => $headers,
		));

		// Replace original page output with new output from redirected page(s).
		if($new = $this->checkForMetaRefresh())
		{
			$out = $new;
		}
		$this->verbose('GET request to: ' . $url .
			'<hr />Ending URL: ' . $this->getUrl() .
			'<hr />' . $out);
		return $out;
	}

	/**
	 * Execute a POST request on an e107 page. It will be done as usual POST request with SimpleBrowser.
	 *
	 * @param $url
	 *   Location of the post form. URL or NULL to post to the current page.
	 *   For multi-stage forms you can set the path to NULL and have it post to the last received page. Example:
	 * @code
	 *     // First step in form.
	 *     $edit = array(...);
	 *     $this->e107Post('some_url', $edit, 'Save');
	 *
	 *     // Second step in form.
	 *     $edit = array(...);
	 *     $this->e107Post(NULL, $edit, 'Save');
	 * @endcode
	 * @param  $edit
	 *   Field data in an associative array. Changes the current input fields (where possible) to the values
	 *   indicated. A checkbox can be set to TRUE to be checked and FALSE to be unchecked. Note that when a form
	 *   contains file upload fields, other fields cannot start with the '@' character.
	 *
	 *   Multiple select fields can be set using name[] and setting each of the possible values. Example:
	 * @code
	 *     $edit = array();
	 *     $edit['name[]'] = array('value1', 'value2');
	 * @endcode
	 * @param $submit
	 *   Value of the submit button whose click is to be emulated. For example, 'Save'. The processing of the
	 *   request depends on this value. For example, a form may have one button with the value 'Save' and another
	 *   button with the value 'Delete', and execute different code depending on which one is clicked.
	 *   This can also be set to NULL in order to emulate an Internet Explorer submission of a form with a single
	 *   text field, and pressing ENTER in that textfield: under these conditions, no button information is added
	 *   to the POST data.
	 * @param $headers
	 *   An array containing additional HTTP request headers, each formatted as "name: value".
	 * @param $form_html_id
	 *   (optional) HTML ID of the form to be submitted. On some pages there are many identical forms, so just
	 *   using the value of the submit button is not enough.
	 * @param $extra_post
	 *   (optional) A string of additional data to append to the POST submission. This can be used to add POST data
	 *   for which there are no HTML fields. This string is literally appended to the POST data, so it must already
	 *   be urlencoded and contain a leading "&" (e.g., "&extra_var1=hello+world&extra_var2=you%26me").
	 *
	 * @return string
	 */
	protected function e107Post($url, $edit, $submit, array $headers = array(), $form_html_id = null, $extra_post = null)
	{
		$submit_matches = false;

		if(isset($url))
		{
			$this->e107Get($url);
		}

		if($this->parse())
		{
			$edit_save = $edit;
			// Let's iterate over all the forms.
			$xpath = "//form";

			if(!empty($form_html_id))
			{
				$xpath .= "[@id='" . $form_html_id . "']";
			}

			$forms = $this->xpath($xpath);

			foreach($forms as $form)
			{
				// We try to set the fields of this form as specified in $edit.
				$edit = $edit_save;
				$post = array();
				$upload = array();
				$submit_matches = $this->handleForm($post, $edit, $upload, $submit, $form);
				$action = isset($form['action']) ? $this->getAbsoluteUrl((string) $form['action']) : $this->getUrl();

				// We post only if we managed to handle every field in edit and the submit button matches.
				if(!$edit && ($submit_matches || !isset($submit)))
				{
					$post_array = $post;
					if($upload)
					{
						// TODO: cURL handles file uploads for us, but the implementation is broken.
						foreach($upload as $key => $file)
						{
							$file = realpath($file); // FIXME
							if($file && is_file($file))
							{
								$post[$key] = '@' . $file;
							}
						}
					}
					else
					{
						foreach($post as $key => $value)
						{
							// Encode according to application/x-www-form-urlencoded
							// Both names and values needs to be urlencoded, according to
							// http://www.w3.org/TR/html4/interact/forms.html#h-17.13.4.1
							$post[$key] = urlencode($key) . '=' . urlencode($value);
						}
						$post = implode('&', $post) . $extra_post;
					}

					$out = $this->curlExec(array(
						CURLOPT_URL        => $action,
						CURLOPT_POST       => true,
						CURLOPT_POSTFIELDS => $post,
						CURLOPT_HTTPHEADER => $headers,
					));

					// Replace original page output with new output from redirected page(s).
					if($new = $this->checkForMetaRefresh())
					{
						$out = $new;
					}

					$this->verbose('POST request to: ' . $url .
						'<hr />Ending URL: ' . $this->getUrl() .
						'<hr />Fields: ' . highlight_string('<?php ' . var_export($post_array, true), true) .
						'<hr />' . $out);

					return $out;
				}
			}

			// We have not found a form which contained all fields of $edit.
			foreach($edit as $name => $value)
			{
				// TODO lans.
				$message = e107::getParser()->lanVars('Failed to set field [x] to [y]', array(
					'x' => $name,
					'y' => $value,
				));
				$this->fail($message);
			}

			if(isset($submit))
			{
				// TODO lans.
				$message = e107::getParser()->lanVars('Found the [x] button', array('x' => $submit));
				$this->assertTrue($message);
			}

			// TODO lans.
			$message = e107::getParser()->lanVars('Found the requested form fields at [x]', array('x' => $url));
			$this->fail($message);
		}
	}

	/**
	 * Check for meta refresh tag and if found call e107Get() recursively. This function looks for the http-equiv
	 * attribute to be set to "Refresh" and is case-sensitive.
	 *
	 * @return boolean
	 *   Either the new page content or FALSE.
	 */
	protected function checkForMetaRefresh()
	{
		if(strpos($this->e107GetContent(), '<meta ') && $this->parse())
		{
			$refresh = $this->xpath('//meta[@http-equiv="Refresh"]');

			if(!empty($refresh))
			{
				// Parse the content attribute of the meta tag for the format:
				// "[delay]: URL=[page_to_redirect_to]".
				if(preg_match('/\d+;\s*URL=(?P<url>.*)/i', $refresh[0]['content'], $match))
				{
					$decoded = html_entity_decode($match['url'], ENT_QUOTES, 'UTF-8');
					return $this->e107Get($this->getAbsoluteUrl($decoded));
				}
			}
		}

		return false;
	}

	/**
	 * Perform an xpath search on the contents of the internal browser. The search is relative to the root
	 * element (HTML tag normally) of the page.
	 *
	 * @param $xpath
	 *   The xpath string to use in the search.
	 * @param array $arguments
	 *   Arguments for buildXPathQuery().
	 *
	 * @return array|bool
	 *   The return value of the xpath search. For details on the xpath string format and return values see
	 *   the SimpleXML documentation: http://us.php.net/manual/function.simplexml-element-xpath.php.
	 */
	protected function xpath($xpath, array $arguments = array())
	{
		if($this->parse())
		{
			$xpath = $this->buildXPathQuery($xpath, $arguments);
			$result = $this->elements->xpath($xpath);
			// Some combinations of PHP / libxml versions return an empty array instead of the documented FALSE.
			// Forcefully convert any falsish values to an empty array to allow foreach(...) constructions.
			return $result ? $result : array();
		}
		else
		{
			return false;
		}
	}

	/**
	 * Builds an XPath query.
	 *
	 * Builds an XPath query by replacing placeholders in the query by the value of the arguments.
	 *
	 * XPath 1.0 (the version supported by libxml2, the underlying XML library used by PHP) doesn't support any
	 * form of quotation. This function simplifies the building of XPath expression.
	 *
	 * @param $xpath
	 *   An XPath query, possibly with placeholders in the form ':name'.
	 * @param $args
	 *   An array of arguments with keys in the form ':name' matching the placeholders in the query.
	 *   The values may be either strings or numeric values.
	 *
	 * @return string
	 *   An XPath query with arguments replaced.
	 */
	protected function buildXPathQuery($xpath, array $args = array())
	{
		// Replace placeholders.
		foreach($args as $placeholder => $value)
		{
			// XPath 1.0 doesn't support a way to escape single or double quotes in a string literal.
			// We split double quotes out of the string, and encode them separately.
			if(is_string($value))
			{
				// Explode the text at the quote characters.
				$parts = explode('"', $value);

				// Quote the parts.
				foreach($parts as &$part)
				{
					$part = '"' . $part . '"';
				}

				// Return the string.
				$value = count($parts) > 1 ? 'concat(' . implode(', \'"\', ', $parts) . ')' : $parts[0];
			}

			$xpath = preg_replace('/' . preg_quote($placeholder) . '\b/', $value, $xpath);
		}

		return $xpath;
	}

	/**
	 * Handle form input related to e107Post(). Ensure that the specified fields exist and attempt to create POST
	 * data in the correct manner for the particular field type.
	 *
	 * @param $post
	 *   Reference to array of post values.
	 * @param $edit
	 *   Reference to array of edit values to be checked against the form.
	 * @param $upload
	 * @param $submit
	 *   Form submit button value.
	 * @param $form
	 *   Array of form elements.
	 *
	 * @return bool
	 *   Submit value matches a valid submit input in the form.
	 */
	protected function handleForm(&$post, &$edit, &$upload, $submit, $form)
	{
		// Retrieve the form elements.
		$elements = $form->xpath('.//input[not(@disabled)]|.//textarea[not(@disabled)]|.//select[not(@disabled)]|.//button[not(@disabled)]');
		$submit_matches = false;

		foreach($elements as $element)
		{
			// SimpleXML objects need string casting all the time.
			$name = (string) $element['name'];
			// This can either be the type of <input> or the name of the tag itself
			// for <select> or <textarea>.
			$type = isset($element['type']) ? (string) $element['type'] : $element->getName();
			$value = isset($element['value']) ? (string) $element['value'] : '';
			$done = false;

			if(isset($edit[$name]))
			{
				switch($type)
				{
					case 'text':
					case 'textarea':
					case 'hidden':
					case 'password':
						$post[$name] = $edit[$name];
						unset($edit[$name]);
						break;
					case 'radio':
						if($edit[$name] == $value)
						{
							$post[$name] = $edit[$name];
							unset($edit[$name]);
						}
						break;
					case 'checkbox':
						// To prevent checkbox from being checked.pass in a FALSE,
						// otherwise the checkbox will be set to its value regardless
						// of $edit.
						if($edit[$name] === false)
						{
							unset($edit[$name]);
							continue 2;
						}
						else
						{
							unset($edit[$name]);
							$post[$name] = $value;
						}
						break;
					case 'select':
						$new_value = $edit[$name];
						$options = $this->getAllOptions($element);
						if(is_array($new_value))
						{
							// Multiple select box.
							if(!empty($new_value))
							{
								$index = 0;
								$key = preg_replace('/\[\]$/', '', $name);
								foreach($options as $option)
								{
									$option_value = (string) $option['value'];
									if(in_array($option_value, $new_value))
									{
										$post[$key . '[' . $index++ . ']'] = $option_value;
										$done = true;
										unset($edit[$name]);
									}
								}
							}
							else
							{
								// No options selected: do not include any POST data for the
								// element.
								$done = true;
								unset($edit[$name]);
							}
						}
						else
						{
							// Single select box.
							foreach($options as $option)
							{
								if($new_value == $option['value'])
								{
									$post[$name] = $new_value;
									unset($edit[$name]);
									$done = true;
									break;
								}
							}
						}
						break;
					case 'file':
						$upload[$name] = $edit[$name];
						unset($edit[$name]);
						break;
				}
			}

			if(!isset($post[$name]) && !$done)
			{
				switch($type)
				{
					case 'textarea':
						$post[$name] = (string) $element;
						break;
					case 'select':
						$single = empty($element['multiple']);
						$first = true;
						$index = 0;
						$key = preg_replace('/\[\]$/', '', $name);
						$options = $this->getAllOptions($element);
						foreach($options as $option)
						{
							// For single select, we load the first option, if there is a
							// selected option that will overwrite it later.
							if($option['selected'] || ($first && $single))
							{
								$first = false;
								if($single)
								{
									$post[$name] = (string) $option['value'];
								}
								else
								{
									$post[$key . '[' . $index++ . ']'] = (string) $option['value'];
								}
							}
						}
						break;
					case 'file':
						break;
					case 'submit':
					case 'image':
						if(isset($submit) && $submit == $value)
						{
							$post[$name] = $value;
							$submit_matches = true;
						}
						break;
					case 'radio':
					case 'checkbox':
						if(!isset($element['checked']))
						{
							break;
						}
					// Deliberate no break.
					default:
						$post[$name] = $value;
				}
			}
		}

		return $submit_matches;
	}

	/**
	 * Pass if a link with the specified label is found, and optional with the specified index.
	 *
	 * @param $label
	 *   Text between the anchor tags.
	 * @param $index
	 *   Link position counting from zero.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertLink($label, $index = 0, $message = '', $group = 'Other') // TODO lans.
	{
		$links = $this->xpath('//a[normalize-space(text())=:label]|//a[.//*[normalize-space(text())=:label]]', array(
			':label' => $label,
		));

		// TODO lans.
		$message = ($message ? $message : e107::getParser()->lanVars('Link with label [x] found.', array(
			'x' => $label,
		)));

		return $this->assert(isset($links[$index]), $message, $group);
	}

	/**
	 * Pass if a link with the specified label is not found.
	 *
	 * @param $label
	 *   Text between the anchor tags.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertNoLink($label, $message = '', $group = 'Other') // TODO lans.
	{
		$links = $this->xpath('//a[normalize-space(text())=:label]|//a[.//*[normalize-space(text())=:label]]', array(
			':label' => $label,
		));

		// TODO lans.
		$message = ($message ? $message : e107::getParser()->lanVars('Link with label [x] not found.', array(
			'x' => $label,
		)));

		return $this->assert(empty($links), $message, $group);
	}

	/**
	 * Pass if a link containing a given href (part) is found.
	 *
	 * @param $href
	 *   The full or partial value of the 'href' attribute of the anchor tag.
	 * @param $index
	 *   Link position counting from zero.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertLinkByHref($href, $index = 0, $message = '', $group = 'Other') // TODO lans.
	{
		$links = $this->xpath('//a[contains(@href, :href)]', array(':href' => $href));

		// TODO lans.
		$message = ($message ? $message : e107::getParser()->lanVars('Link containing href [x] found.', array(
			'x' => $href,
		)));

		return $this->assert(isset($links[$index]), $message, $group);
	}

	/**
	 * Pass if a link containing a given href (part) is not found.
	 *
	 * @param $href
	 *   The full or partial value of the 'href' attribute of the anchor tag.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE if the assertion succeeded, FALSE otherwise.
	 */
	protected function assertNoLinkByHref($href, $message = '', $group = 'Other') // TODO lans.
	{
		$links = $this->xpath('//a[contains(@href, :href)]', array(':href' => $href));

		// TODO lans.
		$message = ($message ? $message : e107::getParser()->lanVars('No link containing href [x] found.', array(
			'x' => $href,
		)));

		return $this->assert(empty($links), $message, $group);
	}

	/**
	 * Follows a link by name.
	 *
	 * Will click the first link found with this link text by default, or a later one if an index is given.
	 * Match is case insensitive with normalized space. The label is translated label. There is an assert for
	 * successful click.
	 *
	 * @param $label
	 *   Text between the anchor tags.
	 * @param $index
	 *   Link position counting from zero.
	 *
	 * @return mixed
	 *   Page on success, or FALSE on failure.
	 */
	protected function clickLink($label, $index = 0)
	{
		$url_before = $this->getUrl();
		$url_target = '';

		$urls = $this->xpath('//a[normalize-space(text())=:label]|//a[.//*[normalize-space(text())=:label]]', array(
			':label' => $label,
		));

		if(isset($urls[$index]))
		{
			$url_target = $this->getAbsoluteUrl($urls[$index]['href']);
		}

		// TODO lans.
		$this->assertTrue(isset($urls[$index]), e107::getParser()->lanVars('Clicked link [x] ([y]) from [z]', array(
			'x' => $label,
			'y' => $url_target,
			'z' => $url_before,
		)), 'Browser'); // TODO lans.

		if(isset($url_target))
		{
			return $this->e107Get($url_target);
		}

		return false;
	}

	/**
	 * Get all option elements, including nested options, in a select.
	 *
	 * @param SimpleXMLElement $element
	 *   The element for which to get the options.
	 *
	 * @return array
	 *   Option elements in select.
	 */
	protected function getAllOptions(SimpleXMLElement $element)
	{
		$options = array();

		// Add all options items.
		foreach($element->option as $option)
		{
			$options[] = $option;
		}

		// Search option group children.
		if(isset($element->optgroup))
		{
			foreach($element->optgroup as $group)
			{
				$options = array_merge($options, $this->getAllOptions($group));
			}
		}

		return $options;
	}

	/**
	 * Takes a path and returns an absolute path.
	 *
	 * @param $path
	 *   A path from the internal browser content.
	 *
	 * @return string
	 *   The $path with $base_url prepended, if necessary.
	 */
	protected function getAbsoluteUrl($path)
	{
		$base_url = SITEURLBASE;
		$base_path = '/'; // FIXME

		$parts = parse_url($path);
		if(empty($parts['host']))
		{
			// Ensure that we have a string (and no xpath object).
			$path = (string) $path;
			// Strip $base_path, if existent.
			$length = strlen($base_path);

			if(substr($path, 0, $length) === $base_path)
			{
				$path = substr($path, $length);
			}

			// Ensure that we have an absolute path.
			if($path[0] !== '/')
			{
				$path = '/' . $path;
			}

			// Finally, prepend the $base_url.
			$path = $base_url . $path;
		}

		return $path;
	}

	/**
	 * Get the current url from the cURL handler.
	 *
	 * @return string
	 *   The current url.
	 */
	protected function getUrl()
	{
		return $this->url;
	}

	/**
	 * Pass if the internal browser's URL matches the given path.
	 *
	 * @param $url
	 *   The expected URL.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertUrl($url, $message = '', $group = 'Other') // TODO lans.
	{
		if(!$message)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Current URL is [x].', array(
				'x' => var_export($url, true),
			));
		}

		$options['absolute'] = true;

		return $this->assertEqual($this->getUrl(), $url, $message, $group);
	}

	/**
	 * Pass if the raw text IS found on the loaded page, fail otherwise.
	 * Raw text refers to the raw HTML that the page generated.
	 *
	 * @param $raw
	 *   Raw (HTML) string to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertRaw($raw, $message = '', $group = 'Other') // TODO lans.
	{
		if(!$message)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Raw "[x]" found', array('x' => $raw));
		}

		return $this->assert(strpos($this->e107GetContent(), $raw) !== false, $message, $group);
	}

	/**
	 * Pass if the raw text is NOT found on the loaded page, fail otherwise.
	 * Raw text refers to the raw HTML that the page generated.
	 *
	 * @param $raw
	 *   Raw (HTML) string to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoRaw($raw, $message = '', $group = 'Other') // TODO lans.
	{
		if(!$message)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Raw "[x]" not found', array('x' => $raw));
		}

		return $this->assert(strpos($this->e107GetContent(), $raw) === false, $message, $group);
	}

	/**
	 * Pass if the text IS found on the text version of the page. The text version is the equivalent of what a user
	 * would see when viewing through a web browser. In other words the HTML has been filtered out of the contents.
	 *
	 * @param $text
	 *   Plain text to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertText($text, $message = '', $group = 'Other') // TODO lans.
	{
		return $this->assertTextHelper($text, $message, $group, false);
	}

	/**
	 * Pass if the text is NOT found on the text version of the page. The text version is the equivalent of what a
	 * user would see when viewing through a web browser. In other words the HTML has been filtered out of the contents.
	 *
	 * @param $text
	 *   Plain text to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoText($text, $message = '', $group = 'Other') // TODO lans.
	{
		return $this->assertTextHelper($text, $message, $group, true);
	}

	/**
	 * Helper for assertText and assertNoText. It is not recommended to call this function directly.
	 *
	 * @param $text
	 *   Plain text to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 * @param $not_exists
	 *   TRUE if this text should not exist, FALSE if it should.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertTextHelper($text, $message = '', $group, $not_exists)
	{
		if($this->plainTextContent === false)
		{
			// TODO filter XSS.
			$this->plainTextContent = $this->e107GetContent();
		}

		if(!$message)
		{
			// TODO lans.
			$message = !$not_exists ? e107::getParser()->lanVars('"[x]" found', array('x' => $text)) : e107::getParser()->lanVars('"[x]" not found', array('x' => $text));
		}

		return $this->assert($not_exists == (strpos($this->plainTextContent, $text) === false), $message, $group);
	}

	/**
	 * Pass if the text is found ONLY ONCE on the text version of the page. The text version is the equivalent of
	 * what a user would see when viewing through a web browser. In other words the HTML has been filtered out of
	 * the contents.
	 *
	 * @param $text
	 *   Plain text to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertUniqueText($text, $message = '', $group = 'Other') // TODO lans.
	{
		return $this->assertUniqueTextHelper($text, $message, $group, true);
	}

	/**
	 * Pass if the text is found MORE THAN ONCE on the text version of the page. The text version is the equivalent
	 * of what a user would see when viewing through a web browser. In other words the HTML has been filtered out
	 * of the contents.
	 *
	 * @param $text
	 *   Plain text to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to, defaults to 'Other'.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoUniqueText($text, $message = '', $group = 'Other') // TODO lans.
	{
		return $this->assertUniqueTextHelper($text, $message, $group, false);
	}

	/**
	 * Helper for assertUniqueText and assertNoUniqueText. It is not recommended to call this function directly.
	 *
	 * @param $text
	 *   Plain text to look for.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 * @param $be_unique
	 *   TRUE if this text should be found only once, FALSE if it should be found more than once.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertUniqueTextHelper($text, $message = '', $group, $be_unique)
	{
		if($this->plainTextContent === false)
		{
			// TODO filter XSS.
			$this->plainTextContent = $this->e107GetContent();
		}

		if(!$message)
		{
			// TODO lans.
			$message = '"' . $text . '"' . ($be_unique ? ' found only once' : ' found more than once');
		}

		$first_occurance = strpos($this->plainTextContent, $text);

		if($first_occurance === false)
		{
			return $this->assert(false, $message, $group);
		}

		$offset = $first_occurance + strlen($text);
		$second_occurance = strpos($this->plainTextContent, $text, $offset);

		return $this->assert($be_unique == ($second_occurance === false), $message, $group);
	}

	/**
	 * Will trigger a pass if the Perl regex pattern is found in the raw content.
	 *
	 * @param $pattern
	 *   Perl regex to look for including the regex delimiters.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertPattern($pattern, $message = '', $group = 'Other') // TODO lans.
	{
		if(!$message)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Pattern "[x]" found', array('x' => $pattern));
		}

		return $this->assert((bool) preg_match($pattern, $this->e107GetContent()), $message, $group);
	}

	/**
	 * Will trigger a pass if the perl regex pattern is not present in raw content.
	 *
	 * @param $pattern
	 *   Perl regex to look for including the regex delimiters.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoPattern($pattern, $message = '', $group = 'Other') // TODO lans.
	{
		if(!$message)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Pattern "[x]" not found', array('x' => $pattern));
		}

		return $this->assert(!preg_match($pattern, $this->e107GetContent()), $message, $group);
	}

	/**
	 * Pass if the page title is the given string.
	 *
	 * @param $title
	 *   The string the title should be.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertTitle($title, $message = '', $group = 'Other') // TODO lans.
	{
		$actual = (string) current($this->xpath('//title'));

		if(!$message)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Page title [x] is equal to [y].', array(
				'x' => var_export($actual, true),
				'y' => var_export($title, true),
			));
		}

		return $this->assertEqual($actual, $title, $message, $group);
	}

	/**
	 * Pass if the page title is not the given string.
	 *
	 * @param $title
	 *   The string the title should not be.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoTitle($title, $message = '', $group = 'Other') // TODO lans.
	{
		$actual = (string) current($this->xpath('//title'));

		if(!$message)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Page title [x] is not equal to [y].', array(
				'x' => var_export($actual, true),
				'y' => var_export($title, true),
			));
		}

		return $this->assertNotEqual($actual, $title, $message, $group);
	}

	/**
	 * Asserts that a field exists in the current page by the given XPath.
	 *
	 * @param $xpath
	 *   XPath used to find the field.
	 * @param $value
	 *   (optional) Value of the field to assert.
	 * @param $message
	 *   (optional) Message to display.
	 * @param $group
	 *   (optional) The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertFieldByXPath($xpath, $value = null, $message = '', $group = 'Other') // TODO lans.
	{
		$fields = $this->xpath($xpath);

		// If value specified then check array for match.
		$found = true;
		if(isset($value))
		{
			$found = false;
			if($fields)
			{
				foreach($fields as $field)
				{
					if(isset($field['value']) && $field['value'] == $value)
					{
						// Input element with correct value.
						$found = true;
					}
					elseif(isset($field->option))
					{
						// Select element found.
						if($this->getSelectedItem($field) == $value)
						{
							$found = true;
						}
						else
						{
							// No item selected so use first item.
							$items = $this->getAllOptions($field);
							if(!empty($items) && $items[0]['value'] == $value)
							{
								$found = true;
							}
						}
					}
					elseif((string) $field == $value)
					{
						// Text area with correct text.
						$found = true;
					}
				}
			}
		}
		return $this->assertTrue($fields && $found, $message, $group);
	}

	/**
	 * Get the selected value from a select field.
	 *
	 * @param $element
	 *   SimpleXMLElement select element.
	 *
	 * @return mixed
	 *   The selected value or FALSE.
	 */
	protected function getSelectedItem(SimpleXMLElement $element)
	{
		foreach($element->children() as $item)
		{
			if(isset($item['selected']))
			{
				return $item['value'];
			}
			elseif($item->getName() == 'optgroup')
			{
				if($value = $this->getSelectedItem($item))
				{
					return $value;
				}
			}
		}
		return false;
	}

	/**
	 * Asserts that a field does not exist in the current page by the given XPath.
	 *
	 * @param $xpath
	 *   XPath used to find the field.
	 * @param $value
	 *   (optional) Value of the field to assert.
	 * @param $message
	 *   (optional) Message to display.
	 * @param $group
	 *   (optional) The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoFieldByXPath($xpath, $value = null, $message = '', $group = 'Other') // TODO lans.
	{
		$fields = $this->xpath($xpath);

		// If value specified then check array for match.
		$found = true;
		if(isset($value))
		{
			$found = false;
			if($fields)
			{
				foreach($fields as $field)
				{
					if($field['value'] == $value)
					{
						$found = true;
					}
				}
			}
		}
		return $this->assertFalse($fields && $found, $message, $group);
	}

	/**
	 * Asserts that a field exists in the current page with the given name and value.
	 *
	 * @param $name
	 *   Name of field to assert.
	 * @param $value
	 *   Value of the field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertFieldByName($name, $value = '', $message = '', $group = 'Browser') // TODO lans.
	{
		// TODO lans.
		return $this->assertFieldByXPath($this->constructFieldXpath('name', $name), $value, $message ? $message : e107::getParser()->lanVars('Found field by name [x]', array('x' => $name)), $group);
	}

	/**
	 * Asserts that a field does not exist with the given name and value.
	 *
	 * @param $name
	 *   Name of field to assert.
	 * @param $value
	 *   Value of the field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoFieldByName($name, $value = '', $message = '', $group = 'Browser') // TODO lans.
	{
		// TODO lans.
		return $this->assertNoFieldByXPath($this->constructFieldXpath('name', $name), $value, $message ? $message : e107::getParser()->lanVars('Did not find field by name [x]', array('x' => $name)), $group);
	}

	/**
	 * Asserts that a field exists in the current page with the given id and value.
	 *
	 * @param $id
	 *   Id of field to assert.
	 * @param $value
	 *   Value of the field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertFieldById($id, $value = '', $message = '', $group = 'Browser') // TODO lans.
	{
		// TODO lans.
		return $this->assertFieldByXPath($this->constructFieldXpath('id', $id), $value, $message ? $message : e107::getParser()->lanVars('Found field by id [x]', array('x' => $id)), $group);
	}

	/**
	 * Asserts that a field does not exist with the given id and value.
	 *
	 * @param $id
	 *   Id of field to assert.
	 * @param $value
	 *   Value of the field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoFieldById($id, $value = '', $message = '', $group = 'Browser') // TODO lans.
	{
		// TODO lans.
		return $this->assertNoFieldByXPath($this->constructFieldXpath('id', $id), $value, $message ? $message : e107::getParser()->lanVars('Did not find field by id [x]', array('x' => $id)), $group);
	}

	/**
	 * Asserts that a checkbox field in the current page is checked.
	 *
	 * @param $id
	 *   Id of field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertFieldChecked($id, $message = '', $group = 'Browser') // TODO lans.
	{
		$elements = $this->xpath('//input[@id=:id]', array(':id' => $id));
		// TODO lans.
		return $this->assertTrue(isset($elements[0]) && !empty($elements[0]['checked']), $message ? $message : e107::getParser()->lanVars('Checkbox field [x] is checked.', array('x' => $id)), $group);
	}

	/**
	 * Asserts that a checkbox field in the current page is not checked.
	 *
	 * @param $id
	 *   Id of field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoFieldChecked($id, $message = '', $group = 'Browser') // TODO lans.
	{
		$elements = $this->xpath('//input[@id=:id]', array(':id' => $id));
		// TODO lans.
		return $this->assertTrue(isset($elements[0]) && empty($elements[0]['checked']), $message ? $message : e107::getParser()->lanVars('Checkbox field [x] is not checked.', array('x' => $id)), $group);
	}

	/**
	 * Asserts that a select option in the current page is checked.
	 *
	 * @param $id
	 *   Id of select field to assert.
	 * @param $option
	 *   Option to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertOptionSelected($id, $option, $message = '', $group = 'Browser') // TODO lans.
	{
		$elements = $this->xpath('//select[@id=:id]//option[@value=:option]', array(':id' => $id, ':option' => $option));
		// TODO lans.
		return $this->assertTrue(isset($elements[0]) && !empty($elements[0]['selected']), $message ? $message : e107::getParser()->lanVars('Option [x] for field [y] is selected.', array('x' => $option, 'y' => $id)), $group);
	}

	/**
	 * Asserts that a select option in the current page is not checked.
	 *
	 * @param $id
	 *   Id of select field to assert.
	 * @param $option
	 *   Option to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoOptionSelected($id, $option, $message = '', $group = 'Browser') // TODO lans.
	{
		$elements = $this->xpath('//select[@id=:id]//option[@value=:option]', array(':id' => $id, ':option' => $option));
		// TODO lans.
		return $this->assertTrue(isset($elements[0]) && empty($elements[0]['selected']), $message ? $message : e107::getParser()->lanVars('Option [x] for field [y] is not selected.', array('x' => $option, 'y' => $id)), $group);
	}

	/**
	 * Asserts that a field exists with the given name or id.
	 *
	 * @param $field
	 *   Name or id of field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertField($field, $message = '', $group = 'Other') // TODO lans.
	{
		return $this->assertFieldByXPath($this->constructFieldXpath('name', $field) . '|' . $this->constructFieldXpath('id', $field), null, $message, $group);
	}

	/**
	 * Asserts that a field does not exist with the given name or id.
	 *
	 * @param $field
	 *   Name or id of field to assert.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoField($field, $message = '', $group = 'Other') // TODO lans.
	{
		return $this->assertNoFieldByXPath($this->constructFieldXpath('name', $field) . '|' . $this->constructFieldXpath('id', $field), null, $message, $group);
	}

	/**
	 * Asserts that each HTML ID is used for just a single element.
	 *
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 * @param $ids_to_skip
	 *   An optional array of ids to skip when checking for duplicates. It is
	 *   always a bug to have duplicate HTML IDs, so this parameter is to enable
	 *   incremental fixing of code.
	 *
	 * @return boolean
	 *   TRUE on pass, FALSE on fail.
	 */
	protected function assertNoDuplicateIds($message = '', $group = 'Other', $ids_to_skip = array()) // TODO lans.
	{
		$status = true;

		foreach($this->xpath('//*[@id]') as $element)
		{
			$id = (string) $element['id'];

			if(isset($seen_ids[$id]) && !in_array($id, $ids_to_skip))
			{
				// TODO lans.
				$this->fail(e107::getParser()->lanVars('The HTML ID [x] is unique.', array('x' => $id)), $group);
				$status = false;
			}

			$seen_ids[$id] = true;
		}

		return $this->assert($status, $message, $group);
	}

	/**
	 * Helper function: construct an XPath for the given set of attributes and value.
	 *
	 * @param $attribute
	 *   Field attributes.
	 * @param $value
	 *   Value of field.
	 *
	 * @return mixed
	 *   XPath for specified values.
	 */
	protected function constructFieldXpath($attribute, $value)
	{
		$xpath = '//textarea[@' . $attribute . '=:value]|//input[@' . $attribute . '=:value]|//select[@' . $attribute . '=:value]';
		return $this->buildXPathQuery($xpath, array(':value' => $value));
	}

	/**
	 * Asserts the page responds with the specified response code.
	 *
	 * @param $code
	 *   Response code. For example 200 is a successful page request. For a list of all codes see
	 *   http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   Assertion result.
	 */
	protected function assertResponse($code, $message = '', $group = 'Browser') // TODO lans.
	{
		$curl_code = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
		$match = is_array($code) ? in_array($curl_code, $code) : $curl_code == $code;
		// TODO lans.
		return $this->assertTrue($match, $message ? $message : e107::getParser()->lanVars('HTTP response expected [x], actual [y]', array('x' => $code, 'y' => $curl_code)), $group);
	}

	/**
	 * Asserts the page did not return the specified response code.
	 *
	 * @param $code
	 *   Response code. For example 200 is a successful page request. For a list of all codes see
	 *   http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html.
	 * @param $message
	 *   Message to display.
	 * @param $group
	 *   The group this message belongs to.
	 *
	 * @return boolean
	 *   Assertion result.
	 */
	protected function assertNoResponse($code, $message = '', $group = 'Browser') // TODO lans.
	{
		$curl_code = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
		$match = is_array($code) ? in_array($curl_code, $code) : $curl_code == $code;
		// TODO lans.
		return $this->assertFalse($match, $message ? $message : e107::getParser()->lanVars('HTTP response not expected [x], actual [y]', array('x' => $code, 'y' => $curl_code)), $group);
	}

}


/**
 * Clone an existing database and use it for testing.
 */
class e107CloneTestCase extends e107WebTestCase
{

	// TODO...

}
