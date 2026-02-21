<?php
/**
 * Setup functions on activate & deactivate events:
 * - Initialize custom options, database, etc.
 * - Upgrade custom options, database, etc.
 * - Cleanup on deactivate
 */

// Direct Access prevention
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'classes/base.php';

class ssw_Setup extends ssw_Base {

	/**
	 * Specify all codes required for plugin activation here.
	 */
	public function activate() {
		// Initialize custom things on plugin activation
		$this->install();
	}

	/**
	 * Specify all codes required for plugin deactivation here.
	 */
	public function deactivate() {
	}

	/**
	 * Specify all codes required for plugin uninstall here.
	 *
	 */
	public function uninstall() {
	}
	

	public function install() {
		
		// Initialize plugin options
		$this->initOptions();
	}

	/**
	 * Storing custom options
	 */
	public function initOptions() {
	}

}
