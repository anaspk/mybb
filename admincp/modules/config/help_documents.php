<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->help_documents, "index.php?".SID."&amp;module=config/help_documents");

// Add something
if($mybb->input['action'] == "add")
{
	// Add section
	if($mybb->input['type'] == "section")
	{
		// Do add?
		if($mybb->request_method == "post")
		{
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_section_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_section_missing_description;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_section_missing_enabled;
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = $lang->error_section_missing_translation;
			}
			
			if($mybb->input['enabled'] != 'yes')
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] != 'yes')
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$sid = $db->insert_query("helpsections", $sql_array);
				
				// Log admin action
				log_admin_action($sid, $mybb->input['name']);

				flash_message($lang->success_help_section_added, 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->add_section);
		$page->output_header($lang->help_documents." - ".$lang->add_new_section);
		
		
		$sub_tabs['add_help_section'] = array(
			'title'	=> $lang->add_new_section,
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=section",
			'description' => $lang->add_new_section_desc
		);
	
		$page->output_nav_tabs($sub_tabs, 'add_help_section');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpsections", "MAX(disporder) as maxdisp");
			$mybb->input['disporder'] = $db->fetch_field($query, "maxdisp")+1;
			$mybb->input['enabled'] = 1;
			$mybb->input['translation'] = 0;
		}
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=section", "post", "add");
		$form_container = new FormContainer($lang->add_new_section);
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->output_row($lang->use_translation." <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->add_section);
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	
	// Add page
	else
	{
		// Do add?
		if($mybb->request_method == "post")
		{
			if(empty($mybb->input['sid']))
			{
				$errors[] = $lang->error_missing_sid;
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_document_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_document_missing_description;
			}
			
			if(empty($mybb->input['document']))
			{
				$errors[] = $lang->error_document_missing_document;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_document_missing_enabled;
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = $lang->error_document_missing_translation;
			}
			
			if($mybb->input['enabled'] != 'yes')
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] != 'yes')
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"sid" => intval($mybb->input['sid']),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$hid = $db->insert_query("helpdocs", $sql_array);

				// Log admin action
				log_admin_action(array($hid, $mybb->input['name']));
				
				flash_message($lang->success_help_document_added, 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->add_document);
		$page->output_header($lang->help_documents." - ".$lang->add_new_document);
		
		
		$sub_tabs['add_help_document'] = array(
			'title'	=> $lang->add_new_document,
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=document",
			'description' => $lang->add_new_document_desc
		);
	
		$page->output_nav_tabs($sub_tabs, 'add_help_document');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			// Select the largest existing display order
			$query = $db->simple_select("helpdocs", "MAX(disporder) as maxdisp");
			$mybb->input['disporder'] = $db->fetch_field($query, "maxdisp")+1;
			$mybb->input['enabled'] = 1;
			$mybb->input['translation'] = 0;
		}
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=document", "post", "add");
		$form_container = new FormContainer($lang->add_new_document);
		$query = $db->simple_select("helpsections", "sid, name");
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row($lang->section." <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid']), 'sid');
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->document." <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->output_row($lang->use_translation." <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->add_document);
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}

	$page->output_footer();
}

