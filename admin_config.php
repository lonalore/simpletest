<?php

/**
 * @file
 * Contains Admin UI for "SimpleTest" plugin.
 */

require_once('../../class2.php');

if(!e107::isInstalled('simpletest') || !getperms("P"))
{
	e107::redirect(e_BASE . 'index.php');
}

// Includes main SimpleTest file.
e107_require_once(e_PLUGIN . 'simpletest/simpletest.php');
// Includes main Batch API file.
e107_require_once(e_PLUGIN . 'batch/includes/batch.php');

// [PLUGINS]/simpletest/languages/[LANGUAGE]/[LANGUAGE]_admin.php
e107::lan('simpletest', true, true);


/**
 * Class simpletest_admin.
 */
class simpletest_admin extends e_admin_dispatcher
{

	/**
	 * Required (set by child class).
	 *
	 * Controller map array in format.
	 * @code
	 *  'MODE' => array(
	 *      'controller' =>'CONTROLLER_CLASS_NAME',
	 *      'path' => 'CONTROLLER SCRIPT PATH',
	 *      'ui' => 'UI_CLASS', // extend of 'comments_admin_form_ui'
	 *      'uipath' => 'path/to/ui/',
	 *  );
	 * @endcode
	 *
	 * @var array
	 */
	protected $modes = array(
		'main' => array(
			'controller' => 'simpletest_admin_ui',
			'path'       => null,
			'ui'         => 'simpletest_admin_form_ui',
			'uipath'     => null
		),
	);

	/**
	 * Optional (set by child class).
	 *
	 * Required for admin menu render. Format:
	 * @code
	 *  'mode/action' => array(
	 *      'caption' => 'Link title',
	 *      'perm' => '0',
	 *      'url' => '{e_PLUGIN}plugname/admin_config.php',
	 *      ...
	 *  );
	 * @endcode
	 *
	 * Note that 'perm' and 'userclass' restrictions are inherited from the $modes, $access and $perm, so you don't
	 * have to set that vars if you don't need any additional 'visual' control.
	 *
	 * All valid key-value pair (see e107::getNav()->admin function) are accepted.
	 *
	 * @var array
	 */
	protected $adminMenu = array(
		'main/list'  => array(
			'caption' => LAN_PLUGIN_ST_ADMIN_22,
			'perm'    => 'P',
		),
		'main/clean' => array(
			'caption' => LAN_PLUGIN_ST_ADMIN_24,
			'perm'    => 'P',
		),
		'other'      => array(
			'divider' => true,
		),
		'main/prefs' => array(
			'caption' => LAN_PLUGIN_ST_ADMIN_01,
			'perm'    => 'P',
		),
	);

	/**
	 * Optional (set by child class).
	 *
	 * @var string
	 */
	protected $menuTitle = LAN_PLUGIN_SIMPLETEST_NAME;

}


/**
 * Class simpletest_admin_ui.
 */
class simpletest_admin_ui extends e_admin_ui
{

	/**
	 * Could be LAN constant (multi-language support).
	 *
	 * @var string plugin name
	 */
	protected $pluginTitle = LAN_PLUGIN_SIMPLETEST_NAME;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	protected $pluginName = "simpletest";

	/**
	 * Example: array('0' => 'Tab label', '1' => 'Another label');
	 * Referenced from $prefs property per field - 'tab => xxx' where xxx is the tab key (identifier).
	 *
	 * @var array edit/create form tabs
	 */
	protected $preftabs = array(
		LAN_PLUGIN_ST_ADMIN_02,
		LAN_PLUGIN_ST_ADMIN_03,
	);

