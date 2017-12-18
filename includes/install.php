<?php


/**
 * Helper class.
 */
class SimpleTestInstall
{

	protected $prefix;

	protected $site_hash;

	protected $db;

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
	public function createTablesWithPrefix()
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
	public function importConfiguration()
	{
		$coreConfig = e_CORE . "xml/default_install.xml";
		e107::getXml()->e107Import($coreConfig, 'add', true, false, $this->db);
	}

	/**
	 * Install a Theme required plugin.
	 *
	 * @param string $plugpath - plugin folder name
	 * @return void
	 */
	public function installPlugin($plugpath)
	{
		$this->db->gen("SELECT * FROM #plugin WHERE plugin_path = '" . $plugpath . "' LIMIT 1");
		$row = $this->db->fetch();
		e107::getPlugin()->install_plugin($row['plugin_id']);
		e107::getMessage()->reset(false, false, true);
	}

}

