<?php

/**
 * @file
 * Contains helper functions.
 */


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

		$folder = $file_directory . "/simpletest/verbose";
		simpletest_file_prepare_directory($folder);
		file_put_contents($folder . "/$class-$id.html", $message, FILE_APPEND);

		return $id++;
	}

	if($original_file_directory)
	{
		$prefs = e107::getPlugConfig('simpletest')->getPref();

		$file_directory = rtrim($original_file_directory, '/');
		$class = $test_class;
		$verbose = isset($prefs['verbose']) ? (bool) $prefs['verbose'] : true;
		$directory = $file_directory . '/simpletest/verbose';
		$writable = simpletest_file_prepare_directory($directory, 1);

		if($writable && !file_exists($directory . '/.htaccess'))
		{
			file_put_contents($directory . '/.htaccess', "allow from all\n\n<IfModule mod_expires.c>\n\tExpiresActive Off\n</IfModule>\n");
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
		// We skip calls that occurred in one of the classes of the database layer or in one of its global functions.
		$db_functions = array(
			'db_Query',
			'db_Query_all',
			'db_QueryCount',
			'db_Insert',
			'db_Replace',
			'db_Update',
			'db_UpdateArray',
		);

		// The first element in the stack is the call, the second element gives us the caller.
		while(!empty($backtrace[1]) && ($caller = $backtrace[1]) && ((isset($caller['class']) && (strpos($caller['class'], 'db') !== false || strpos($caller['class'], 'e_db_mysql') !== false || strpos($caller['class'], 'PDO') !== false)) || in_array($caller['function'], $db_functions)))
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
 * Get information about the last test that ran given a test ID.
 *
 * @param $test_id
 *   The test ID to get the last test from.
 * @return array
 *   Array containing the last database prefix used and the last test class that ran.
 */
function simpletest_last_test_get($test_id)
{
	$last_prefix = e107::getDb()->retrieve('simpletest_test_id', 'last_prefix', ' test_id = ' . (int) $test_id . ' ORDER BY test_id DESC LIMIT 1');
	$last_test_class = e107::getDb()->retrieve('simpletest', 'test_class', ' test_id = ' . (int) $test_id . ' ORDER BY message_id DESC LIMIT 1');
	return array($last_prefix, $last_test_class);
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
	$log = e_SYSTEM_BASE . ($during_test ? '' : 'simpletest/' . substr($prefix, 10)) . '/error.log';

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

/**
 * Get a list of all of the tests provided by the system.
 *
 * The list of test classes is loaded from the registry where it looks for files ending in ".test".
 * Once loaded the test list is cached and stored in a static variable.
 *
 * @return array
 *   An array of tests keyed with the groups specified in each of the tests getInfo() method and then keyed by the
 *   test class. An example of the array structure is provided below.
 *
 * @code
 *   $groups['Comment'] => array(
 *    'CommentTestCase' => array(
 *        'name' => 'Comment functionality',
 *        'description' => 'Create, view, edit, delete, ...',
 *        'group' => 'Comment',
 *    ),
 *   );
 * @endcode
 */
function simpletest_test_get_all()
{
	static $groups;

	if(empty($groups))
	{
		$cache = e107::getCache();

		// Load test information from cache if available, otherwise retrieve the information from each tests getInfo()
		// method.
		if($cached = $cache->retrieve('simpletest_tests', false, true, true))
		{
			$groups = unserialize(base64_decode($cached));
		}
		else
		{
			// Select all classes in files ending with .test.
			$classes = array(); // TODO...

			// Check that each class has a getInfo() method and store the information in an array keyed with the group
			// specified in the test information.
			$groups = array();

			foreach($classes as $class)
			{
				// Test classes need to implement getInfo() to be valid.
				if(class_exists($class) && method_exists($class, 'getInfo'))
				{
					$info = call_user_func(array($class, 'getInfo'));

					// If this test class requires a non-existing plugin, skip it.
					if(!empty($info['dependencies']))
					{
						foreach($info['dependencies'] as $plugin)
						{
							if(!e107::isInstalled($plugin))
							{
								continue 2;
							}
						}
					}

					$groups[$info['group']][$class] = $info;
				}
			}

			// Sort the groups and tests within the groups by name.
			uksort($groups, 'strnatcasecmp');
			foreach($groups as $group => &$tests)
			{
				uksort($tests, 'strnatcasecmp');
			}

			// Allow plugins to alter $groups.
			// TODO...

			$cacheData = base64_encode(serialize($groups));
			$cache->set('simpletest_tests', $cacheData, true, false, true);
		}
	}

	return $groups;
}

/**
 * Generate test file.
 */
function simpletest_generate_file($filename, $width, $lines, $type = 'binary-text')
{
	$size = $width * $lines - $lines;

	// Generate random text.
	$text = '';

	for($i = 0; $i < $size; $i++)
	{
		switch($type)
		{
			case 'text':
				$text .= chr(rand(32, 126));
				break;
			case 'binary':
				$text .= chr(rand(0, 31));
				break;
			case 'binary-text':
			default:
				$text .= rand(0, 1);
				break;
		}
	}
	$text = wordwrap($text, $width - 1, "\n", true) . "\n"; // Add \n for symetrical file.

	$folder = e_SYSTEM_BASE . 'simpletest/generated';
	simpletest_file_prepare_directory($folder);
	file_put_contents($folder . '/' . $filename . '.txt', $text);

	return $filename;
}

/**
 * Remove all temporary database tables and directories.
 */
function simpletest_clean_environment()
{
	simpletest_clean_database();
	simpletest_clean_temporary_directories();

	$prefs = e107::getPlugConfig('simpletest')->getPref();

	if(!empty($prefs['clear_results']))
	{
		$count = simpletest_clean_results_table();

		if($count == 0)
		{
			$message = 'No leftover test results.';
		}
		elseif($count > 1)
		{
			$message = e107::getParser()->lanVars('Removed [x] test results.', array(
				'x' => $count,
			));
		}
		else
		{
			$message = 'Removed 1 test result.';
		}
	}
	else
	{
		$message = 'Clear results is disabled and the test results table will not be cleared.';
	}

	e107::getMessage()->add($message);
	e107::getCache()->clear('simpletest_test');
}

/**
 * Removed prefixed tables from the database that are left over from crashed tests.
 */
function simpletest_clean_database()
{
	global $mySQLdefaultdb;

	$sql = e107::getDb();
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

	$count = count($tables);

	if($count > 0)
	{
		if($count > 1)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Removed [x] leftover tables.', array(
				'x' => $count,
			));
		}
		else
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Removed [x] leftover table.', array(
				'x' => $count,
			));
		}
	}
	else
	{
		// TODO lans.
		$message = 'No leftover tables to remove.';
	}

	e107::getMessage()->add($message);
}

