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
	public function importDefaultConfig()
	{
		// $coreConfig = e_CORE . "xml/default_install.xml";
		// e107::getXml()->e107Import($coreConfig, 'add', true, false, $this->db);

		$data = array(
			'prefs'    => array(),
			'database' => array(),
		);

		// Parse default config file into data array.
		$this->parseConfigFile(e_CORE . "xml/default_install.xml", $data);

		// Try to find theme config file and parse it into data array.
		if(!empty($data['SitePrefs']['sitetheme']))
		{
			$sitetheme = $data['SitePrefs']['sitetheme'];

			if($sitetheme === 'bootstrap')
			{
				$sitetheme = 'bootstrap3';
			}

			$themeImportFile = array();
			$themeImportFile[0] = e_THEME . $sitetheme . "/install.xml";
			$themeImportFile[1] = e_THEME . $sitetheme . "/install/install.xml";

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
				$this->parseConfigFile($XMLImportfile, $data);
			}
		}

		// Insert core prefs into DB.
		if(!empty($data['prefs']))
		{
			foreach($data['prefs'] as $name => $value)
			{
				$dbdata = (string) e107::getArrayStorage()->WriteArray($value, false);
				$this->db->gen("REPLACE INTO `#core` (e107_name, e107_value) values ('{$name}', '" . addslashes($dbdata) . "') ");
			}
		}

		// Insert data into DB.
		if(!empty($data['database']))
		{
			foreach($data['database'] as $query)
			{
				$this->db->insert($query['table'], $query['data']);
			}
		}
	}

	/**
	 * Helper method to parse config file into data array.
	 */
	private function parseConfigFile($file, &$data)
	{
		$xmlArray = e107::getXml()->loadXMLfile($file, 'advanced');

		if(!empty($xmlArray['prefs']))
		{
			foreach($xmlArray['prefs'] as $type => $array)
			{
				$pArray = e107::getXml()->e107ImportPrefs($xmlArray, $type);

				foreach($pArray as $pname => $pval)
				{
					$data['prefs'][$this->aliases[$type]][$pname] = $pval;

					if($type === 'core')
					{
						$data['prefs'][$this->aliases[$type] . '_Backup'][$pname] = $pval;
					}
				}
			}
		}


		if(!empty($xmlArray['pluginPrefs']))
		{
			foreach($xmlArray['pluginPrefs'] as $type => $array)
			{
				$pArray = e107::getXml()->e107ImportPrefs($xmlArray, $type, 'plugin');

				foreach($pArray as $pname => $pval)
				{
					$data['prefs']['plugin_' . $pname] = $pval;
				}
			}
		}


		if(vartrue($xmlArray['database']))
		{
			foreach($xmlArray['database']['dbTable'] as $val)
			{
				$table = $val['@attributes']['name'];

				if(!isset($val['item']))
				{
					continue;
				}

				foreach($val['item'] as $item)
				{
					$insert_array = array();

					foreach($item['field'] as $f)
					{
						$fieldkey = $f['@attributes']['name'];
						$fieldval = (isset($f['@value'])) ? e107::getXml()->e107ImportValue($f['@value']) : "";
						$insert_array[$fieldkey] = $fieldval;
					}

					$data['database'][] = array(
						'table' => $table,
						'data'  => $insert_array,
					);
				}
			}
		}
	}

	/**
	 * Install a plugin.
	 *
	 * @param string $plugin_path
	 *   Plugin folder name.
	 */
	public function installPlugin($plugin_path)
	{
		$this->db->gen("SELECT * FROM #plugin WHERE plugin_path = '" . $plugin_path . "' LIMIT 1");
		$row = $this->db->fetch();

		if(!empty($row['plugin_id']))
		{
			// TODO
			// e107::getPlugin() works with the current DB, so we need to find a way to install
			// plugin on the test site.
			// e107::getPlugin()->install($row['plugin_id']);
		}
	}

}

