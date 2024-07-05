<?php

/**
 * @file
 * Configuration file for the OSTicket API Extension plugin.
 */

// Include configuration file for OSTicket.
include 'ost-config.php';

// Define states constants.
define('STATE_OPEN', 1);
define('STATE_RESOLVE', 2);
define('STATE_CLOSE', 3);
define('STATE_ARCHIVED', 4);
define('STATE_RESOLVED', 5);
define('STATE_SUSPENDED', 6);

// Id of Ticket Details in table ost_form
define('TICKET_DETAILS_FORM', 2);

// Define names of database tables.
define('API_NEW_TABLE', TABLE_PREFIX . 'api_key_extension'); // Table for new API keys.
define('SUSPEND_NEW_TABLE', TABLE_PREFIX . 'suspended_ticket'); // Table for suspended tickets.

// Define directories to SQL files.
define('PRJ_PLUGIN_DIR', INCLUDE_DIR . 'plugins/OSTicket-API-Extension/'); // Plugin directory.
define('SQL_SCRIPTS_DIR', PRJ_PLUGIN_DIR . 'sql/'); // Directory for SQL scripts.
define('PRJ_API_DIR', PRJ_PLUGIN_DIR . 'api/'); // Directory for API files.
define('PRJ_UTIL_DIR', PRJ_PLUGIN_DIR . 'util/'); // Directory for Util files.

// Define names of SQL files.
define('SAVED_DATA_SQL', SQL_SCRIPTS_DIR . 'savedData.sql'); // SQL file for saved data.
define('UNINSTALL_SCRIPT', SQL_SCRIPTS_DIR . 'uninstallScript.sql'); // SQL file for uninstallation.
define('INSTALL_SCRIPT', SQL_SCRIPTS_DIR . 'scripts.sql'); // SQL file for installation.