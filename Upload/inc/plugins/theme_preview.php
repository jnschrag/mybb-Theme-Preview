<?php
/**
 * Theme Preview
 * Copyright 2013 Jacque Schrag
 */

 // Disallow Direct Access
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/*
*	Plugin Information
*/
function theme_preview_info() {
    
    return array(
        "name" => "Theme Preview",
        "description" => "Allows users to view a preview of themes when selecting.",
        "website"			=> "http://github.com/jnschrag/mybb-HTML-usergroups",
		"author"			=> "Jacque Schrag",
		"authorsite"		=> "http://jacqueschrag.com",
		"version"			=> "1.0",
		"guid"				=> "",
		"compatibility"		=> "*"       
    );   
}
/*
*	End Plugin Information
*/

/*
*	Plugin Install
*/
function theme_preview_install() {
    global $mybb, $db, $cache;
    
    $db->write_query("ALTER TABLE `".TABLE_PREFIX."themes` ADD `author` VARCHAR(100) NOT NULL, ADD `preview` VARCHAR(100) NOT NULL;");
}
/*
*	End Plugin Install
*/

/*
*	Check if plugin is installed
*/
function theme_preview_is_installed() {
    global $db;
    return $db->field_exists("author", "themes");
}
/*
*	End Check if plugin is installed
*/

function theme_preview_activate() {
	global $db;

	$settings_group = array(
        "gid" => "",
        "name" => "theme_preview",
        "title" => "Theme Preview",
        "description" => "Settings for the Theme Preview Plugin",
        "disporder" => "0",
        "isdefault" => "0",
    );
    
    $db->insert_query("settinggroups", $settings_group);
    $gid = $db->insert_id();

    $setting[0] = array(
	    "name" => "theme_preview_on",
	    "title" => "Do you want the Theme Preview Plugin On?",
	    "description" => "Select Yes if you would like this plugin to run.",
	    "optionscode" => "yesno",
	    "value" => "1",
	    "disporder" => "1",
	    "gid" => $gid,
	);
	foreach ($setting as $row) {
	    $db->insert_query("settings", $row);
	}
    rebuild_settings();

	$template1 = array(
		"tid" => NULL,
		"title" => "theme_preview_item",
		"template" => $db->escape_string('
						<label for="{$item[\\\'tid\\\']}"><strong>{$item[\\\'name\\\']}</strong></label><br />
						<img src="/images/skins/previews/{$item[\\\'preview\\\']}" style="border:1px solid #000;"/><br />
						Theme Author: {$item[\\\'author\\\']}<br />
						<input type="radio" name="style" value="{$item[\\\'tid\\\']}"{$item[\\\'selected\\\']}>
					</div>'),
		"sid" => "-1"
	);
	$db->insert_query("templates", $template1);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_options", "#".preg_quote('{$stylelist}')."#i", "{$themeselection}");
}

// This function runs when the plugin is deactivated.
function theme_preview_deactivate()
{
	global $db;
	$query = $db->simple_select("settinggroups", "gid", "name='theme_preview'");
    $gid = $db->fetch_field($query, 'gid');
    $db->delete_query("settinggroups", "gid='".$gid."'");
    $db->delete_query("settings", "gid='".$gid."'");
    $db->delete_query("templates", "title LIKE '%theme_preview%'");
    rebuild_settings();

    include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_options", "#".preg_quote('{$themeselection}')."#i", "{$stylelist}");
}

/*
*	Plugin Uninstall
*/
function theme_preview_uninstall() {
    global $db, $cache;
    
    $db->query("ALTER TABLE `".TABLE_PREFIX."themes` DROP `author`, DROP `preview`;");
}
/*
*	End Plugin Uninstall
*/

function theme_selector($name, $selected="", $tid=0, $depth="", $usergroup_override=false)
{
	global $db, $themeselector, $tcaches, $lang, $mybb, $limit, $templates, $item, $sel;


	if($tid == 0)
	{
		$tid = 1;
	}

	if(!is_array($tcaches))
	{
		$query = $db->simple_select("themes", "name, pid, tid, allowedgroups, preview, author", "pid != '0'", array('order_by' => 'pid, name'));

		while($item = $db->fetch_array($query))
		{
			$tcaches[$item['pid']][$item['tid']] = $item;
		}
	}

	if(is_array($tcaches[$tid]))
	{
		// Figure out what groups this user is in
		if($mybb->user['additionalgroups'])
		{
			$in_groups = explode(",", $mybb->user['additionalgroups']);
		}
		$in_groups[] = $mybb->user['usergroup'];

		foreach($tcaches[$tid] as $item)
		{
			$item['selected'] = "";
			// Make theme allowed groups into array
			$is_allowed = false;
			if($item['allowedgroups'] != "all")
			{
				$allowed_groups = explode(",", $item['allowedgroups']);
				// See if groups user is in is allowed
				foreach($allowed_groups as $agid)
				{
					if(in_array($agid, $in_groups))
					{
						$is_allowed = true;
						break;
					}
				}
			}

			// Show theme if allowed, or if override is on
			if($is_allowed || $item['allowedgroups'] == "all" || $usergroup_override == true)
			{
				if($item['tid'] == $selected)
				{
					$item['selected'] = " checked";
				}

				if($item['pid'] != 0)
				{
					eval("\$themeselector .= \"".$templates->get("theme_preview_item")."\";");
					
				}

				if(array_key_exists($item['tid'], $tcaches))
				{
					theme_selector($name, $selected, $item['tid'], $depthit, $usergroup_override);
				}
			}
		}
	}

	if($tid == 1)
	{
		
	}

	return $themeselector;
}


/*
*	Checks to see usergroup permission and forum permission for HTML
*/
$plugins->add_hook("usercp_options_end", "theme_preview_display");
function theme_preview_display() 
{
	global $mybb, $themeselection, $user, $theme;
	
	if($mybb->settings['theme_preview_on'] == 1) {
		$themeselection = theme_selector("style", $user['style']);
	}
	else {
		$themeselection = build_theme_select("style", $user['style']);
	}
}

//Adds Author & Preview Fields to Admin Theme Editor
$plugins->add_hook("admin_style_themes_edit_commit", "theme_preview_editor");
function theme_preview_editor() {
	global $mybb, $db;
		$properties = array(
			"author" => $db->escape_string($mybb->input['author']),
			"preview" => $db->escape_string($mybb->input['preview'])
		);
	$db->update_query("themes", $properties, "tid='".intval($mybb->input['tid'])."'");

}
$plugins->add_hook('admin_formcontainer_end','theme_preview_form');
function theme_preview_form() {
	global $mybb, $form, $form_container, $lang, $theme;

	if($form_container->_title == $lang->edit_theme_properties && $mybb->input['action'] == "edit" && $mybb->settings['theme_preview_on'] == 1) {
		$form_container->output_row("Author", "", $form->generate_text_box('author', $theme['author'], array('id' => 'author')), 'author');
		$form_container->output_row("Preview Image", "", $form->generate_text_box('preview', $theme['preview'], array('id' => 'preview')), 'preview');
	}
}

?>