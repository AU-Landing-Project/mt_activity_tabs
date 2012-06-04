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
    // register filter tabs
  $context = elgg_get_context();
  
  if(elgg_is_logged_in() && ($context == 'activity' || $context == 'activity_tabs')){
    $user = elgg_get_logged_in_user_entity();
    $filter_context = get_input('filter_context', FALSE);
    $collections = get_user_access_collections($user->guid);
    $groups = $user->getGroups('', 0);
    
    // iterate through collections and add tabs as necessary
    foreach($collections as $collection){
      $name = $collection->name;
      
      // ignore group acls, they will be done in the groups section
      if(substr($name, 0, 7) == 'Group: ') {
        continue;  
      }
    
      $collectionid = "collection_" . $collection->id;
      $enable = elgg_get_plugin_user_setting($collectionid, $user->guid, 'mt_activity_tabs');
      $order = elgg_get_plugin_user_setting($collectionid . "_priority", $user->guid, 'mt_activity_tabs');
      $priority = 500;
      if(is_numeric($order)){
        $priority += $order;
      }
      
      if($enable == 'yes'){
        // we need to create a tab
        $tab = array(
            'name' => $collectionid,
            'text' => $name,
            'href' => "activity_tabs/collection/{$collection->id}/" . elgg_get_friendly_title($name),
            'selected' => ($filter_context == $collectionid),
            'priority' => $priority,
        );
            
        elgg_register_menu_item('filter', $tab);
      }
    }
    
    
    // iterate through groups and add tabs as necessary
    foreach($groups as $group){
      $name = $group->name;
    
      $groupid = "group_" . $group->guid;
      $enable = elgg_get_plugin_user_setting($groupid, $user->guid, 'mt_activity_tabs');
      $order = elgg_get_plugin_user_setting($groupid . "_priority", $user->guid, 'mt_activity_tabs');
      $priority = 500;
      if(is_numeric($order)){
        $priority += $order;
      }
      
      if($enable == 'yes'){
        // we need to create a tab
        $tab = array(
            'name' => $groupid,
            'text' => $name,
            'href' => "activity_tabs/group/{$group->guid}/" . elgg_get_friendly_title($name),
            'selected' => ($filter_context == $groupid),
            'priority' => $priority,
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
  
  $url = elgg_get_site_url() . "activity_tabs/user/{$user->username}";
	
	$item = new ElggMenuItem('activity_tabs_user_activity', elgg_echo('activity_tabs'), $url);
	$item->setSection('action');
  $item->setPriority(200);
  
  $returnvalue[] = $item;
  
  return $returnvalue;
}

elgg_register_event_handler('init', 'system', 'activity_tabs_init');
