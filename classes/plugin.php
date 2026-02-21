<?php
/**
 * The core plugin class.
 *
 */

// Direct Access prevention
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'classes/setup.php';

class ssw_Plugin extends ssw_Setup {
	public $config;
	
	public function __construct($config) {
		$this->config = $config;
		add_action('init', array(&$this, 'init'));
	}

	public function init() {

	}

}