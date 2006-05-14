<?php 

/*
Plugin Name: Open Web Analytics
Plugin URI: http://www.openwebanalytics
Description: This plugin enables Wordpress blog owners to use the Open Web Analytics Framework.
Author: Peter Adams
Version: v1.0
Author URI: http://www.openwebanalytics.com
*/

require_once 'owa_controller.php';
require_once 'owa_install.php';
require_once 'owa_env.php';
require_once 'owa_settings_class.php';
require_once 'owa_error.php';
/**
 * WORDPRESS Constants
 * You should not need to change these.
 */

// URL special requests can be intercepted on
define ('OWA_BASE_URL', get_bloginfo('url').'/index.php');

// URL used for graph generation requests
define ('OWA_GRAPH_URL', OWA_BASE_URL);

// URL stem used for inter report navigation
define ('OWA_REPORTING_URL', $_SERVER['PHP_SELF'].'?page=owa/reports');

// Path to images used in reports
define ('OWA_IMAGES_PATH', '../wp-content/plugins/owa/reports/i/');

/**
 * These are set to pass wa the db connection params that wordpress uses. 
 * These are also persisted when in async mode.
 */
define('OWA_DB_NAME', DB_NAME);     // The name of the database
define('OWA_DB_USER', DB_USER);     // Your db username
define('OWA_DB_PASSWORD', DB_PASSWORD); // ...and password
define('OWA_DB_HOST', DB_HOST);     // The host of your db

/**
 * This is the main logger function that calls wa on each normal web request.
 * Application specific request data should be set here. as part of the $app_params array.
 */
function owa_main() {

	$owa = new owa;
	
	// WORDPRESS SPECIFIC DATA //
	
	//Load config from wp_database
	//owa_fetch_config();
	
	// Get the type of page
	$app_params['page_type'] = owa_get_page_type();
	
	//Check to see if this is a Feed Reeder
	if(is_feed()):
		$app_params['is_feedreader'] = true;
	endif;
	
	// Track users by the email address of that they used when posting a comment
	$app_params['user_email'] = $_COOKIE['comment_author_email_'.COOKIEHASH]; 
	
	// Track users who have a named account
	$app_params['user_name'] = $_COOKIE['wordpressuser_'.COOKIEHASH];
	
	// Get Title of Page
	$app_params['page_title'] = owa_get_title($app_params['page_type']);
	
	// Get Feed Tracking Code
	//$app_params['feed_subscription_id'] = ''
	
	// Get Source Tracking code
	//$app_params['source'] = '';
	
	// Provide an ID for this instance in case you want to track multiple blogs/sites seperately
	//$app_params['site_id'] = '';
	
	// Process the request by calling owa
	$owa->process_request($app_params);
	
	return;
}

/**
 * Determines the title of the page being requested
 *
 * @param string $page_type
 * @return string $title
 */
