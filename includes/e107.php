<?php

/**
 * @file
 * Contains helper class to make new e107 installation.
 */


/**
 * Helper class.
 */
class SimpleTestE107
{

	/**
	 * Database prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Site [hash] to be used for system and media folders.
	 *
	 * @var string
	 */
	protected $site_hash;

	/**
	 * Database connection.
	 *
	 * @var e_db_mysql
	 */
	protected $db;

	/**
	 * Core prefs.
	 *
	 * @var array
	 */
	protected $aliases = array(
		'core'    => 'SitePrefs',
		'emote'   => 'emote_default',
		'menu'    => 'menu_pref',
		'search'  => 'search_prefs',
		'notify'  => 'notify_prefs',
		'history' => 'history_prefs'
	);

	/**
	 * Constructor.
	 *
	 * @param string $prefix
	 *   Database prefix.
	 * @param string $site_hash
	 *   Site [hash] to be used for system and media folders.
	 */
	public function __construct($prefix, $site_hash)
	{
		// global $mySQLdefaultdb;

		$this->prefix = $prefix;
		$this->site_hash = $site_hash;

		$this->db = e107::getDb($prefix);

		if(!empty($this->prefix))
		{
			// $this->db->database($mySQLdefaultdb, $this->prefix);
			$this->db->mySQLPrefix = $prefix;
		}
	}