/**
 * Find all leftover temporary directories and remove them.
 */
function simpletest_clean_temporary_directories()
{
	$system_files = scandir(e_SYSTEM_BASE . 'simpletest');
	$media_files = scandir(e_MEDIA_BASE . 'simpletest');

	$count = 0;

	foreach($system_files as $file)
	{
		$path = e_SYSTEM_BASE . 'simpletest/' . $file;

		if(is_dir($path) && is_numeric($file))
		{
			simpletest_file_delete_recursive($path);
			$count++;
		}
	}

	foreach($media_files as $file)
	{
		$path = e_MEDIA_BASE . 'simpletest/' . $file;

		if(is_dir($path) && is_numeric($file))
		{
			simpletest_file_delete_recursive($path);
			$count++;
		}
	}

	if($count > 0)
	{
		if($count > 1)
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Removed [x] temporary directories.', array(
				'x' => $count,
			));
		}
		else
		{
			// TODO lans.
			$message = e107::getParser()->lanVars('Removed [x] temporary directory.', array(
				'x' => $count,
			));
		}
	}
	else
	{
		// TODO lans.
		$message = 'No temporary directories to remove.';
	}

	e107::getMessage()->add($message);
}

/**
 * Clear the test result tables.
 *
 * @param $test_id
 *   Test ID to remove results for, or NULL to remove all results.
 *
 * @return int
 *   The number of results removed.
 */
