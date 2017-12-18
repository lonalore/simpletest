<?php


/**
 * Helper class.
 */
class SimpleTestInstall
{

	protected $prefix;
	protected $site_hash;

	public function __construct($prefix, $site_hash)
	{
		$this->prefix = $prefix;
		$this->site_hash = $site_hash;
	}

	/**
	 * Create Core MySQL tables.
	 */
	public function createTablesWithPrefix()
	{
		if (empty($this->prefix))
		{
			return false;
		}

		global $mySQLdefaultdb;

		$db = e107::getDb();
		$db->database($mySQLdefaultdb, $this->prefix);

		$filename = e_CORE . 'sql/core_sql.php';
		$fd = fopen($filename, "r");
		$sql_data = fread($fd, filesize($filename));
		// Strip comments.
		$sql_data = preg_replace("#\/\*.*?\*\/#mis", '', $sql_data);
		fclose($fd);

		preg_match_all("/create(.*?)(?:myisam|innodb);/si", $sql_data, $result);

		// Force UTF-8 again
		$this->dbqry('SET NAMES `utf8`');

		$srch = array("CREATE TABLE", "(");
		$repl = array("DROP TABLE IF EXISTS", "");

		foreach($result[0] as $sql_table)
		{
			$sql_table = preg_replace("/create table\s/si", "CREATE TABLE {$this->prefix}", $sql_table);

			// Drop existing tables before creating.
			$tmp = explode("\n", $sql_table);
			$drop_table = str_replace($srch, $repl, $tmp[0]);
			$this->dbqry($drop_table);
		}

		return true;
	}

	/**
	 * Import and generate preferences and default content.
	 */
	public function importConfiguration($theme = 'bootstrap3', $generate_content = true, $install_plugins = true)
	{
		// PRE-CONFIG start - create and register blank config instances - do not load!
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

		$themeImportFile = array();
		$themeImportFile[0] = e_THEME . $theme . "/install.xml";
		$themeImportFile[1] = e_THEME . $theme . "/install/install.xml";

		$XMLImportfile = false;

		if($generate_content)
		{
			foreach($themeImportFile as $file)
			{
				if(is_readable($file))
				{
					$XMLImportfile = $file;
					break;
				}
			}
		}

		if(!defined('PREVIEWTHEMENAME'))
		{
			define('PREVIEWTHEMENAME', ""); // Notice Removal.
		}

		include_lan(e_LANGUAGEDIR . "/admin/lan_theme.php");

		$coreConfig = e_CORE . 'xml/default_install.xml';
		e107::getXml()->e107Import($coreConfig, 'replace', true, false);

		// We cannot rely on themes to include all prefs..so use 'replace'.
		if($XMLImportfile)
		{
			// Overwrite specific core pref and tables entries.
			e107::getXml()->e107Import($XMLImportfile, 'replace', true, false);
		}

		// Create default plugin-table entries.
		e107::getPlugin()->update_plugins_table('update');

		// Install Theme-required plugins.
		if($install_plugins)
		{
			if($themeInfo = $this->get_theme_xml($theme))
			{
				if(isset($themeInfo['plugins']['plugin']))
				{
					foreach($themeInfo['plugins']['plugin'] as $k => $plug)
					{
						$this->installPlugin($plug['@attributes']['name']);
					}
				}
			}
		}

		// Save plugin addon pref-lists. eg. e_latest_list.
		e107::getSingleton('e107plugin')->save_addon_prefs('update');

		$tm = e107::getSingleton('themeHandler');
		$tm->noLog = true; // false to enable log
		$tm->setTheme($theme, false);

		// Admin log fix - don't allow logs to be called inside pref handler
		// FIX
		e107::getConfig('core')->setParam('nologs', true); // change to false to enable log
		$prefs = e107::getConfig('core')->getPref();

		// Set Preferences defined during install - overwriting those that may exist in the XML.
		$prefs['sitelanguage'] = 'English';
		$prefs['sitelang_init'] = 'English';

		$prefs['siteadmin'] = 'admin';
		$prefs['siteadminemail'] = 'admin@example.com';

		$prefs['install_date'] = time();
		$prefs['siteurl'] = e_HTTP;

		$prefs['sitetag'] = "e107 Website System";
		$prefs['sitedisclaimer'] = '';

		$prefs['replyto_name'] = 'admin';
		$prefs['replyto_email'] = 'admin@example.com';

		// Cookie name fix, ended up with 406 error when non-latin words used
		$cookiename = preg_replace('/[^a-z0-9]/i', '', trim($prefs['sitename']));
		$prefs['cookie_name'] = ($cookiename ? substr($cookiename, 0, 4) . '_' : 'e_') . 'cookie';

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
			$pwdEncoding = PASSWORD_E107_MD5;
		}

		// Set prefs, save
		e107::getConfig('core')->setPref($prefs);
		e107::getConfig('core')->save(false, true, false);

		// Create the admin user - replacing any that may be been included in the XML.

		$hash = $us->HashPassword('admin', 'admin', $pwdEncoding);

		$ip = $_SERVER['REMOTE_ADDR'];
		$userp = "1, 'Administrator', 'admin', '', '" . $hash . "', '', 'admin@example.com', '', '', 0, " . time() . ", 0, 0, 0, 0, 0, '{$ip}', 0, '', 0, 1, '', '', '0', '', " . time() . ", ''";
		$this->dbqry("REPLACE INTO {$this->prefix}user VALUES ({$userp})");

		// Add Default user-extended values;
		$extendedQuery = "REPLACE INTO `{$this->prefix}user_extended` (`user_extended_id` ,	`user_hidden_fields`) VALUES ('1', NULL);";
		$this->dbqry($extendedQuery);

		e107::getDb()->close();
		e107::getMessage()->reset(false, false, true);
	}

	/**
	 * Install a Theme required plugin.
	 *
	 * @param string $plugpath - plugin folder name
	 * @return void
	 */
	public function installPlugin($plugpath)
	{
		e107::getDb()->gen("SELECT * FROM #plugin WHERE plugin_path = '" . $plugpath . "' LIMIT 1");
		$row = e107::getDb()->fetch();
		e107::getPlugin()->install_plugin($row['plugin_id']);
		e107::getMessage()->reset(false, false, true);
	}

	/**
	 * get_theme_xml - check theme.xml file of specific theme
	 *
	 * @param string $theme_folder
	 * @return array $xmlArray OR boolean FALSE if result is no array
	 */
	function get_theme_xml($theme_folder)
	{
		if(!defined("SITEURL"))
		{
			define("SITEURL", "");
		}

		$path = e_THEME . $theme_folder . "/theme.xml";

		if(!is_readable($path))
		{
			return array();
		}

		$xmlArray = e107::getTheme($theme_folder)->get();

		return (is_array($xmlArray)) ? $xmlArray : array();
	}

	function dbqry($qry)
	{
		$sql = e107::getDb();
		return $sql->db_Query($qry);
	}
}

