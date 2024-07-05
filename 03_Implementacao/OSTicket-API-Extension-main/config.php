<?php

/**
 * @file
 * PluginConfig class extension for the OSTicket API Extension plugin.
 */

require_once(INCLUDE_DIR.'/class.forms.php');

/**
 * Class PluginConfigExtension.
 *
 * This is the class that stores all configuration values of the plugin.
 */
class PluginConfigExtension extends PluginConfig {

    /**
     * Provides compatibility function for versions of osTicket prior to translation support (v1.9.4).
     * @return array from Plugin::translate('auth-passthru').
     */
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-passthru');
    }

    /**
     * This function is where all configuration options for the plugin are defined.
     * Every option is a field and is stored as an element of an array.
     * 
     * @return array with all the configuration options fields as values.
     */
    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'title' => new SectionBreakField(array(
                'label' => $__('OSTicket API Extension Configuration Option'),
            )),
            'username' => new TextboxField(array(
                'label' => $__('Username'),
                'required' => true,
                'configuration' => array('size'=>40),
                'hint' => $__('Admin username to add the first API key.'),
            )),
            'save_info' => new BooleanField(array(
                'id' => 'save_info',
                'label' => $__('Save New Tables Info'),
                'default' => false,
                'configuration' => array(
                    'desc' => $__('Saves all values inside the tables added by this plugin after deactivating it, 
                    so when the plugin is activated again it has all the same data as before.')
                )
            )),
            'apikey' => new TextboxField(array(
                'label' => $__('Your API Key'),
                'configuration' => array('size'=>40),
            )),
        );
    }

    /**
     * Pre saves the configuration options.
     * 
     * @return boolean true;
     */
    function pre_save(&$config, &$errors) {
        global $msg;

        list($__, $_N) = self::translate();
        if (!$errors)
            $msg = $__('Configuration updated successfully');

        return true;
    }

    /**
     * Post saves the configuration options.
     * 
     * @return boolean true;
     */
    public function post_save(&$config, &$errors) {
        global $msg;
    
        // Verifica se a opção 'save_on_deactivate' foi selecionada
        if (isset($config['save_info'])) {
            $this->set('save_info', $config['save_info']);
            $msg = 'Save on deactivate setting updated.';
        }
    
        return true;
    }
}