function simpletest_clean_results_table($test_id = null)
{
	$prefs = e107::getPlugConfig('simpletest')->getPref();

	if(!empty($prefs['clear_results']))
	{
		if($test_id)
		{
			// 'SELECT COUNT(test_id) FROM {simpletest_test_id} WHERE test_id = :test_id'
			$count = e107::getDb()->count('simpletest_test_id', 'test_id', 'test_id=' . $test_id);

			e107::getDb()->delete('simpletest', 'test_id=' . $test_id);
			e107::getDb()->delete('simpletest_test_id', 'test_id=' . $test_id);
		}
		else
		{
			// 'SELECT COUNT(test_id) FROM {simpletest_test_id}'
			$count = e107::getDb()->count('simpletest_test_id', 'test_id');

			// Clear test results.
			e107::getDb()->delete('simpletest');
			e107::getDb()->delete('simpletest_test_id');
		}

		return $count;
	}

	return 0;
}

function simpletest_get_tests()
{
	e107_require_once(e_PLUGIN . 'simpletest/simpletest.php');

	$plugList = e107::getFile()->get_files(e_PLUGIN, "^([a-zA-Z0-9_]+)\.(test)$", "standard", 3);

	$tests = array();

	foreach($plugList as $key => $details)
	{
		$path = $details['path'] . $details['fname'];
		$classes = simpletest_get_class_from_file($path);

		if(empty($classes))
		{
			continue;
		}

		if(empty($tests[$path]))
		{
			$tests[$path] = array();
		}

		foreach($classes as $class)
		{
			if(!isset($tests[$path][$class]))
			{
				$info = simpletest_get_test_info($path, $class);

				if(!empty($info))
				{
					$tests[$path][$class] = $info;
				}
			}
		}
	}

	$grouped = array();

	foreach($tests as $path => $classes)
	{
		foreach($classes as $class => $info)
		{
			$group = !empty($info['group']) ? $info['group'] : 'Other';
			$name = !empty($info['name']) ? $info['name'] : '';
			$desc = !empty($info['description']) ? $info['description'] : '';
			$deps = !empty($info['dependencies']) ? $info['dependencies'] : array();

			if(!isset($grouped[$group]))
			{
				$grouped[$group] = array();
			}

			$grouped[$group][] = array(
				'file'         => str_replace('../../e107_plugins/', '{e_PLUGIN}', $path),
				'class'        => $class,
				'group'        => $group,
				'name'         => $name,
				'description'  => $desc,
				'dependencies' => $deps,
			);
		}
	}

	return $grouped;
}

function simpletest_get_test_info($file, $class)
{
	$info = array();

	e107_require_once($file);

	if(class_exists($class))
	{
		$test = new $class();

		if(method_exists($test, 'getInfo'))
		{
			$info = $test->getInfo();
		}
	}

	return $info;
}

/**
 * @param $path_to_file
 * @return mixed|string
 */
function simpletest_get_class_from_file($path_to_file)
{
	$classes = array();

	$contents = file_get_contents($path_to_file);
	$tokens = token_get_all($contents);
	$count = count($tokens);

	for($i = 2; $i < $count; $i++)
	{
		if(!isset($tokens[$i]) || !is_array($tokens[$i]) || !isset($tokens[$i - 2]) || !is_array($tokens[$i - 2]))
		{
			continue;
		}

		if($tokens[$i - 2][0] === T_CLASS && $tokens[$i - 1][0] === T_WHITESPACE && $tokens[$i][0] === T_STRING)
		{
			$class_name = $tokens[$i][1];
			$classes[] = $class_name;
		}
	}

	return $classes;
}

/**
 * Get test results for $test_id.
 *
 * @param int $test_id
 *   The test_id to retrieve results of.
 *
 * @return array
 *   Array of results grouped by test_class.
 */
function simpletest_result_get($test_id)
{
	$db = e107::getDb();
	$db->select('simpletest', '*', 'test_id = ' . (int) $test_id . ' ORDER BY test_class, message_id ASC');

	$test_results = array();

	while($row = $db->fetch())
	{
		if(!isset($test_results[$row['test_class']]))
		{
			$test_results[$row['test_class']] = array();
		}

		$test_results[$row['test_class']][] = $row;
	}

	return $test_results;
}

/**
 * Returns the test prefix if this is an internal request from SimpleTest.
 *
 * @return bool
 *   Either the simpletest prefix (the string "simpletest" followed by any number of digits)
 *   or FALSE if the user agent does not contain a valid HMAC and timestamp.
 */