function owa_get_title($page_type) {

	if ($page_type == "Home"):
		$title = get_bloginfo('name');
	elseif ($page_type == "Search Results"):
		$title = "Search Results for \"".$_GET['s']."\"";	
	elseif ($page_type == "Page" || "Post"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Author"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Category"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Month"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Day"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Year"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Time"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Feed"):
		$title = wp_title($sep = '', $display = 0);
	endif;	
	
	return $title;
}

/**
 * Determines the type of page being requested
 *
 * @return string $type
 */
function owa_get_page_type() {	
	
	if (is_home()):
		$type = "Home";
	elseif (is_single()):
		$type = "Post";
	elseif (is_page()):
		$type = "Page";
	elseif (is_author()):
		$type = "Author";
	elseif (is_category()):
		$type = "Category";
	elseif (is_search()):
		$type = "Search Results";
	elseif (is_month()):
		$type = "Month";
	elseif (is_day()):
		$type = "Day";
	elseif (is_year()):
		$type = "Year";
	elseif (is_time()):
		$type = "Time";
	elseif (is_archive()):
		$type = "Archive";
	elseif (is_feed()):
		$type = "Feed";
	endif;
	
	return $type;
}

/**
 * Adds a GUID to the feed URL.
 *
 * @param array $binfo
 * @return string $newbinfo
 */
function add_feed_sid($binfo) {

	global $doing_rss;
	
	if (strstr($binfo, "feed=")):

		$owa_wp = new owa_wp;
		$newbinfo = $owa_wp->add_feed_tracking($binfo);
		
	else: 
	
		$newbinfo = $binfo;
	
	endif;
	
	return $newbinfo;

}

/**
 * Logs comments to session
 *
 */
function owa_is_comment() {
	$owa = new owa;
	$owa->process_comment();
	
	return;

}

/**
 * Adds tracking params to links in feeds
 *
 * @param string $link
 * @return string
 */
function owa_post_link($link) {

	global $doing_rss;
	if($doing_rss):
	
		$owa_wp = new owa_wp;
		$tracked_link = $owa_wp->add_link_tracking($link);
		return $tracked_link;
		
	endif;

}

/**
 * Schema and setting installation
 *
 */
function owa_install() {

	global $user_level;
	
	//check to see if the user has permissions to install or not...
	get_currentuserinfo();
	
	if ($user_level < 8):
   
    	return;
    	
    else:
    	
	    $installer = &owa_install::get_instance('mysql');	    
	    $install_check = $installer->check_for_schema();
	    
	    if ($install_check == false):
		    //Install owa schema
	    	$installer->create_all_tables();
	    else:
	    	// owa already installed
	    	return;
	    endif;
	endif;

	return;
}

/**
 * Adds Analytics sub tab to admin dashboard screens.
 *
 */
function owa_dashboard_view() {

	if (function_exists('add_submenu_page')):
		add_submenu_page('index.php', 'OWA Dashboard', 'Analytics', 8, dirname(__FILE__) . '/reports/dashboard_report.php');
    endif;
    
    return;

}

/**
 * Adds Options page to admin interface
 *
 */
function owa_options() {
	
	if (function_exists('add_options_page')):
		add_options_page('Options', 'OWA', 8, basename(__FILE__), 'owa_options_page');
	endif;
    
    return;
}

function owa_fetch_config() {

	require_once 'owa_settings_class.php';
	
	// Fetch config
	$config = &owa_settings::get_settings();
	$config['db_type'] = 'wordpress';
	$config['report_wrapper'] = 'wordpress.tpl';
	
	return $config;
}

function owa_options_page() {
	
	require_once 'template_class.php';
	
	$config = owa_fetch_config();
	print_r($config);
	//update options
	
	//Setup templates
	$options_page = & new Template;
	$options_page->set_template($options_page->config['report_wrapper']);
	$body = & new Template; 
	$body->set_template('options.tpl');// This is the inner template
	$body->set('config', $config);
	$body->set('page_title', 'OWA Options');
	$options_page->set('content', $body);
	// Make Page
	echo $options_page->fetch();
	
	return;
}

/**
 * Parses string to get the major and minor version of the 
 * instance of wordpress that is running
 *
 * @param string $version
 * @return array
 */
function owa_parse_version($version) {
	
	list($major, $minor, $dot) = split(".", $version);
   
   return array($major, $minor, $dot);
	
}

// WORDPRESS Filter and action hooks.

// Check to see what version of wordpress is running
$owa_wp_version = owa_parse_version($wp_version);

// Create new instance of helper object
$owa_wp = new owa_wp;

// fetch updated config from db if needed
if($owa_wp->config['fetch_config_from_db'] == true):
	
	$owa_wp->load_config_from_db();
	
endif;

if ($owa_wp_version[0] == '1'):

	if (isset($_GET['activate']) && $_GET['activate'] == 'true'):

	add_action('init', 'owa_install');
  
	endif;

elseif ($owa_wp_version[0] == '2'):

	add_action('activate_owa/wp_plugin.php', 'owa_install');

endif;

add_action('template_redirect', 'owa_main');
add_action('wp_footer', array(&$owa_wp, 'add_tag'));
add_filter('post_link', 'owa_post_link');
add_filter('bloginfo', 'add_feed_sid');
add_action('admin_menu', 'owa_dashboard_view');
add_action('init', array(&$owa_wp, 'init_action'));
add_action('comment_post', 'owa_is_comment');
add_action('admin_menu', 'owa_options');

////////// FORM HANDLERS

//if (is_plugin_page()):
		if (isset($_POST['wa_update_options'])):
				
			//create config array
			$new_config = array();
			foreach ($_POST as $key => $value) {
				
				if ($key != 'update_options'):
					$new_config[$key] = $value;
				endif;
			}
		
			owa_settings::save($new_config);		
			
		endif;
		
		if (isset($_POST['wa_reset_options'])):
		
			$owa_wp->reset_config();	
			
		endif;
	//endif;

	
	

/**
	 * Wordpress plugin class
	 * 
	 * @author      Peter Adams <peter@openwebanalytics.com>
	 * @copyright   Copyright &copy; 2006 Peter Adams <peter@openwebanalytics.com>
	 * @license     http://www.gnu.org/copyleft/gpl.html GPL v2.0
	 * @category    owa
	 * @package     owa
	 * @version		$Revision$	      
	 * @since		owa 1.0.0
	 */
		
class owa_wp {
	
	/**
	 * Configuration
	 *
	 * @var array
	 */
	var $config;
	
	/**
	 * Error handler
	 * 
	 * @var object
	 */
	var $e;
	
	/**
	 * Database Type
	 *
	 * @var string
	 */
	var $db_type = 'wordpress';
	
	/**
	 * Outer Template for reports and admin screens
	 *
	 * @var string
	 */
	var $report_wrapper = 'wordpress.tpl';
	
	/**
	 * Constructor
	 *
	 * @return owa_wp
	 */
	function owa_wp() {
		
		$this->config = &owa_settings::get_settings();
		$this->config['db_type'] = $this->db_type;
		$this->config['report_wrapper'] = $this->report_wrapper;
	
		$this->e = &owa_error::get_instance();
		return;
	}
	
	function add_link_tracking($link) {
	
		if (!empty($_GET[$this->config['feed_subscription_id']])):
			return $link."&amp;"."from=feed"."&amp;".$this->config['feed_subscription_id']."=".$_GET[$this->config['feed_subscription_id']];
		else:
			return $link;
		endif;
	
	}
	
	function add_feed_tracking($binfo) {
		
		$guid = crc32(posix_getpid().microtime());
		
		return $binfo."&".$this->config['ns'].$this->config['feed_subscription_id']."=".$guid;
	}
	
	function add_tag() {
		
		if (empty($_COOKIE[$this->config['ns'].$this->config['visitor_param']]) && empty($_COOKIE[$this->config['ns'].$this->config['first_hit_param']])):
			$bug  = "<script language=\"JavaScript\" type=\"text/javascript\">";
			$bug .= "document.write('<img src=\"".OWA_BASE_URL."?owa_action=".$this->config['first_hit_param']."\">');</script>";
			$bug .= "<noscript><img src=\"".OWA_BASE_URL."?owa_action=".$this->config['first_hit_param']."\"></noscript>";		
			echo $bug;
		endif;
		
		return;
	}
	
	function init_action() {
		
		if (isset($_GET['owa_action'])):
			$this->e->debug('Received special OWA request. OWA action = '.$_GET['owa_action']);
		endif;
			
		switch ($_GET['owa_action']) {
			
			case "first_hit":
				$this->first_request_handler();	
				exit;
				break;		
			case "graph":
				$this->graph_request_handler();
				exit;
				break;
				
		}
		
		return;
		
	}
	
	function first_request_handler() {
		
		if (isset($_COOKIE[$this->config['ns'].$this->config['first_hit_param']])):
			$owa = new owa;
			$owa->process_first_request();
		endif;
			
		header('Content-type: image/gif');
		header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"');
		header('Expires: Sat, 22 Apr 1978 02:19:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
				
		printf(
		  '%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%',
		  71,73,70,56,57,97,1,0,1,0,128,255,0,192,192,192,0,0,0,33,249,4,1,0,0,0,0,44,0,0,0,0,1,0,1,0,0,2,2,68,1,0,59
		);
			
		return;
	}
	
	function graph_request_handler() {
		
		$params = array(
				'api_call' 		=> $_GET['graph'],
				'period'			=> $_GET['period']
			
			);
			
		$owa = new owa;
		$owa->get_graph($params);
		return;
	}
	
	function save_config($config) {
		
		$this->config->save($config);
		
		return;
	}
	
	function reset_config() {
			
		$config = $this->config->get_default_config();
		$this->config->save($config);
		return;
				
	}
	
	function load_config_from_db() {
		
		$config_from_db = owa_settings::fetch($this->config['site_id']);
				
		if (!empty($config_from_db)):
			
			foreach ($config_from_db as $key => $value) {
			
				$this->config[$key] = $value;
			
			}
					
		endif;
		
		return;
	}
		
	
}

?>