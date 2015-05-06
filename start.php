<?php
// note that parts of the plugin (the main directory for instance) is preserved
// as mt_activity_tabs to maintain back compatibility when upgrading from 1.7

// plugin init
function activity_tabs_init(){
  // add our own css
  elgg_extend_view('css/elgg', 'activity_tabs/css');
  
  elgg_register_page_handler('activity_tabs', 'activity_tabs_pagehandler');
  elgg_register_event_handler('pagesetup', 'system', 'activity_tabs_pagesetup');
  
  // default menu items are registered with relative paths
  // need to change it due to different page handlers
  elgg_register_plugin_hook_handler('register', 'menu:filter', 'activity_tabs_filtermenu');
  
  // set user activity link on menu:user_hover dropdown
  $user_activity = elgg_get_plugin_setting('user_activity', 'mt_activity_tabs');
  
  if($user_activity != 'no'){
    elgg_register_plugin_hook_handler('register', 'menu:user_hover', 'activity_tabs_user_hover');
    elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'activity_tabs_user_hover');
  }
}


//
// page handler function
function activity_tabs_pagehandler($page){
  
  $filter_context = $page[0] . '_' . $page[1];
  
  // set guid
  if($page[0] == 'user'){
    $guid = get_user_by_username($page[1])->guid;
  } else {
    $guid = $page[1];
  }
  
  set_input('filter_context', $filter_context);
  set_input('activity_tab_guid', $guid);
  set_input('activity_tab_type', $page[0]);
  
  if(include('pages/mt_activity_tabs.php')){
    return TRUE;
  }
  
  return FALSE;
}

/**
 *
 * Lost to do during page setup
 * register filter tabs
 * register links on sidebar
 */
function activity_tabs_pagesetup($event, $object_type, $object){
  if (!elgg_is_logged_in()) {
		return;
	}

	if (!elgg_in_context('activity') && !elgg_in_context('activity_tabs')) {
		return;
	}

	$dbprefix = elgg_get_config('dbprefix');
	$priority = 500;

	$user = elgg_get_logged_in_user_entity();
	$filter_context = get_input('filter_context', FALSE);

	$all_settings = elgg_get_all_plugin_user_settings($user->guid, 'mt_activity_tabs');

	$tabs = array(
		'group' => array(),
		'collection' => array(),
	);

	if (!empty($all_settings)) {
		foreach ($all_settings as $name => $value) {
			list($type, $id, $opt) = explode('_', $name);
			if ($type !== 'group' && $type !== 'collection') {
				continue;
			}
			if (!$opt) {
				$opt = 'enabled';
			}
			$tabs[$type][$id][$opt] = $value;
		}
	}

	$collection_ids = array();
	foreach ($tabs['collection'] as $id => $opts) {
		$enabled = elgg_extract('enabled', $opts);
		if ($enabled == 'yes') {
			$collection_ids[] = (int) $id;
		}
	}

	$group_ids = array();
	foreach ($tabs['group'] as $id => $opts) {
		$enabled = elgg_extract('enabled', $opts);
		if ($enabled == 'yes') {
			$group_ids[] = (int) $id;
		}
	}

	if (!empty($collection_ids)) {
		$collection_ids_in = implode(',', $collection_ids);
		$query = "SELECT * FROM {$dbprefix}access_collections
			WHERE owner_guid = {$user->guid} AND id IN ($collection_ids_in) AND name NOT LIKE 'Group:%'";
		$collections = get_data($query);
	}

	if (!empty($collections)) {

		// iterate through collections and add tabs as necessary
		foreach ($collections as $collection) {
			// we need to create a tab
			$tab = array(
				'name' => "collection:$collection->id",
				'text' => $collection->name,
				'href' => "activity_tabs/collection/{$collection->id}/" . elgg_get_friendly_title($collection->name),
				'selected' => (int) $filter_context == (int) $collection->id,
				'priority' => $priority + (int) $tabs['collection']["$collection->id"]['priority'],
			);
			elgg_register_menu_item('filter', $tab);
		}
	}

	if (!empty($group_ids)) {
		$group_ids_in = implode(',', $group_ids);
		$query = "SELECT * FROM {$dbprefix}groups_entity ge
			JOIN {$dbprefix}entity_relationships er ON er.guid_two = ge.guid
			WHERE er.guid_one = {$user->guid} AND ge.guid IN ($group_ids_in)";
		$groups = get_data($query);
	}

	if (!empty($groups)) {
		foreach ($groups as $group) {
			$tab = array(
				'name' => "group:$group->guid",
				'text' => $group->name,
				'href' => "activity_tabs/group/{$group->guid}/" . elgg_get_friendly_title($group->name),
				'selected' => (int) $filter_context == (int) $group->guid,
				'priority' => $priority + (int) $tabs['group']["$group->guid"]['priority'],
			);
			elgg_register_menu_item('filter', $tab);
		}
	}

	// register menu item for configuring tabs
	$link = array(
		'name' => 'configure_activity_tabs',
		'text' => elgg_echo('activity_tabs:configure'),
		'href' => 'settings/plugins/' . $user->username,
	);

	elgg_register_menu_item('page', $link);
}


// fix filter menu hrefs on activity_tabs handler
function activity_tabs_filtermenu($hook, $type, $returnvalue, $params){
  if(elgg_get_context() == 'activity_tabs' && elgg_is_logged_in()){
    $check = $returnvalue;
    
    foreach($check as $key => $item){
      switch($item->getName()){
        case 'all':
          $url = elgg_get_site_url() . 'activity/all';
          $item->setHref($url);
          $returnvalue[$key] = $item;
          break;
        case 'mine':
          $url = elgg_get_site_url() . 'activity/owner/' . elgg_get_logged_in_user_entity()->username;
          $item->setHref($url);
          $returnvalue[$key] = $item;
          break;
        case 'friend':
          $url = elgg_get_site_url() . 'activity/friends/' . elgg_get_logged_in_user_entity()->username;
          $item->setHref($url);
          $returnvalue[$key] = $item;
          break;
        default:
        break;
      }
    }
    
    return $returnvalue;
  }
}


//
//
// add in the activity to the user hover menu
function activity_tabs_user_hover($hook, $type, $returnvalue, $params){
  $user = $params['entity'];
  
  if (elgg_instanceof($user, 'user')) {
    $url = elgg_get_site_url() . "activity_tabs/user/{$user->username}";
	
    $item = new ElggMenuItem('activity_tabs_user_activity', elgg_echo('activity_tabs'), $url);
    
    if ($type == 'menu:user_hover') {
      $item->setSection('action');
      $item->setLinkClass('activity-tabs-user-hover');
      $item->setPriority(200);
    }
    $returnvalue[] = $item;
  }
  
  return $returnvalue;
}

elgg_register_event_handler('init', 'system', 'activity_tabs_init');