	/**
	 * Plugin Preference description array.
	 *
	 * @var array
	 */
	protected $prefs = array(
		'clear_results'     => array(
			'title'      => LAN_PLUGIN_ST_ADMIN_05,
			'help'       => LAN_PLUGIN_ST_ADMIN_06,
			'type'       => 'boolean',
			'writeParms' => 'label=yesno',
			'data'       => 'int',
			'tab'        => 0,
		),
		'remove_tables'     => array(
			'title'      => LAN_PLUGIN_ST_ADMIN_07,
			'help'       => LAN_PLUGIN_ST_ADMIN_08,
			'type'       => 'boolean',
			'writeParms' => 'label=yesno',
			'data'       => 'int',
			'tab'        => 0,
		),
		'verbose'           => array(
			'title'      => LAN_PLUGIN_ST_ADMIN_09,
			'help'       => LAN_PLUGIN_ST_ADMIN_10,
			'type'       => 'boolean',
			'writeParms' => 'label=yesno',
			'data'       => 'int',
			'tab'        => 0,
		),
		'httpauth_method'   => array(
			'title'      => LAN_PLUGIN_ST_ADMIN_11,
			'type'       => 'dropdown',
			'data'       => 'str',
			'writeParms' => array(
				'optArray' => array(
					CURLAUTH_BASIC        => LAN_PLUGIN_ST_ADMIN_12,
					CURLAUTH_DIGEST       => LAN_PLUGIN_ST_ADMIN_13,
					CURLAUTH_GSSNEGOTIATE => LAN_PLUGIN_ST_ADMIN_14,
					CURLAUTH_NTLM         => LAN_PLUGIN_ST_ADMIN_15,
					CURLAUTH_ANY          => LAN_PLUGIN_ST_ADMIN_16,
					CURLAUTH_ANYSAFE      => LAN_PLUGIN_ST_ADMIN_17,
				),
			),
			'tab'        => 1,
		),
		'httpauth_username' => array(
			'title' => LAN_PLUGIN_ST_ADMIN_18,
			'type'  => 'text',
			'data'  => 'str',
			'tab'   => 1,
		),
		'httpauth_password' => array(
			'title' => LAN_PLUGIN_ST_ADMIN_19,
			'type'  => 'text',
			'data'  => 'str',
			'tab'   => 1,
		),
	);

	/**
	 * User defined init.
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * Provides a list with available tests.
	 */
	public function listPage()
	{
		$form = e107::getForm();
		$msg = e107::getMessage();
		$ns = e107::getRender();

		$errors = simpletest_check_dependencies();

		if(!empty($errors))
		{
			$message = '<strong>' . 'Missing dependency:' . '</strong>';
			$message .= '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
			$msg->addWarning($message);
		}

		e107::css('simpletest', 'assets/css/simpletest.css');
		e107::js('simpletest', 'assets/js/simpletest.js');

		$msg->addInfo('Select the test(s) or test group(s) you would like to run, and click Run tests.');

		// Output.
		$html = '';

		// Action URL with [debug=-] to disable debug mode.
		$action = e_PLUGIN_ABS . 'simpletest/admin_config.php?[debug=-]&mode=main&action=submit';
		$html .= $form->open('simpletest-tests', 'post', $action);

		$html .= '<label class="control-label toggle-all-label">';
		$html .= $form->checkbox('select-all', 1, false, array('id' => 'simpletest-toggle-all')) . ' Select / Deselect all tests';
		$html .= '</label>';

		$tests = simpletest_get_tests();
		foreach($tests as $group => $items)
		{
			$table = '';

			$table .= '<table class="table table-striped">';
			$table .= '<thead>';
			$table .= '<tr>';
			$table .= '<th width="40%">Class</th>';
			$table .= '<th width="60%">Name / Description</th>';
			$table .= '</tr>';
			$table .= '</thead>';
			$table .= '<tbody>';
			foreach($items as $item)
			{
				$table .= '<tr>';
				$table .= '<td>';
				$table .= '<label class="control-label">';
				$table .= $form->checkbox('tests[]', $item['class'], false) . ' ' . $item['class'];
				$table .= '</label>';
				$table .= '</td>';
				$table .= '<td><strong>' . $item['name'] . '</strong><p class="small">' . $item['description'] . '</p></td>';
				$table .= '</tr>';
			}
			$table .= '</tbody>';
			$table .= '</table>';

			$html .= $this->getPanel($group, '', $table, '', array(
				'checkbox'    => true,
				'collapsible' => true,
				'collapsed'   => true,
			));
		}

		if(empty($errors))
		{
			$html .= $form->submit('run', 'Run tests');
		}

		$html .= $form->close();

		return $ns->tablerender(null, $html, 'default', true);
	}

