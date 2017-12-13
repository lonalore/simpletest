<?php

/**
 * @file
 * Class installations to handle configuration forms on Admin UI.
 */

require_once('../../class2.php');

if(!e107::isInstalled('simpletest') || !getperms("P"))
{
	e107::redirect(e_BASE . 'index.php');
}

e107_require_once(e_PLUGIN . 'simpletest/includes/helpers.php');

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
		'main/list' => array(
			'caption' => LAN_PLUGIN_ST_ADMIN_22,
			'perm'    => 'P',
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
		LAN_PLUGIN_ST_ADMIN_04,
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
		'remote_url'        => array(
			'title' => LAN_PLUGIN_ST_ADMIN_20,
			'help'  => LAN_PLUGIN_ST_ADMIN_21,
			'type'  => 'text',
			'data'  => 'str',
			'tab'   => 2,
		),
	);

	/**
	 * User defined init.
	 */
	public function init()
	{

	}

	public function listPage()
	{
		$ns = e107::getRender();

		// Output.
		$html = '';

		$ns->tablerender(LAN_PLUGIN_ST_ADMIN_22, $html);
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
