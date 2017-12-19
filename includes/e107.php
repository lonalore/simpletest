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
	 * Constructor.
	 *
	 * @param string $prefix
	 *   Database prefix.
	 * @param string $site_hash
	 *   Site [hash] to be used for system and media folders.
	 */
	public function __construct($prefix, $site_hash)
	{
		global $mySQLdefaultdb;

		$this->prefix = $prefix;
		$this->site_hash = $site_hash;
		$this->db = e107::getDb();

		if(!empty($this->prefix))
		{
			$this->db->database($mySQLdefaultdb, $this->prefix);
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
	public function importConfig()
	{
		$coreConfig = e_CORE . "xml/default_install.xml";
		e107::getXml()->e107Import($coreConfig, 'add', true, false, $this->db);
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
			e107::getPlugin()->install($row['plugin_id']);
			e107::getMessage()->reset(false, false, true);
		}
	}

}

