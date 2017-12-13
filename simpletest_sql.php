# ---------------------------
# Stores simpletest messages.
# ---------------------------

CREATE TABLE `simpletest` (
`message_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary Key. Unique simpletest message ID.',
`test_id` int(11) NOT NULL DEFAULT '0' COMMENT 'Test ID. Messages belonging to the same ID are reported together.',
`test_class` varchar(255) NOT NULL DEFAULT '' COMMENT 'The name of the class that created this message.',
`status` varchar(10) NOT NULL DEFAULT '' COMMENT 'Message status. Core understands pass, fail, exception.',
`message` text COMMENT 'The message itself.',
`message_group` varchar(255) NOT NULL DEFAULT '' COMMENT 'The message group this message belongs to. For example warning, browser, user.',
`function` varchar(255) NOT NULL DEFAULT '' COMMENT 'Name of the assertion function or method that created this message.',
`line` int(11) NOT NULL DEFAULT '0' COMMENT 'Line number on which the function is called.',
`file` varchar(255) NOT NULL DEFAULT '' COMMENT 'Name of the file where the function is called.',
PRIMARY KEY (`message_id`),
KEY `reporter` (`test_class`, `message_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

# -----------------------------------------------------------------------------------------------
# Stores simpletest test IDs, used to auto-increment the test ID so that a fresh test ID is used.
# -----------------------------------------------------------------------------------------------

CREATE TABLE `simpletest_test_id` (
`test_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary Key. Unique simpletest ID used to group test results together. Each time a set of tests are run a new test ID is used.',
`last_prefix` varchar(60) NOT NULL DEFAULT '' COMMENT 'The last database prefix used during testing.',
PRIMARY KEY (`test_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
