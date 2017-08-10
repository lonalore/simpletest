SimpleTest
==========

e107 (v2) plugin - Provides a framework for unit and functional testing.

> This plugin is under active development! Please do not use it!

### Requirements

- SimpleTest requires the PHP **cURL** library to be available.
- SimpleTest requires the **DOMDocument** class to be available.
- SimpleTest requires the PHP **open_basedir** restriction to be disabled. Check your webserver configuration or contact your web host.

### How e107's SimpleTest works

SimpleTest creates a complete e107 installation and a virtual web browser and then uses the virtual web browser to walk the e107 install through a series of tests, just like you would do if you were doing it by hand. It's terribly important to realize that each test runs in a completely new e107 instance, which is created from scratch for the test. In other words, none of your configuration and none of your users exists! None of your plugins are installed beyond the default e107 core plugins. If your test sequence requires a privileged user, you'll have to create one (just as you would if you were setting up a manual testing environment from scratch). If plugins have to be installed, you have to install them. If something has to be configured, you'll have to use SimpleTest to do it, because none of the configuration on your current site is in the magically created e107 instance that we're testing. None of the files in your files directory are there, none of the optional plugins are installed, none of the users are created.

![SimpleTest Overview](https://www.dropbox.com/s/d5wehaxgil5kjrq/simpletest_overview.png?dl=1)

### Unit Testing

SimpleTest also provides an **e107UnitTestCase** as an alternative to the **e107WebTestCase**.

The database tables and files directory are not created for unit tests. This makes them much faster to initialize than functional tests but means that they cannot access the database or the files directory. Calling any e107 function that needs the database will throw exceptions.
