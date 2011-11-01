<?php

  // Etherpad: include class
  global $CONFIG;
  $eclient = $CONFIG->pluginspath . "etherpad/classes/etherpad-lite-client.php";
  include $eclient;
  
  // Etherpad: Create an instance
  $apikey = elgg_get_plugin_setting('etherpad_key', 'etherpad');
  $apiurl = elgg_get_plugin_setting('etherpad_host', 'etherpad') . "/api";
  $instance = new EtherpadLiteClient($apikey,$apiurl);
  
  //Etherpad: Create a group for logged in user
  try { 
   $mappedGroup = $instance->createGroupIfNotExistsFor(get_loggedin_user()->username);
   $groupID = $mappedGroup->groupID;
  } catch (Exception $e) {echo $e.getMessage();}

  //Etherpad: Create an author(etherpad user) for logged in user
  try {
    $author = $instance->createAuthorIfNotExistsFor(get_loggedin_user()->username);
    $authorID = $author->authorID;
  } catch (Exception $e) {
    echo "\n\ncreateAuthorIfNotExistsFor Failed with message ". $e->getMessage();
  }
  //Etherpad: Create session
  $validUntil = mktime(0, 0, 0, date("m"), date("d")+1, date("y")); // One day in the future
  $sessionID = $instance->createSession($groupID, $authorID, $validUntil);
  $sessionID = $sessionID->sessionID;
  setcookie("sessionID",$sessionID); // Set a cookie 
  
  //generate pad name
  function genRandomString() { // A funtion to generate a random name
        $length = 10;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $string = '';
        for ($p = 0; $p < $length; $p++) {
          $string .= $characters[mt_rand(0, strlen($characters))];
        }
        return $string;
   }
   $name = genRandomString();
   $padID = $groupID . "$" . $name;
  //Create new pad
	//TODO : Access control, private pads. 
  try {
	$instance->createGroupPad($groupID,$name, elgg_get_plugin_setting('new_pad_text', 'etherpad'));
	$instance->setPublicStatus($padID,"false");
  } catch (Exception $e) {
  	echo "\n\ncreatePad Failed with message ". $e->getMessage();
  }
  // get the form input
  $title = get_input('title');
  $padurl = elgg_get_plugin_setting('etherpad_host', 'etherpad') . "/p/". $padID;
  $body = get_input('description');
  $tags = string_to_tag_array(get_input('tags'));
 
  // create a new etherpad object
  $etherpad = new ElggObject();
  $etherpad->subtype = "etherpad";
  $etherpad->title = $title;
  $etherpad->description = $body;
  $etherpad->paddress = $padurl;
  $etherpad->access_id = ACCESS_PUBLIC;
  $etherpad->pname = $padID;
  // owner is logged in user
  $etherpad->owner_guid = elgg_get_logged_in_user_guid();
  // save tags as metadata
  $etherpad->tags = $tags;
  $guid = get_input('guid');

  // save to database
  if ($etherpad->save()) {
	//add to river only if new
	if ($guid == 0) {
		add_to_river('river/object/etherpad/create','create', elgg_get_logged_in_user_guid(), $etherpad->getGUID());
		$etherpad->container_guid = (int)get_input('container_guid', elgg_get_logged_in_user_guid());
	}
	system_message(elgg_echo('etherpad:save:success')); 
  } else {
	if (!$etherpad->canEdit()) {
		system_message(elgg_echo('etherpad:save:failed'));
		forward(REFERRER);
	}	
}
  // forward user to a page that displays the post
  $instance->deleteSession($sessionID);
  forward($etherpad->getURL());
?>