<?php

	/*
	Plugin Name: wpShopGermany - IT-Recht Kanzlei
	Plugin URI: http://wpshopgermany.maennchen1.de/
	Description: IT-Recht Kanzlei Integration in Wordpress/wpShopGermany
	Author: maennchen1.de
	Version: 1.0
	Author URI: http://maennchen1.de/
	*/
 
	require_once dirname(__FILE__).'/functions.php';
	require_once dirname(__FILE__).'/classes/wpsg_itrecht.class.php';

	function wpsg_itrecht__install()
	{
		 
	} // function wpsg_itrecht__install()
	
	function wpsg_itrecht_uninstall()
	{
		
		
	} // function wpsg_itrecht_uninstall()

	$wpsg_itrecht = new wpsg_itrecht();
	 
	// Shortcodes
	$arPageTypes = $wpsg_itrecht->getPageTypes();
	
	foreach ($arPageTypes as $page_key => $page)
	{

		add_shortcode('wpsg_itrecht_'.$page_key, array($wpsg_itrecht, 'sc_wpsg_itrecht_'.$page_key));
		
	}
	
	if (is_admin())
	{
	
		add_action('admin_menu', array(&$wpsg_itrecht, "admin_menu"));
	
	}
	
	add_action('wp_loaded', array(&$wpsg_itrecht, 'wp_loaded'));
	
	register_activation_hook(__FILE__, 'wpsg_itrecht__install');
	register_deactivation_hook(__FILE__, 'wpsg_itrecht_uninstall');
	
?>