function simpletest_valid_test_ua()
{
	// No reason to reset this.
	static $test_prefix;

	if(isset($test_prefix))
	{
		return $test_prefix;
	}

	if(isset($_SERVER['HTTP_USER_AGENT']) && preg_match("/^(simpletest\d+);(.+);(.+);(.+)$/", $_SERVER['HTTP_USER_AGENT'], $matches))
	{
		list(, $prefix, $time, $salt, $hmac) = $matches;
		$check_string = $prefix . ';' . $time . ';' . $salt;
		$key = simpletest_get_hash_salt() . filectime(__FILE__) . fileinode(__FILE__);
		$time_diff = ((int) $_SERVER['REQUEST_TIME']) - $time;
		// Since we are making a local request a 5 second time window is allowed,
		// and the HMAC must match.
		if($time_diff >= 0 && $time_diff <= 5 && $hmac == simpletest_hmac_base64($check_string, $key))
		{
			$test_prefix = $prefix;
			return $test_prefix;
		}
	}

	$test_prefix = false;
	return $test_prefix;
}

/**
 * Generates a user agent string with a HMAC and timestamp for simpletest.
 */
function simpletest_generate_test_ua($prefix)
{
	static $key;

	if(!isset($key))
	{
		$hash = hash('sha256', serialize($prefix));
		$key = $hash . filectime(__FILE__) . fileinode(__FILE__);
	}
	// Generate a moderately secure HMAC based on the database credentials.
	$salt = uniqid('', true);
	$check_string = $prefix . ';' . time() . ';' . $salt;
	return $check_string . ';' . simpletest_hmac_base64($check_string, $key);
}

/**
 * Gets a salt.
 *
 * @return string
 *   A salt based on information in e107_config.php.
 */
function simpletest_get_hash_salt()
{
	global $mySQLprefix;
	return hash('sha256', serialize($mySQLprefix));
}

/**
 * Calculates a base-64 encoded, URL-safe sha-256 hmac.
 *
 * @param string $data
 *   String to be validated with the hmac.
 * @param string $key
 *   A secret string key.
 *
 * @return string
 *   A base-64 encoded sha-256 hmac, with + replaced with -, / with _ and any = padding characters removed.
 */
function simpletest_hmac_base64($data, $key)
{
	// Casting $data and $key to strings here is necessary to avoid empty string
	// results of the hash function if they are not scalar values. As this
	// function is used in security-critical contexts like token validation it is
	// important that it never returns an empty string.
	$hmac = base64_encode(hash_hmac('sha256', (string) $data, (string) $key, true));
	// Modify the hmac so it's safe to use in URLs.
	return strtr($hmac, array('+' => '-', '/' => '_', '=' => ''));
}

/**
 * Helper function to get all (path) constants, which need to be replaced.
 *
 * @return array
 *   Contains the names for constants.
 */
function simpletest_get_path_constants()
{
	return array(
		'e_AVATAR',             // e107_media/[HASH]/avatars/
		'e_AVATAR_ABS',         // /e107_media/[HASH]/avatars/
		'e_AVATAR_DEFAULT',     // e107_media/[HASH]/avatars/default/
		'e_AVATAR_DEFAULT_ABS', // /e107_media/[HASH]/avatars/default/
		'e_AVATAR_UPLOAD',      // e107_media/[HASH]/avatars/upload/
		'e_AVATAR_UPLOAD_ABS',  // /e107_media/[HASH]/avatars/upload/
		'e_DOWNLOAD',           // e107_media/[HASH]/files/
		'e_MEDIA',              // e107_media/[HASH]/
		'e_MEDIA_ABS',          // /e107_media/[HASH]/
		// 'e_MEDIA_BASE',         // e107_media/
		'e_MEDIA_FILE',         // e107_media/[HASH]/files/
		'e_MEDIA_FILE_ABS',     // /e107_media/[HASH]/files/
		'e_MEDIA_ICON',         // e107_media/[HASH]/icons/
		'e_MEDIA_ICON_ABS',     // /e107_media/[HASH]/icons/
		'e_MEDIA_IMAGE',        // e107_media/[HASH]/images/
		'e_MEDIA_IMAGE_ABS',    // /e107_media/[HASH]/images/
		'e_MEDIA_VIDEO',        // e107_media/[HASH]/videos/
		'e_MEDIA_VIDEO_ABS',    // /e107_media/[HASH]/videos/

		'e_BACKUP',             // e107_system/[HASH]/backup/
		'e_CACHE',              // e107_system/[HASH]/cache/
		'e_CACHE_CONTENT',      // e107_system/[HASH]/cache/content/
		'e_CACHE_DB',           // e107_system/[HASH]/cache/db/
		'e_CACHE_IMAGE',        // e107_system/[HASH]/cache/images/
		'e_CACHE_URL',          // e107_system/[HASH]/cache/url/
		'e_IMPORT',             // e107_system/[HASH]/import/
		'e_LOG',                // e107_system/[HASH]/logs/
		'e_SYSTEM',             // e107_system/[HASH]/
		// 'e_SYSTEM_BASE',        // e107_system/
		'e_TEMP',               // e107_system/[HASH]/temp/
		'e_UPLOAD',             // e107_system/[HASH]/temp/
	);
}