	/**
	 * Submit callback to prepare and run tests.
	 */
	public function submitPage()
	{
		$tests_all = simpletest_get_tests();
		$tests_selected = !empty($_POST['tests']) ? $_POST['tests'] : array();

		$tests = array();
		foreach($tests_all as $group => $items)
		{
			foreach($items as $item)
			{
				if(in_array($item['class'], $tests_selected))
				{
					$tests[] = $item;
				}
			}
		}

		if(empty($tests))
		{
			e107::getMessage()->addError('No test was selected!', 'default', true);
			e107::redirect(e_PLUGIN_ABS . 'simpletest/admin_config.php?mode=main&action=list');
		}

		$test_id = e107::getDb()->insert('simpletest_test_id', array('last_prefix' => ''));

		if(empty($test_id))
		{
			e107::getMessage()->addError('Cannot prepare batch process!', 'default', true);
			e107::redirect(e_PLUGIN_ABS . 'simpletest/admin_config.php?mode=main&action=list');
		}

		// Clear out the previous verbose files.
		$system_files_directory = e_SYSTEM_BASE . 'simpletest/verbose';
		simpletest_file_delete_recursive($system_files_directory);

		$batch = array(
			'operations'       => array(
				array('simpletest_run_tests_process', array($tests, $test_id)),
			),
			'finished'         => 'simpletest_run_tests_finished',
			'title'            => 'Running tests',
			'init_message'     => 'Testing is starting.',
			'progress_message' => 'Processed test @current out of @total.',
			'error_message'    => 'Testing has encountered an error.',
			'file'             => '{e_PLUGIN}simpletest/includes/batch.php',
		);

		$finished = e_PLUGIN_ABS . 'simpletest/admin_config.php?mode=main&action=results&test=' . $test_id;
		$process = e_PLUGIN_ABS . 'simpletest/admin_config.php?mode=main&action=run';

		e107::getEvent()->trigger('simpletest_test_group_started');

		batch_set($batch);
		batch_process($finished, $process);
	}

	/**
	 * Provides a Batch API page callback.
	 */
	public function runPage()
	{
		e107::css('simpletest', 'assets/css/simpletest.css');

		$output = _batch_page();

		if($output !== false)
		{
			return e107::getRender()->tablerender($output['caption'], $output['content'], 'default', true);
		}
		else
		{
			e107::getMessage()->addError('Cannot render the batch processing page!', 'default', true);
			e107::redirect(e_PLUGIN_ABS . 'simpletest/admin_config.php?mode=main&action=list');
		}
	}

