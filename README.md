SimpleTest
==========

e107 (v2) plugin - Provides a framework for unit and functional testing.

> This plugin is under active development! Please do not use it!

### Requirements

- [Batch API](https://github.com/lonalore/batch) plugin
- PHP **cURL** extension
- PHP **DOMDocument** class
- PHP **open_basedir** restriction need to be disabled. Check your webserver configuration or contact your web host.

### How e107's SimpleTest works

SimpleTest creates a complete e107 installation and a virtual web browser and then uses the virtual web browser to walk the e107 install through a series of tests, just like you would do if you were doing it by hand. It's terribly important to realize that each test runs in a completely new e107 instance, which is created from scratch for the test. In other words, none of your configuration and none of your users exists! None of your plugins are installed beyond the default e107 core plugins. If your test sequence requires a privileged user, you'll have to create one (just as you would if you were setting up a manual testing environment from scratch). If plugins have to be installed, you have to install them. If something has to be configured, you'll have to use SimpleTest to do it, because none of the configuration on your current site is in the magically created e107 instance that we're testing. None of the files in your files directory are there, none of the optional plugins are installed, none of the users are created.

![SimpleTest Overview](https://raw.githubusercontent.com/lonalore/simpletest/master/assets/images/readme/simpletest_overview.png)

### Unit Testing

SimpleTest also provides an **e107UnitTestCase** as an alternative to the **e107WebTestCase**.

The database tables and files directory are not created for unit tests. This makes them much faster to initialize than functional tests but means that they cannot access the database or the files directory. Calling any e107 function that needs the database will throw exceptions.

### Events provided by the SimpleTest plugin.

| Event name                     | Description                                                                                    | Event data                                              |
| :----------------------------- |:-----------------------------------------------------------------------------------------------| :-------------------------------------------------------|
| simpletest_test_group_started  | A test group has started. This event is triggered just once at the beginning of a test group.  | N/A                                                     |
| simpletest_test_group_finished | A test group has finished. This event is triggered just once at the end of a test group.       | N/A                                                     |
| simpletest_test_finished       | An individual test has finished. This event is triggered when an individual test has finished. | The results of the test as gathered by e107WebTestCase. |

### Setup

Copy the following code at the end of your `e107_config.php` file.

```php
if(!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'simpletest') === 0)
{
	$exploded = explode(';', $_SERVER['HTTP_USER_AGENT']);
	// Use the test tables.
	$mySQLprefix = $exploded[0] . '_';
	// Change system folders.
	$MEDIA_DIRECTORY .= 'simpletest/';
	$SYSTEM_DIRECTORY .= 'simpletest/';
	// Set site [hash] for system folders.
	$E107_CONFIG['site_path'] = substr($exploded[0], 10);
}
```
