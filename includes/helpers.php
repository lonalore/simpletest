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
		while(!empty($backtrace[1]) && ($caller = $backtrace[1]) && ((isset($caller['class']) && (strpos($caller['class'], 'db') !== false || strpos($caller['class'], 'e_db_mysql') !== false || strpos($caller['class'], 'PDO') !== false)) || in_array($caller['function'], $db_functions))) {
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

		if($count > 1)
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

	e107::getMessage()->add($message, eMessage::E_WARNING);
	e107::getCache()->clear('simpletest_test');
}

/**
 * Removed prefixed tables from the database that are left over from crashed tests.
 */
function simpletest_clean_database()
{
	$tables = array(); // TODO - get tables with 'simpletest%' prefix...

	$count = 0;

	foreach($tables as $table)
	{
		// Strip the prefix and skip tables without digits following "simpletest", e.g. {simpletest_test_id}.
		if(preg_match('/simpletest\d+.*/', $table, $matches))
		{
			// TODO - drop $matches[0] table...
			$count++;
		}
	}

	if($count > 0)
	{
		if($count > 1)
		{
			// FIXME - LANs...
			$message = e107::getParser()->lanVars('Removed [x] leftover tables.', array(
				'x' => $count,
			));
		}
		else
		{
			// FIXME - LANs...
			$message = e107::getParser()->lanVars('Removed [x] leftover table.', array(
				'x' => $count,
			));
		}
	}
	else
	{
		// FIXME - LANs...
		$message = 'No leftover tables to remove.';
	}

	e107::getMessage()->add($message);
}

/**
 * Find all leftover temporary directories and remove them.
 */
function simpletest_clean_temporary_directories()
{
	$files = scandir(e_SYSTEM_BASE . 'simpletest');

	$count = 0;

	foreach($files as $file)
	{
		$path = e_SYSTEM_BASE . 'simpletest/' . $file;

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
			// FIXME - LANs...
			$message = e107::getParser()->lanVars('Removed [x] temporary directories.', array(
				'x' => $count,
			));
		}
		else
		{
			// FIXME - LANs...
			$message = e107::getParser()->lanVars('Removed [x] temporary directory.', array(
				'x' => $count,
			));
		}
	}
	else
	{
		// FIXME - LANs...
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

	$plugList = e107::getFile()->get_files(e_PLUGIN, "^([a-zA-Z0-9]+)\.(test)$", "standard", 2);

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