// Edit something
if($mybb->input['action'] == "edit")
{
	// Edit a section
	if($mybb->input['sid'] && !$mybb->input['hid'])
	{
		// Do edit?
		if($mybb->request_method == "post")
		{
			$sid = intval($mybb->input['sid']);
			
			if(empty($sid))
			{
				$errors[] = $lang->error_invalid_sid;
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_section_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_section_missing_description;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_section_missing_enabled;
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = $lang->error_section_missing_translation;
			}
			
			if($mybb->input['enabled'] != 'yes')
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] != 'yes')
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->update_query("helpsections", $sql_array, "sid = '{$sid}'");

				// Log admin action
				log_admin_action(array($sid, $mybb->input['name']));
				
				flash_message($lang->success_help_section_updated, 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->edit_section);
		$page->output_header($lang->help_documents." - ".$lang->edit_section);
		
		
		$sub_tabs['edit_help_section'] = array(
			'title'	=> $lang->edit_section,
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;sid=".intval($mybb->input['sid']),
			'description' => $lang->edit_section_desc
		);
	
		$page->output_nav_tabs($sub_tabs, 'edit_help_section');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpsections", "*", "sid = '".intval($mybb->input['sid'])."'");
			$section = $db->fetch_array($query);
			$mybb->input['name'] = $section['name'];
			$mybb->input['description'] = $section['description'];
			$mybb->input['disporder'] = $section['disporder'];
			$mybb->input['enabled'] = $section['enabled'];
			$mybb->input['translation'] = $section['usetranslation'];
		}
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=edit", "post", "edit");
		
		echo $form->generate_hidden_field("sid", $section['sid']);
		
		$form_container = new FormContainer($lang->edit_section." ({$lang->id} {$section['sid']})");
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->output_row($lang->use_translation." <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->edit_section);
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	}
	
	// Edit document
	else
	{
		// Do edit?
		if($mybb->request_method == "post")
		{
			$hid = intval($mybb->input['hid']);
			
			if(empty($hid))
			{
				$errors[] = $lang->error_invalid_sid;
			}
			
			if(empty($mybb->input['name']))
			{
				$errors[] = $lang->error_document_missing_name;
			}
			
			if(empty($mybb->input['description']))
			{
				$errors[] = $lang->error_document_missing_description;
			}
			
			if(empty($mybb->input['document']))
			{
				$errors[] = $lang->error_document_missing_document;
			}
			
			if(!isset($mybb->input['enabled']))
			{
				$errors[] = $lang->error_document_missing_enabled;
			}
			
			if(!isset($mybb->input['translation']))
			{
				$errors[] = $lang->error_document_missing_translation;
			}
			
			if($mybb->input['enabled'] != 'yes')
			{
				$mybb->input['enabled'] = "no";
			}
			
			if($mybb->input['translation'] != 'yes')
			{
				$mybb->input['translation'] = "no";
			}
			
			if(!is_array($errors))
			{
				$sql_array = array(
					"sid" => intval($mybb->input['sid']),
					"name" => $db->escape_string($mybb->input['name']),
					"description" => $db->escape_string($mybb->input['description']),
					"document" => $db->escape_string($mybb->input['document']),
					"usetranslation" => $db->escape_string($mybb->input['translation']),
					"enabled" => $db->escape_string($mybb->input['enabled']),
					"disporder" => intval($mybb->input['disporder'])
				);
				
				$db->update_query("helpdocs", $sql_array, "hid = '{$hid}'");
				
				// Log admin action
				log_admin_action(array($hid, $mybb->input['name']));

				flash_message($lang->success_help_document_updated, 'success');
				admin_redirect('index.php?'.SID.'&module=config/help_documents');
			}
		}
	
		$page->add_breadcrumb_item($lang->edit_document);
		$page->output_header($lang->help_documents." - ".$lang->edit_document);
		
		
		$sub_tabs['edit_help_document'] = array(
			'title'	=> $lang->edit_document,
			'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;hid=".intval($mybb->input['hid']),
			'description' => $lang->edit_document_desc
		);
	
		$page->output_nav_tabs($sub_tabs, 'edit_help_document');
	
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$query = $db->simple_select("helpdocs", "*", "hid = '".intval($mybb->input['hid'])."'");
			$doc = $db->fetch_array($query);
			$mybb->input['sid'] = $doc['sid'];
			$mybb->input['name'] = $doc['name'];
			$mybb->input['description'] = $doc['description'];
			$mybb->input['document'] = $doc['document'];
			$mybb->input['disporder'] = $doc['disporder'];
			$mybb->input['enabled'] = $doc['enabled'];
			$mybb->input['translation'] = $doc['usetranslation'];
		}
	
		$form = new Form("index.php?".SID."&amp;module=config/help_documents&amp;action=edit", "post", "edit");
		
		echo $form->generate_hidden_field("hid", $doc['hid']);
				
		$form_container = new FormContainer($lang->edit_document." ({$lang->id} {$doc['hid']})");
		
		$query = $db->simple_select("helpsections", "sid, name");
		while($section = $db->fetch_array($query))
		{
			$sections[$section['sid']] = $section['name'];
		}
		$form_container->output_row($lang->section." <em>*</em>", "", $form->generate_select_box("sid", $sections, $mybb->input['sid']), 'sid');
		$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row($lang->short_description." <em>*</em>", "", $form->generate_text_box('description', $mybb->input['description'], array('id' => 'description')), 'description');
		$form_container->output_row($lang->document." <em>*</em>", "", $form->generate_text_area('document', $mybb->input['document'], array('id' => 'document')), 'document');
		$form_container->output_row($lang->display_order, "", $form->generate_text_box('disporder', $mybb->input['disporder'], array('id' => 'disporder')), 'disporder');
		$form_container->output_row($lang->enabled." <em>*</em>", "", $form->generate_yes_no_radio('enabled', $mybb->input['enabled']));
		$form_container->output_row($lang->use_translation." <em>*</em>", "", $form->generate_yes_no_radio('translation', $mybb->input['translation']));
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button($lang->edit_document);
		
		$form->output_submit_wrapper($buttons);
		$form->end();
	}

	$page->output_footer();
}

