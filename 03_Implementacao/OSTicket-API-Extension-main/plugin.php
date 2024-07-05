<?php

/**
 * @file
 * Configuration file for the OSTicket API Extension plugin.
 */

/**
 * Plugin configuration array for the OSTicket API Extension.
 *
 * This array defines the settings and metadata for the OSTicket API Extension plugin.
 *
 * @return array An array containing plugin configuration details.
 */
return array(
    /**
     * Unique identifier for the plugin.
     *
     * This ID is used to identify the plugin internally.
     */
    'id' => 'proposal-15:extension',

    /**
     * Version number of the plugin.
     *
     * The version number follows the format [major].[minor].
     */
    'version' => '0.1',

    /**
     * Name of the plugin.
     *
     * The display name of the OSTicket API Extension plugin.
     */
    'name' => 'OSTicket API Extension',

    /**
     * Author(s) of the plugin.
     *
     * The name(s) of the individual(s) or group responsible for developing the plugin.
     */
    'author' => 'Grupo Proposta 15',

    /**
     * Description of the plugin.
     *
     * Provides an overview of the functionality added by the OSTicket API Extension.
     */
    'description' => 'Adds extra functionality to OSTicket API.',

    /**
     * Plugin entry point.
     *
     * The file and class name that serves as the main entry point for the plugin.
     */
    'plugin' => 'extension.php:PluginExtension'
);

?>