	/**
	 * Create Core MySQL tables.
	 */
	public function createTables()
	{
		if(empty($this->prefix))
		{
			return false;
		}

		$sql_data = file_get_contents(e_CORE . "sql/core_sql.php");
		$sql_data = preg_replace("#\/\*.*?\*\/#mis", '', $sql_data); // Strip comments.

		if(!$sql_data)
		{
			return false;
		}

		preg_match_all("/create(.*?)(?:myisam|innodb);/si", $sql_data, $result);

		$this->db->gen('SET NAMES `utf8`');

		foreach($result[0] as $sql_table)
		{
			$sql_table = preg_replace("/create table\s/si", "CREATE TABLE " . $this->prefix, $sql_table);

			if(!$this->db->gen($sql_table))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Import and generate preferences and default content.
	 */
	public function importDefaultConfig($options = array())
	{
		$defaults = array(
			'theme'          => 'bootstrap3',
			'sitename'       => 'My Website',
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

		$params = array_replace_recursive($defaults, $options);

		// Create and register blank config instances - do not load!
		$config_aliases = array(
			'core',
			'core_backup',
			'emote',
			'menu',
			'search',
			'notify',
		);

		foreach($config_aliases as $alias)
		{
			e107::getConfig($alias, false)->clearPrefCache();
		}

		$coreConfig = e_CORE . "xml/default_install.xml";
		e107::getXml()->e107Import($coreConfig, 'add', true, false, $this->db);

		$themeImportFile = array();
		$themeImportFile[0] = e_THEME . $params['theme'] . '/install.xml';
		$themeImportFile[1] = e_THEME . $params['theme'] . '/install/install.xml';

		$XMLImportfile = false;

		foreach($themeImportFile as $file)
		{
			if(is_readable($file))
			{
				$XMLImportfile = $file;
				break;
			}
		}

		if($XMLImportfile)
		{
			// We cannot rely on themes to include all prefs... so use 'replace'.
			// Overwrite specific core pref and tables entries.
			e107::getXml()->e107Import($XMLImportfile, 'replace', true, false);
		}

		// Create default plugin-table entries.
		e107::getPlugin()->update_plugins_table('update');

		if($themeInfo = $this->getThemeXml($params['theme']))
		{
			if(isset($themeInfo['plugins']['plugin']))
			{
				foreach($themeInfo['plugins']['plugin'] as $k => $plug)
				{
					$this->installPlugin($plug['@attributes']['name']);
				}
			}
		}

		// Save plugin add-on pref-lists. eg. e_latest_list.
		e107::getSingleton('e107plugin')->save_addon_prefs('update');

		$tm = e107::getSingleton('themeHandler');
		$tm->noLog = true; // false to enable log
		$tm->setTheme($params['theme'], false);

		// Admin log fix - don't allow logs to be called inside pref handler.
		// Change to false to enable log.
		e107::getConfig('core')->setParam('nologs', true);
		$prefs = e107::getConfig('core')->getPref();

		$prefs['sitename'] = $params['sitename'];
		$prefs['sitelanguage'] = $params['sitelanguage'];
		$prefs['sitelang_init'] = $params['sitelang_init'];
		$prefs['siteadmin'] = $params['siteadmin'];
		$prefs['siteadminemail'] = $params['siteadminemail'];
		$prefs['install_date'] = $params['install_date'];
		$prefs['siteurl'] = $params['siteurl'];
		$prefs['sitetag'] = $params['sitetag'];
		$prefs['sitedisclaimer'] = $params['sitedisclaimer'];
		$prefs['replyto_name'] = $params['replyto_name'];
		$prefs['replyto_email'] = $params['replyto_email'];

		// Cookie name fix, ended up with 406 error when non-latin words used
		$cookiename = preg_replace('/[^a-z0-9]/i', '', trim($params['sitename']));
		$prefs['cookie_name'] = ($cookiename ? substr($cookiename, 0, 4) . '_' : 'e_') . 'cookie';

		// Set all prefs so that they are available, required for adminReadModules() - it checks which plugins
		// are installed.
		e107::getConfig('core')->setPref($prefs);

		$url_modules = eRouter::adminReadModules();
		$url_locations = eRouter::adminBuildLocations($url_modules);
		$url_config = eRouter::adminBuildConfig(array(), $url_modules);

		$prefs['url_aliases'] = array();
		$prefs['url_config'] = $url_config;
		$prefs['url_modules'] = $url_modules;
		$prefs['url_locations'] = $url_locations;

		eRouter::clearCache();

		$us = e107::getUserSession();

		if($us->passwordAPIExists() === true)
		{
			$prefs['passwordEncoding'] = PASSWORD_E107_PHP;
			$pwdEncoding = PASSWORD_E107_PHP;
		}
		else
		{
			// Default already in default_install.xml
			$pwdEncoding = PASSWORD_E107_MD5;
		}

		// Set prefs, save
		e107::getConfig('core')->setPref($prefs);
		// Save preferences made during install.
		e107::getConfig('core')->save(false, true, false);

		// Create the admin user - replacing any that may be been included in the XML.
		$hash = $us->HashPassword($params['siteadminpass'], $params['siteadminuser'], $pwdEncoding);

		$ip = $_SERVER['REMOTE_ADDR'];
		$userp = "1, '{$params['siteadmin']}', '{$params['siteadminuser']}', '', '" . $hash . "', '', '{$params['siteadminemail']}', '', '', 0, " . time() . ", 0, 0, 0, 0, 0, '{$ip}', 0, '', 0, 1, '', '', '0', '', " . time() . ", ''";
		$qry = "REPLACE INTO {$this->prefix}user VALUES ({$userp})";
		$this->db->gen($qry);

		// Add Default user-extended values;
		$extendedQuery = "REPLACE INTO `{$this->prefix}user_extended` (`user_extended_id` ,	`user_hidden_fields`) VALUES ('1', NULL 	);";
		$this->db->gen($extendedQuery);

		e107::getMessage()->reset(false, false, true);
	}

	/**
	 * Install a plugin.
	 *
	 * @param string $plugin_path
	 *   Plugin folder name.
	 * @param bool $update
	 *   Update plugin add-on pref-lists. eg. e_latest_list.
	 */
	public function installPlugin($plugin_path, $update = false)
	{
		$this->db->gen("SELECT * FROM #plugin WHERE plugin_path = '" . $plugin_path . "' LIMIT 1");
		$row = $this->db->fetch();

		if(!empty($row['plugin_id']))
		{
			e107::getPlugin()->install($row['plugin_id']);
		}

		if($update)
		{
			// Save plugin add-on pref-lists. eg. e_latest_list.
			e107::getSingleton('e107plugin')->save_addon_prefs('update');
		}
	}

	/**
	 * Check theme.xml file of specific theme.
	 *
	 * @param string $theme
	 *   Theme folder name.
	 * @return bool|array
	 *   $xmlArray OR boolean FALSE if result is no array
	 */
	function getThemeXml($theme)
	{
		if(!defined("SITEURL"))
		{
			define("SITEURL", "");
		}

		$path = e_THEME . $theme . "/theme.xml";

		if(!is_readable($path))
		{
			return false;
		}

		$xmlArray = e107::getTheme($theme)->get();

		return (is_array($xmlArray)) ? $xmlArray : false;
	}

}