// Delete something
if($mybb->input['action'] == "delete")
{
	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?".SID."&module=config/help_documents");
	}

	// Do delete something?
	if($mybb->request_method == "post")
	{
		// Delete section
		if(isset($mybb->input['sid']))
		{
			$sid = intval($mybb->input['sid']);
			
			$query = $db->simple_select("helpsections", "*", "sid='{$sid}'");
			$section = $db->fetch_array($query);
	
			// Invalid section?
			if(!$section['sid'])
			{
				flash_message($lang->error_missing_section_id, 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}
			
			// Default section?
			if($sid <= 2)
			{
				flash_message($lang->error_cannot_delete_section, 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}
			
			// Delete section and its documents
			$db->delete_query("helpsections", "sid = '{$sid}'", 1);
			$db->delete_query("helpdocs", "sid = '{$sid}'");

			// Log admin action
			log_admin_action(array($section['name']));

			flash_message($lang->success_section_deleted, 'success');
			admin_redirect("index.php?".SID."&module=config/help_documents");
		}
		
		// Delete document
		else
		{
			$hid = intval($mybb->input['hid']);
			
			$query = $db->simple_select("helpdocs", "*", "hid='{$hid}'");
			$doc = $db->fetch_array($query);
	
			// Invalid document?
			if(!$doc['hid'])
			{
				flash_message($lang->error_missing_hid, 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}			
			
			// Default document?
			if($hid <= 7)
			{
				flash_message($lang->error_cannot_delete_document, 'error');
				admin_redirect("index.php?".SID."&module=config/help_documents");
			}
			
			$db->delete_query("helpdocs", "hid = '{$hid}'", 1);

			// Log admin action
			log_admin_action(array($doc['name']));
			
			flash_message($lang->success_document_deleted, 'success');
			admin_redirect("index.php?".SID."&module=config/help_documents");
		}
	}
	// Show form for deletion
	else
	{
		// Section
		if(isset($mybb->input['sid']))
		{
			$sid = intval($mybb->input['sid']);
			$page->output_confirm_action("index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;sid={$sid}", $lang->confirm_section_deletion);
		}
		// Document
		else
		{
			$hid = intval($mybb->input['hid']);
			$page->output_confirm_action("index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;hid={$hid}", $lang->confirm_document_deletion);
		}
	}
}

// List document and sections
if(!$mybb->input['action'])
{
	$page->output_header($lang->help_documents);

	$sub_tabs['manage_help_documents'] = array(
		'title'	=> $lang->manage_help_documents,
		'link'	=> "index.php?".SID."&amp;module=config/help_documents",
		'description'=> $lang->manage_help_documents_desc
	);

	$sub_tabs['add_help_document'] = array(
		'title'	=> $lang->add_new_document,
		'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=document"
	);
	
	$sub_tabs['add_help_section'] = array(
		'title'	=> $lang->add_new_section,
		'link'	=> "index.php?".SID."&amp;module=config/help_documents&amp;action=add&amp;type=section"
	);

	$page->output_nav_tabs($sub_tabs, 'manage_help_documents');

	$table = new Table;
	$table->construct_header("Section / Document");
	$table->construct_header("Controls", array('class' => "align_center", 'colspan' => 2, "width" => "150"));

	$query = $db->simple_select("helpsections", "*", "", array('order_by' => "disporder"));
	while($section = $db->fetch_array($query))
	{
		// Icon to differentiate section type
		if($section['sid'] > 2)
		{
			$icon = "<img src=\"styles/default/images/icons/custom.gif\" title=\"{$lang->custom_doc_sec}\" alt=\"{$lang->custom_doc_sec}\" style=\"vertical-align: middle;\" />";
		}
		else
		{
			$icon = "<img src=\"styles/default/images/icons/default.gif\" title=\"{$lang->default_doc_sec}\" alt=\"{$lang->default_doc_sec}\" style=\"vertical-align: middle;\" />";
		}
		$table->construct_cell("<div class=\"float_right\">{$icon}</div><div><strong>{$section['name']}</strong><br /><small>{$section['description']}</small></div>");
 
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;sid={$section['sid']}\">{$lang->edit}</a>", array("class" => "align_center", "width" => '60'));
		
		// Show delete only if not a default section
		if($section['sid'] > 2)
		{
			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;sid={$section['sid']}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_document_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => '90'));
		}
		else
		{
			$table->construct_cell("&nbsp;", array("width" => '90'));
		}
		$table->construct_row();
			
		$query2 = $db->simple_select("helpdocs", "*", "sid='{$section['sid']}'", array('order_by' => "disporder"));
		while($doc = $db->fetch_array($query2))
		{
			// Icon to differentiate document type
			if($doc['hid'] > 7)
			{
				$icon = "<img src=\"styles/default/images/icons/custom.gif\" title=\"{$lang->custom_doc_sec}\" alt=\"{$lang->custom_doc_sec}\" style=\"vertical-align: middle;\" />";
			}
			else
			{
				$icon = "<img src=\"styles/default/images/icons/default.gif\" title=\"{$lang->default_doc_sec}\" alt=\"{$lang->default_doc_sec}\" style=\"vertical-align: middle;\" />";
			}
			$table->construct_cell("<div style=\"padding-left: 40px;\"><div class=\"float_right\">{$icon}</div><div><strong>{$doc['name']}</strong><br /><small>{$doc['description']}</small></div></div>");

			$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=edit&amp;hid={$doc['hid']}\">{$lang->edit}</a>", array("class" => "align_center", "width" => '60'));
			
			// Only show delete if not a default document
			if($doc['hid'] > 7)
			{
				$table->construct_cell("<a href=\"index.php?".SID."&amp;module=config/help_documents&amp;action=delete&amp;hid={$doc['hid']}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_section_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => '90'));
			}
			else
			{
				$table->construct_cell("&nbsp;", array("width" => '90'));
			}
			$table->construct_row();
		}
	}
	
	// No documents message
	if(count($table->rows) == 0)
	{
		$table->construct_cell($lang->no_help_documents, array('colspan' => 3));
		$table->construct_row();
	}

	$table->output($lang->help_documents);
	
	echo <<<LEGEND
	<fieldset>
<legend>{$lang->legend}</legend>
<img src="styles/default/images/icons/custom.gif" alt="{$lang->custom_doc_sec}" style="vertical-align: middle;" /> {$lang->custom_doc_sec}<br />
<img src="styles/default/images/icons/default.gif" alt="{$lang->default_doc_sec}" style="vertical-align: middle;" /> {$lang->default_doc_sec}
</fieldset>
LEGEND;

	$page->output_footer();
}
?>