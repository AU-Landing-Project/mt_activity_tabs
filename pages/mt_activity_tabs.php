<?php
/**
 * Main activity stream list page
 */

$options = array();

$page_type = preg_replace('[\W]', '', get_input('activity_tab_type', 'all'));
$type = preg_replace('[\W]', '', get_input('type', 'all'));
$subtype = preg_replace('[\W]', '', get_input('subtype', ''));

$id = get_input('activity_tab_guid', FALSE);

//sanity
if(!is_numeric($id)){
  register_error(elgg_echo('activity_tabs:invalid:id'));
  forward('activity', 'activity_tabs_invalid_id');
}

if ($subtype) {
	$selector = "type=$type&subtype=$subtype";
} else {
	$selector = "type=$type";
}

if ($type != 'all') {
	$options['type'] = $type;
	if ($subtype) {
		$options['subtype'] = $subtype;
	}
}

switch ($page_type) {
	case 'collection':
		$title = elgg_echo('activity_tabs:collection');
		$page_filter = 'activity_tab';
    
    $members = get_members_of_access_collection($id,	TRUE);
    
		$options['subject_guids'] = $members;
		break;
	case 'group':
	default:
    $db_prefix = elgg_get_config('dbprefix');
		$title = elgg_echo('activity_tabs:group');
		$page_filter = 'activity_tab';
		$options['joins'] = array("JOIN {$db_prefix}entities e ON e.guid = rv.object_guid");
		$options['wheres'] = array("e.container_guid = $id");
		break;
}

$activity = elgg_list_river($options);
if (!$activity) {
	$activity = elgg_echo('river:none');
}

$content = elgg_view('core/river/filter', array('selector' => $selector));

$sidebar = elgg_view('core/river/sidebar');

$params = array(
	'content' =>  $content . $activity,
	'sidebar' => $sidebar,
	'filter_context' => $page_filter,
	'class' => 'elgg-river-layout',
);

$body = elgg_view_layout('content', $params);

echo elgg_view_page($title, $body);
