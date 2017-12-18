<?php

/**
 * @file
 * Batch functions.
 */

/**
 * Batch Operation Callback.
 */
function simpletest_run_tests_process($tests, $test_id, &$context)
{
	if(!isset($context['sandbox']['progress']))
	{
		$context['sandbox']['progress'] = 0;
		$context['sandbox']['max'] = count($tests);

		$context['sandbox']['test_results'] = array(
			'#pass'      => 0,
			'#fail'      => 0,
			'#exception' => 0,
			'#debug'     => 0,
		);
	}

	$test_results = &$context['sandbox']['test_results'];

	e107_require_once(e_PLUGIN . 'simpletest/simpletest.php');

	// For this example, we decide that we can safely process 1 comment
	// at a time without a timeout.
	$limit = 1;

	for($i = 0; $i < $limit; $i++)
	{
		if(isset($tests[$context['sandbox']['progress']]))
		{
			$test = $tests[$context['sandbox']['progress']];
			$file = e107::getParser()->replaceConstants($test['file']);

			e107_include_once($file);

			/** @var e107TestCase|e107UnitTestCase|e107WebTestCase|e107CloneTestCase $obj */
			$obj = new $test['class']($test_id);
			$obj->run();

			// $info = $obj->getInfo();

			e107::getEvent()->trigger('simpletest_test_finished', $obj->results);

			$test_results[$test['class']] = $obj->results;
			foreach($test_results[$test['class']] as $key => $value)
			{
				$test_results[$key] += $value;
			}

			// Update our progress information.
			$context['sandbox']['progress']++;

			$context['message'] = 'Processed test ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' - ' . $test['class'];
			$context['message'] .= '<div class="simpletest-' . ($test_results['#fail'] + $test_results['#exception'] ? 'fail' : 'pass') . '">';
			$context['message'] .= 'Overall results: ' . _simpletest_format_summary_line($test_results);
			$context['message'] .= '</div>';
		}
	}

	// The test_id is the only thing we need to save for the report page.
	$context['results']['test_id'] = $test_id;

	// Inform the batch engine that we are not finished,
	// and provide an estimation of the completion level we reached.
	if($context['sandbox']['progress'] != $context['sandbox']['max'])
	{
		$context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
	}
}

/**
 * Batch 'finished' callback
 */
function simpletest_run_tests_finished($success, $results, $operations, $elapsed)
{
	$tp = e107::getParser();
	$ms = e107::getMessage();

	if($success)
	{
		// Here we do something meaningful with the results.
		$message = $tp->lanVars('[x] items successfully processed.', array(
			'x' => count($results),
		));
		$ms->add($message, E_MESSAGE_SUCCESS, true);
	}
	else
	{
		// An error occurred.
		// $operations contains the operations that remained unprocessed.
		$error_operation = reset($operations);
		$message = $tp->lanVars('An error occurred while processing [x] with arguments: [y]', array(
			'x' => $error_operation[0],
			'y' => print_r($error_operation[1], true),
		));
		$ms->add($message, E_MESSAGE_ERROR, true);
	}

	e107::getEvent()->trigger('simpletest_test_group_finished');
}

function _simpletest_format_summary_line($summary)
{
	$args = array(
		'x' => $summary['#pass'] . ' ' . ($summary['#pass'] > 1 ? 'passes' : 'pass'),
		'y' => $summary['#fail'] . ' ' . ($summary['#fail'] > 1 ? 'fails' : 'fail'),
		'z' => $summary['#exception'] . ' ' . ($summary['#exception'] > 1 ? 'exceptions' : 'exception'),
	);

	if(!$summary['#debug'])
	{
		return e107::getParser()->lanVars('[x], [y], and [z]', $args);
	}

	$args['w'] = $summary['#debug'] . ' ' . ($summary['#debug'] > 1 ? 'debug messages' : 'debug message');

	return e107::getParser()->lanVars('[x], [y], [z] and [w]', $args);
}