/**
 * Replaces constants are set in simpletest_get_path_constants() by the given rules.
 *
 * @param array $rules
 *   Associative array, whose keys are the $search params and the values are the $replace params for
 *   simpletest_replace_constant() function.
 *
 * @see simpletest_get_path_constants()
 * @see simpletest_replace_constant()
 */
function simpletest_rewrite_paths($rules = array())
{
	if(empty($rules))
	{
		return;
	}

	$constants = simpletest_get_path_constants();

	foreach($constants as $constant)
	{
		foreach($rules as $search => $replace)
		{
			simpletest_replace_constant($constant, $search, $replace);
		}
	}
}

/**
 * Restores the original values for each constants are set in simpletest_get_path_constants().
 *
 * @see simpletest_get_path_constants()
 */
function simpletest_restore_paths()
{
	$constants = simpletest_get_path_constants();

	foreach($constants as $constant)
	{
		simpletest_restore_constant($constant);
	}
}

/**
 * Gets the current or original value of a constant.
 *
 * @param $constant_name
 *   The name of the constant.
 * @param bool $original
 *   If TRUE, the constant's original value will be returned.
 *
 * @return mixed
 *   The value for the constant.
 */
function simpletest_get_constant($constant_name, $original = false)
{
	static $original_constant_values;

	if(!isset($original_constant_values[$constant_name]))
	{
		$original_constant_values[$constant_name] = constant($constant_name);
	}

	if($original)
	{
		return $original_constant_values[$constant_name];
	}

	return constant($constant_name);
}

/**
 * Replace all occurrences of the search string with the replacement string.
 *
 * @param $constant_name
 *   The name of the constant being searched and replaced on.
 * @param $search
 *   The value being searched for, otherwise known as the needle.
 * @param $replace
 *   The replacement value that replaces found search values.
 */
function simpletest_replace_constant($constant_name, $search, $replace)
{
	if(empty($constant_name))
	{
		return;
	}

	if(!function_exists('runkit_constant_redefine'))
	{
		// TODO threw an exception?
		return;
	}

	$value = simpletest_get_constant($constant_name);
	$value = str_replace($search, $replace, $value);
	runkit_constant_redefine($constant_name, $value);
}

/**
 * Restores a constant's original value.
 *
 * @param string $constant_name
 *   Name fo the constant.
 */
function simpletest_restore_constant($constant_name)
{
	if(empty($constant_name))
	{
		return;
	}

	if(!function_exists('runkit_constant_redefine'))
	{
		// TODO threw an exception?
		return;
	}

	$value = simpletest_get_constant($constant_name, true);
	runkit_constant_redefine($constant_name, $value);
}

/**
 * Checks dependencies required for running tests.
 *
 * @param bool $bool
 *   If TRUE, function will return TRUE if all dependencies are met. Otherwise FALSE.
 *   IF FALSE, function will return an array contains error messages, or empty array if all dependencies are met.
 *
 * @return array|bool
 */
function simpletest_check_dependencies($bool = false)
{
	$errors = array();

	if(!function_exists('runkit_constant_redefine'))
	{
		$errors[] = e107::getParser()->lanVars('To run the tests, you need to install the [x] PHP extension!', array(
			'x' => '<a href="https://github.com/lonalore/simpletest#setup" target="_blank">Runkit</a>',
		));
	}

	return !$bool ? $errors : empty($errors);
}
