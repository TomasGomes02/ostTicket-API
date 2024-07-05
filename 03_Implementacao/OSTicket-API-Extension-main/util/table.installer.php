<?php

/**
 * @file
 * SetupWizard class extension for the OSTicket API Extension plugin.
 */

include_once 'plugin.config.php';
require_once 'class.setup.php';

/**
 * Class TableInstaller.
 *
 * This class extends the SetupWizard class to be able to run our own sql scripts.
 */
class TableInstaller extends SetupWizard {
    
	/**
     * Runs a sql script with specified path and prefix.
     *
     * @param string the path of the sql script that need to be runned.
	 * 
	 * @return boolean true if sql runned correctly, false if not.
     */
    function runJob($schemaFile) {
		// checks if file exists and if script runned correctly
		if (!file_exists ($schemaFile) || !$this->load_sql_file($schemaFile, TABLE_PREFIX, true, false)) {
			return false;
		}
		return true;
	}
}
