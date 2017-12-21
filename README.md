SimpleTest
==========

e107 (v2) plugin - Provides a framework for unit and functional testing. This plugin is modeled after the [SimpleTest PHP library](http://simpletest.sourceforge.net/)

## SimpleTest Testing tutorial

This tutorial will take you through the basics of testing in e107 (v2). By the end you should be able to write your first test! 

### Setup

First, we will need to make sure that the SimpleTest plugin is installed. But before installing, make sure all requirements are met.

**Requirements**

- [Batch API](https://github.com/lonalore/batch) plugin need to be installed
- PHP **cURL** extension
- PHP **DOMDocument** class
- PHP **open_basedir** restriction need to be disabled. Check your webserver configuration or contact your web host.
- [Runkit](https://github.com/zenovich/runkit) for PHP5 / [Runkit7](https://github.com/runkit7/runkit7) for PHP7

Before or after plugin installation, place the following code at the end of your `e107_config.php` file. this is a very basic multisite solution to run Web tests.

```php
if(!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'simpletest') === 0)
{
	$exploded = explode(';', $_SERVER['HTTP_USER_AGENT']);
    $mySQLprefix = $exploded[0] . '_';
    $E107_CONFIG['site_path'] = 'simpletest/' . substr($exploded[0], 10);
}
```

**Settings**

Verbose information logging for test runs is enabled by default. This option provides very detailed feedback in addition to the assertion checks that are displayed on a completed test run report. Enabling this feature is only recommended for development and debugging work, and you can disable verbose logging if you don't need it.

### How e107's SimpleTest works

SimpleTest creates a complete e107 installation and a virtual web browser and then uses the virtual web browser to walk the e107 install through a series of tests, just like you would do if you were doing it by hand.

It's terribly important to realize that each test runs in a completely new e107 instance, which is created from scratch for the test. In other words, none of your configuration and none of your users exists! None of your plugins are installed beyond the default e107 core plugins. If your test sequence requires a privileged user, you'll have to create one (just as you would if you were setting up a manual testing environment from scratch). If plugins have to be installed, you have to install them. If something has to be configured, you'll have to use SimpleTest to do it, because none of the configuration on your current site is in the magically created e107 instance that we're testing. None of the files in your files directory are there, none of the optional plugins are installed, none of the users are created.

![SimpleTest Overview](https://raw.githubusercontent.com/lonalore/simpletest/master/assets/images/readme/simpletest_overview.png)

### Functional Testing

There are five basic steps involved in building a test:

- Creating each .test file in a plugin's 'tests' folder
- Creating the structure (just creating a class that inherits from **e107WebTestCase**)
- Initializing the test case with whatever user creation or configuration needs to be done
- Creating actual tests within the test case
- And, of course, trying desperately to figure out why our test doesn't work the way we expect, and debugging the test (and perhaps the plugin)

To start, we just need a bit of boilerplate extending **e107WebTestCase**.

```php
/**
 * Tests the SimpleTest's internal browser.
 */
class SimpleTestFunctionalTest extends e107WebTestCase {

}
```

To make the test available to the Simpletest testing interface, we implement `getInfo()`. This just provides the user interface information that will show up on the simpletest page after clearing the cache table.

```php
/**
 * Provides metadata about this test.
 *
 * @return array
 *   An array of test metadata with the following keys:
 *   - name: The name of the test.
 *   - description: The description of the test.
 *   - group: The group of the test.
 */
public static function getInfo()
{
	return array(
		'name'        => 'Web runner',
		'description' => 'Tests the SimpleTest\'s internal browser and API\'s.',
		'group'       => 'SimpleTest',
	);
}
```

Next comes the terribly important `setUp()`. Here is where we must do anything that needs to be done to make this e107 instance work the way we want to. We have to think: "What did I have to do to get from a stock e107 install to where I can run this test?". In our case, we know that we had to:

- Enable the Batch API plugin
- Enable the SimpleTest plugin

This work is done by the `setUp()` method:

```php
protected function setUp() {
	parent::setUp(array('batch', 'simpletest'));
}
```

Now we need to create specific tests to exercise the plugin. We just create member functions of our test class, each of which exercises a particular test. All member functions should start with 'test' in lower-case. Any function, with public visibility, that starts this way will automatically be recognized by SimpleTest and run when requested. 

```php
/**
 * Tests the internal browsers functionality.
 */
protected function testInternalBrowser() {
	$this->e107Get(SITEURLBASE);
	$this->assertTrue($this->e107GetHeader('Date'), 'An HTTP header was received.');
}
```

**Note:** each test function you have will create a new set of temporary simpletest tables. This means that whatever you have created in a previous test will not be available anymore in the next.

### e107Get, e107Post and Assertions

Most tests will follow this pattern:

- Do a `e107Get()` to go to a page or a `e107Post()` to POST a form.
- Do one or more assertions to check that what we see on the page is what we should see.

`$this->e107Get($url)` is as easy as it can be: It just goes to the `$url`.

`$this->e107Post($url, $edit_fields, $submit_button_name)` is only slightly more complex. The `$edit_fields` array usually maps to the values of the form you are trying to post to.

And then there are dozens of possible assertions. The easiest of these is `$this->assertText($text_to_find_on_page)`.

### Running the SimpleTest web interface

Next we need to run the test. Here we'll use the web interface to run the test.

Go to the _Admin > Tools > SimpleTest > Tests_ page and find the test that you created, and press the Run tests button. Once the test has run you should see the results, which for this test will pass.

### Unit Testing

SimpleTest also provides an **e107UnitTestCase** as an alternative to the **e107WebTestCase**.

The database tables and files directory are not created for unit tests. This makes them much faster to initialize than functional tests but means that they cannot access the database or the files directory. Calling any e107 function that needs the database will throw exceptions.

### Debugging tests

In the testing settings (_Admin > Tools > SimpleTest > Settings_) there is an option "Provide verbose information when running tests". If you turn this on, every `e107Get()` and every `e107Post()` will be captured as an HTML file, which will be available for you to view in the test results. This is a tremendously important tool.

You can also use `$this->verbose("some message")` and the message you provide will be shown when verbose information is being displayed.

### Events provided by the SimpleTest plugin.

| Event name                     | Description                                                                                    | Event data                                              |
| :----------------------------- |:-----------------------------------------------------------------------------------------------| :-------------------------------------------------------|
| simpletest_test_group_started  | A test group has started. This event is triggered just once at the beginning of a test group.  | N/A                                                     |
| simpletest_test_group_finished | A test group has finished. This event is triggered just once at the end of a test group.       | N/A                                                     |
| simpletest_test_finished       | An individual test has finished. This event is triggered when an individual test has finished. | The results of the test as gathered by e107WebTestCase. |