	/**
	 * Page callback to display test results.
	 */
	public function resultsPage()
	{
		$test_id = !empty($_GET['test']) ? (int) $_GET['test'] : 0;
		$results = simpletest_result_get($test_id);

		if(empty($results))
		{
			e107::getMessage()->addWarning('No test results to display.', 'default', true);
			e107::redirect(e_PLUGIN_ABS . 'simpletest/admin_config.php?mode=main&action=list');
		}

		e107::css('simpletest', 'assets/css/simpletest.css');
		e107::js('simpletest', 'assets/js/simpletest.js');

		$html = '';

		foreach($results as $test_class => $items)
		{
			$table = '';

			$success = true;

			$table .= '<table class="table table-striped simpletest-result-table">';
			$table .= '<thead>';
			$table .= '<tr>';
			$table .= '<th width="30%">Message</th>';
			$table .= '<th width="25%">Function</th>';
			$table .= '<th width="30%">Filename</th>';
			$table .= '<th width="5%">Line</th>';
			$table .= '<th width="10%">Group</th>';
			$table .= '</tr>';
			$table .= '</thead>';
			$table .= '<tbody>';
			foreach($items as $item)
			{
				$class = '';

				switch($item['status'])
				{
					case 'pass':
						$class = 'success';
						break;

					case 'fail':
						$class = 'danger';
						$success = false;
						break;

					case 'debug':
						$class = 'info';
						break;
				}

				$table .= '<tr class="' . $class . '">';
				$table .= '<td>' . $item['message'] . '</td>';
				$table .= '<td>' . $item['function'] . '</td>';
				$table .= '<td>' . str_replace(e_DOCROOT, '', $item['file']) . '</td>';
				$table .= '<td>' . $item['line'] . '</td>';
				$table .= '<td>' . $item['message_group'] . '</td>';
				$table .= '</tr>';
			}
			$table .= '</tbody>';
			$table .= '</table>';

			$html .= $this->getPanel($test_class, '', $table, '', array(
				'checkbox'    => false,
				'collapsible' => true,
				'collapsed'   => $success,
				'success'     => $success,
			));
		}

		simpletest_clean_results_table($test_id);

		return e107::getRender()->tablerender(null, $html, 'default', true);
	}

	/**
	 * Page callback to display/handle 'Clean environment' form.
	 */
	public function cleanPage()
	{
		$frm = e107::getForm();

		if(!empty($_POST['clean']))
		{
			simpletest_clean_environment();
		}

		$html = '';
		$form = '';

		$form .= $frm->open('simpletest_clean', 'post');
		$form .= $frm->hidden('clean', 1);
		$form .= $frm->button('submit', LAN_PLUGIN_ST_ADMIN_25);
		$form .= $frm->close();

		$html .= $this->getPanel(LAN_PLUGIN_ST_ADMIN_25, LAN_PLUGIN_ST_ADMIN_26, $form, '', array(
			'checkbox'    => false,
			'collapsible' => false,
			'collapsed'   => false,
		));

		return e107::getRender()->tablerender(null, $html, 'default', true);
	}

	/**
	 * Helper function to render Bootstrap Panel.
	 *
	 * @param string $title
	 *   Panel title.
	 * @param string $help
	 *   Help text, description.
	 * @param string $body
	 *   Panel body.
	 * @param string $footer
	 *   Panel footer.
	 * @param array $options
	 *   Panel options.
	 *
	 * @return string
	 */
	function getPanel($title = '', $help = '', $body = '', $footer = '', $options = array())
	{
		$tpl = e107::getTemplate('simpletest');
		$sc = e107::getScBatch('simpletest', true);
		$tp = e107::getParser();

		$sc->setVars(array(
			'title'   => $title,
			'help'    => $help,
			'body'    => $body,
			'footer'  => $footer,
			'options' => array(
				'id'          => !empty($options['id']) ? $options['id'] : md5($title),
				'collapsible' => isset($options['collapsible']) ? $options['collapsible'] : true,
				'collapsed'   => isset($options['collapsed']) ? $options['collapsed'] : false,
				'checkbox'    => isset($options['checkbox']) ? $options['checkbox'] : true,
				'success'     => isset($options['success']) ? $options['success'] : null,
			),
		));

		$html = $tp->parseTemplate($tpl['PANEL'], true, $sc);

		return $html;
	}

}


/**
 * Class simpletest_admin_form.
 */
class simpletest_admin_form extends e_admin_form_ui
{

}


new simpletest_admin();

require_once(e_ADMIN . "auth.php");
e107::getAdminUI()->runPage();
require_once(e_ADMIN . "footer.php");
exit;
