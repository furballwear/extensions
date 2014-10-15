<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial2.6
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * class for jomhelper
 *
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial2.6
 * @since       1.0
 */

class jomHelper
{
	private $date_now;
	private $IJUserID;
	private $mainframe;
	private $db;
	private $my;
	private $config;

	/**
	 * constructor
	 */
	function __construct()
	{
		$this->date_now  = JFactory::getDate();
		$this->mainframe = JFactory::getApplication();
		$this->db        = JFactory::getDBO(); // set database object
		$this->IJUserID  = $this->mainframe->getUserState('com_ijoomeradv.IJUserID', 0); //get login user id
		$this->my        = CFactory::getUser($this->IJUserID); // set the login user object
		$this->config    = CFactory::getConfig();
	}

	/**
	 * function for get name
	 *
	 * @param   object  $obj  object
	 *
	 * @return  $name some value
	 */
	function getName($obj)
	{
		if (method_exists($obj, 'getDisplayName'))
		{
			$name = $obj->getDisplayName();
		}
		else
		{

			$name = ($this->config->get('displayname') == 'username') ? $obj->username : $obj->name;
		}

		return $name;
	}

	/**
	 * function check's is connected or not
	 *
	 * @param   integer  $id1  id1
	 * @param   integer  $id2  id2
	 *
	 * @return  mixed  jomHelper data object on success, false on failure.
	 */
	function isconnected($id1, $id2)
	{
		if (($id1 == $id2) && ($id1 != 0))
			return true;

		if ($id1 == 0 || $id2 == 0)
			return false;

		$query = "SELECT count(*)
				FROM #__community_connection
				WHERE `connect_from`='{$id1}'
				AND `connect_to`='{$id2}'
				AND `status` = 1";
		$this->db->setQuery($query);
		$result = $this->db->loadResult();

		return $result;
	}

	/**
	 * function for check isMember or not
	 *
	 * @param   integer  $id1  id1
	 *
	 * @return  boolean  true on success and false on failure
	 */
	function isMember($id1 = 0)
	{
		if ($id1 == 0)
			return false;

		$query = "SELECT count(*)
				FROM #__community_users
				WHERE `userid`='{$id1}'";
		$this->db->setQuery($query);
		$result = $this->db->loadResult();

		return $result;
	}
	/**
	 * function for get jomsocial version
	 *
	 * @return  $version
	 */
	function getjomsocialversion()
	{
		$parser  = JFactory::getXMLParser('Simple');
		$xml    = JPATH_ROOT . '/administrator/components/com_community/community.xml';
		$parser->loadFile($xml);
		$doc      = $parser->document;
		$element  = $doc->getElementByPath('version');

		return $version = $element->data();
	}

	/**
	 * function for get Notification Parameters
	 *
	 * @param   integer  $userid  userid
	 *
	 * @return  boolean  jomHelper data object on success, false on failure.
	 */
	function getNotificationParams($userid = 0)
	{
		if ($userid == 0)
		{
			$user   = JFactory::getUser();
			$userid = $user->id;
		}

		$query = "SELECT *
				FROM #__ijoomeradv_users
				WHERE `userid`='{$userid}'";
		$this->db->setQuery($query);
		$row = $this->db->loadObject();

		$result = array();
		if (!isset($row->jomsocial_params) || $row->jomsocial_params == "")
		{
			$result['pushFriendOnline']  = 1;
			$result['pushInboxMessage']  = 1;
			$result['pushFriendRequest'] = 1;
		}
		else
		{
			$array = explode("\n", $row->jomsocial_params);

			foreach ($array as $r)
			{
				$var = explode("=", $r);
				if (count($var) > 1)
					$result[$var[0]] = (int) $var[1];
			}
		}

		return $result;
	}

	/**
	 * function GetLatLong
	 *
	 * @param  string  $addrss   address
	 * @param  string  $city     city
	 * @param  string  $state    state
	 * @param  string  $country  country
	 *
	 * @return $l
	 */
	function GetLatLong($addrss = '', $city = '', $state = '', $country = '')
	{
		$q_array = array();
		$address = urlencode($addrss);

		if (trim($address) != '')
			$q_array[] = $address;
		if (trim($city) != '')
			$q_array[] = $city;
		if (trim($state) != '')
			$q_array[] = $state;
		if (trim($country) != '')
			$q_array[] = $country;

		$q     = implode("+", $q_array);
		$myKey = GOOGLEAPI;

		$url = "http://maps.googleapis.com/maps/api/geocode/json?address={$q}&sensor=true";

		$response = file_get_contents(str_replace(' ', '%20', $url));
		$l        = ",";
		if (!empty($response))
		{
			$arr = json_decode($response);
			if (strtolower($arr->status) == 'ok')
			{
				$l = $arr->results[0]->geometry->location->lng . ',' . $arr->results[0]->geometry->location->lat;
			}
		}

		return $l;
	}

	/**
	 * function for googleAuthenticate
	 *
	 * @param   string  $username  username
	 * @param   mixed  $password  password
	 * @param   [type]  $service   service
	 *
	 * @return array/boolean  true on success or false on failure
	 */
	function googleAuthenticate($username, $password, $service)
	{
		// get an authorization token
		$ch = curl_init();
		if (!$ch)
		{
			return false;
		}

		curl_setopt($ch, CURLOPT_URL, "https://www.google.com/accounts/ClientLogin");
		$post_fields = array("Email" => $username, "Passwd" => $password, "accountType" => "GOOGLE", "service" => $service);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$response = curl_exec($ch);

		curl_close($ch);
		if (strpos($response, '200 OK') === false)
		{
			return false;
		}

		// find the auth code
		preg_match("/(Auth=)([\w|-]+)/", $response, $matches);

		if (!$matches[2])
		{
			return false;
		}

		return $matches[2];
	}

	/**
	 * function for send Message To Android
	 *
	 * @param   integer  $authCode              authentication code
	 * @param   integer  $deviceRegistrationId  deviceRegistrationId
	 * @param   string  $msgType               message Type
	 * @param   string  $messageText           messageText
	 * @param   integer  $totMsg                totalMsg
	 * @param   [type]  $whentype              whentype
	 *
	 * @return  boolean true on success and false on failure
	 */
	function sendMessageToAndroid($authCode, $deviceRegistrationId, $msgType, $messageText, $totMsg = '', $whentype)
	{
		if (!empty($authCode) && !empty($deviceRegistrationId))
		{
			$headers = array('Authorization: GoogleLogin auth=' . $authCode);
			$data    = array(
				'registration_id' => $deviceRegistrationId,
				'collapse_key'    => $msgType,
				'data.type'       => $whentype,
				'data.totalcount' => $totMsg,
				'data.badge'      => 1,
				'data.message'    => $messageText //TODO Add more params with just simple data instead
			);
			$ch      = curl_init();

			curl_setopt($ch, CURLOPT_URL, 'https://android.apis.google.com/c2dm/send');
			if ($headers)
			{
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			$response = curl_exec($ch);
			curl_close($ch);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * send push notification code start here
	 *
	 * @param   [type]   $device_token  device token
	 * @param   string   $message       message
	 * @param   integer  $badge         badge
	 * @param   string   $type          type
	 *
	 * @return  void
	 */
	function send_push_notification($device_token, $message = '', $badge = 1, $type = '')
	{
		$server = 'ssl://gateway.push.apple.com:2195';
		if (PUSH_SERVER == '1')
			$server = 'ssl://gateway.sandbox.push.apple.com:2195';
		$keyCertFilePath = JPATH_SITE . '/components/com_ijoomeradv/certificates/certificates.pem';

		$sound = 'default';
		// Construct the notification payload
		$badge       = (int) $badge;
		$body        = array();
		$body['aps'] = array('alert' => $message);
		if ($badge)
			$body['aps']['badge'] = $badge;
		if ($sound)
			$body['aps']['sound'] = $sound;
		if ($type != '')
			$body['aps']['type'] = $type;

		/* End of Configurable Items */
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $keyCertFilePath);

		// assume the private key passphase was removed.
		//stream_context_set_option($ctx, 'ssl', 'passphrase', $pass);

		$fp = stream_socket_client($server, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
		// for production change the server to ssl://gateway.push.apple.com:219

		if (!$fp)
		{
			//print "Failed to connect $err $errstr\n";
			return;
		}
		//
		//$payload = '{"aps": {"badge": 1, "alert": "Hello from iJoomer!", "sound": "cow","type":"online"}}';//json_encode($body);
		$payload = json_encode($body);

		$msg = chr(0) . pack("n", 32) . pack('H*', str_replace(' ', '', $device_token)) . pack("n", strlen($payload)) . $payload;
		fwrite($fp, $msg);
		fclose($fp);
	}

	/**
	 * function updateLatLong
	 *
	 * @param   integer  $uid   uid
	 * @param   integer  $lat   lat
	 * @param   integer  $long  long
	 *
	 * @return  boolean true on success and false on failure
	 */
	function updateLatLong($uid = 0, $lat = 255, $long = 255)
	{
		$db  = JFactory::getDBO();
		if ($uid == 0)
			return false;

		$query = "UPDATE #__community_users
				SET `latitude`='{$lat}', `longitude`='{$long}'
				WHERE `userid`='{$uid}'";
		$this->db->setQuery($query);
		$this->db->Query();
	}

	/**
	 * get location from lat, long.
	 *
	 * @param   [type]  $lattitude  lattitude
	 * @param   [type]  $longitude  longitude
	 *
	 * @return  array  address
	 */
	function getaddress($lattitude, $longitude)
	{
		$address = '';
		if ($lattitude != '' && $longitude != '')
		{
			CFactory::load('helpers', 'remote');
			$url     = 'http://maps.google.com/maps/api/geocode/json?latlng=' . urlencode($lattitude . "," . $longitude) . '&sensor=false';
			$content = CRemoteHelper::getContent($url);
			$status  = null;
			if (!empty($content))
			{
				require_once JPATH_SITE . '/plugins/system/azrul.system/pc_includes/JSON.php';
				$json = new Services_JSON;
				$data = $json->decode($content);

				if ($data->status == 'OK')
				{
					$address = $data->results[0]->formatted_address;
				}
			}
		}

		return $address;
	}

	/**
	 * get title from location.
	 *
	 * @param   string  $location  location
	 *
	 * @return  null or value
	 */
	function gettitle($location)
	{
		if ($location != '')
		{
			CFactory::load('helpers', 'remote');
			$url     = 'http://maps.google.com/maps/api/geocode/json?address=' . urlencode($location) . '&sensor=false';
			$content = CRemoteHelper::getContent($url);

			$status = null;
			if (!empty($content))
			{
				require_once JPATH_SITE . '/plugins/system/azrul.system/pc_includes/JSON.php';
				$json = new Services_JSON;
				$data = $json->decode($content);
				if ($data->status == 'OK')
				{
					$address = $data->results[0]->address_components;
					foreach ($address as $adKe => $adVal)
					{
						if ($adVal->types[0] == 'route' || $adVal->types[0] == 'neighborhood' || $adVal->types[0] == 'sublocality' || $adVal->types[0] == 'locality' || $adVal->types[0] == 'administrative_area_level_1')
						{
							$locality[] = $adVal->long_name;
						}
						if ($adVal->types[0] == 'country')
						{
							$locality1 = $adVal->long_name;
						}
					}
					$title   = $locality;
					$title[] = $locality1;
					if (count($title))
					{
						$add = implode(', ', $title);

						return addslashes($add);
					}
					else
					{
						return '';
					}
				}
				else
				{
					return '';
				}
			}
			else
			{
				return '';
			}
		}
		else
		{
			return '';
		}
	}

	/**
	 * function timeLapse
	 *
	 * @param   date  $date  date
	 *
	 * @return  lapse
	 */
	function timeLapse($date)
	{
		jimport('joomla.utilities.date');
		require_once JPATH_ROOT . '/components/com_community/helpers/string.php';
		$now      = new JDate;
		$dateDiff = CTimeHelper::timeDifference($date->toUnix(), $now->toUnix());

		if ($dateDiff['days'] > 0)
		{
			$lapse = JText::sprintf((CStringHelper::isPlural($dateDiff['days'])) ? 'COM_COMMUNITY_LAPSED_DAY_MANY' : 'COM_COMMUNITY_LAPSED_DAY', $dateDiff['days']);
		}
		elseif ($dateDiff['hours'] > 0)
		{
			$lapse = JText::sprintf((CStringHelper::isPlural($dateDiff['hours'])) ? 'COM_COMMUNITY_LAPSED_HOUR_MANY' : 'COM_COMMUNITY_LAPSED_HOUR', $dateDiff['hours']);
		}
		elseif ($dateDiff['minutes'] > 0)
		{
			$lapse = JText::sprintf((CStringHelper::isPlural($dateDiff['minutes'])) ? 'COM_COMMUNITY_LAPSED_MINUTE_MANY' : 'COM_COMMUNITY_LAPSED_MINUTE', $dateDiff['minutes']);
		}
		else
		{
			if ($dateDiff['seconds'] == 0)
			{
				$lapse = JText::_('COM_COMMUNITY_ACTIVITIES_MOMENT_AGO');
			}
			else
			{
				$lapse = JText::sprintf((CStringHelper::isPlural($dateDiff['seconds'])) ? 'COM_COMMUNITY_LAPSED_SECOND_MANY' : 'COM_COMMUNITY_LAPSED_SECOND', $dateDiff['seconds']);
			}
		}

		return $lapse;
	}

	/**
	 * function for get Date
	 *
	 * @param   string   $str  string
	 * @param   integer  $off  off
	 *
	 * @return  date $date
	 */
	function getDate($str = '', $off = 0)
	{
		require_once JPATH_ROOT . '/components/com_community/libraries/core.php';

		$extraOffset = $this->config->get('daylightsavingoffset');
		//convert to utc time first.
		$utc_date = new CDate($str);
		$date     = new CDate($utc_date->toUnix() + $off * 3600);

		$my   = JFactory::getUser();
		$cMy = CFactory::getUser();

		//J1.6 returns timezone as string, not integer offset.
		if (method_exists('JDate', 'getOffsetFromGMT'))
		{
			$systemOffset = new CDate('now', $this->mainframe->getCfg('offset'));
			$systemOffset = $systemOffset->getOffsetFromGMT(true);
		}
		else
		{
			$systemOffset = $this->mainframe->getCfg('offset');
		}

		if (!$my->id)
		{
			$date->setOffset($systemOffset + $extraOffset);
		}
		else
		{
			if (!empty($my->params))
			{
				$pos = JString::strpos($my->params, 'timezone');

				$offset = $systemOffset + $extraOffset;
				if ($pos === false)
				{
					$offset = $systemOffset + $extraOffset;
				}
				else
				{
					$offset = $my->getParam('timezone', -100);

					$myParams = $cMy->getParams();
					$myDTS    = $myParams->get('daylightsavingoffset');
					$cOffset  = (!empty($myDTS)) ? $myDTS : $this->config->get('daylightsavingoffset');

					if ($offset == -100)
						$offset = $systemOffset + $extraOffset;
					else
						$offset = $offset + $cOffset;
				}
				$date->setOffset($offset);
			}
			else
				$date->setOffset($systemOffset + $extraOffset);
		}

		return $date;
	}

	/**
	 * function for show a date
	 *
	 * @param   [type]  $time    time
	 * @param   string  $mode    mode
	 * @param   string  $tz      tz
	 * @param   integer  $offset offset
	 *
	 * @return  date  $date
	 */
	function showDate($time, $mode = 'datetime_today', $tz = 'kunena', $offset = null)
	{
		require_once JPATH_SITE . '/components/com_kunena/lib/kunena.timeformat.class.php';

		$date = JFactory::getDate($time);

		if ($offset === null || strtolower($tz) != 'utc')
		{
			$offset = JFactory::getUser()->getParam('timezone', $this->mainframe->getCfg('offset', 0));
		}
		if (is_numeric($offset))
		{
			$date->setOffset($offset);
		}
		else
		{
			// Joomla 1.6 support
			$offset = new DateTimeZone($offset);
			$date->setTimezone($offset);
		}
		if ($date->toFormat('%Y') < 1902) return JText::_('COM_KUNENA_DT_DATETIME_UNKNOWN');

		$modearr = explode('_', $mode);

		switch (strtolower($modearr [0]))
		{
			case 'none' :
				return '';
			case 'time' :
				$usertime_format  = JText::_('COM_KUNENA_DT_TIME_FMT');
				$today_format     = JText::_('COM_KUNENA_DT_TIME_FMT');
				$yesterday_format = JText::_('COM_KUNENA_DT_TIME_FMT');
				break;
			case 'date' :
				$usertime_format  = JText::_('COM_KUNENA_DT_DATE_FMT');
				$today_format     = JText::_('COM_KUNENA_DT_DATE_TODAY_FMT');
				$yesterday_format = JText::_('COM_KUNENA_DT_DATE_YESTERDAY_FMT');
				break;
			case 'ago' :
				return CKunenaTimeformat::showTimeSince($date->toUnix());
				break;
			case 'datetime':
				$usertime_format  = JText::_('COM_KUNENA_DT_DATETIME_FMT');
				$today_format     = JText::_('COM_KUNENA_DT_DATETIME_TODAY_FMT');
				$yesterday_format = JText::_('COM_KUNENA_DT_DATETIME_YESTERDAY_FMT');
				break;
			default:
				$usertime_format  = $mode;
				$today_format     = $mode;
				$yesterday_format = $mode;

		}

		// Today and Yesterday?
		if ($modearr [count($modearr) - 1] == 'today')
		{
			$now  = JFactory::getDate('now');
			$now  = @getdate($now->toUnix());
			$then = @getdate($date->toUnix());

			// Same day of the year, same year.... Today!
			if ($then ['yday'] == $now ['yday'] &&
				$then ['year'] == $now ['year']
			)
				$usertime_format = $today_format;

			// Day-of-year is one less and same year, or it's the first of the year and that's the last of the year...
			if (($then ['yday'] == $now ['yday'] - 1 && $then ['year'] == $now ['year']) ||
				($now ['yday'] == 0 && $then ['year'] == $now ['year'] - 1) && $then ['mon'] == 12 && $then ['mday'] == 31
			)
				$usertime_format = $yesterday_format;
		}

		return $date->toFormat($usertime_format, true);
	}

	/**
	 * get the notification count for logged in user
	 *
	 * @return  array  jsonarray
	 */
	function getNotificationCount()
	{
		CFactory::load('libraries', 'toolbar');
		$toolbar    = CToolbarLibrary::getInstance();
		$notifModel = CFactory::getModel('notification');

		$newMessageCount      = $toolbar->getTotalNotifications('inbox');
		$newEventInviteCount  = $toolbar->getTotalNotifications('events');
		$newFriendInviteCount = $toolbar->getTotalNotifications('friends');
		$newGroupInviteCount  = $toolbar->getTotalNotifications('groups');

		$my                                               = CFactory::getUser($this->IJUserID);
		$myParams                                          = $my->getParams();
		$newNotificationCount                             = $notifModel->getNotificationCount($my->id, '0', $myParams->get('lastnotificationlist', ''));
		$jsonarray['notification']['messageNotification'] = intval($newMessageCount);
		$jsonarray['notification']['friendNotification']  = intval($newFriendInviteCount);
		$jsonarray['notification']['globalNotification']  = intval($newEventInviteCount + $newGroupInviteCount + $newNotificationCount);

		return $jsonarray;
	}

	/**
	 * Like an item. Update ajax count
	 *
	 * @param string $element Can either be core object (photo/album/videos/profile/profile.status) or a plugins (plugins,plugin_name)
	 * @param mixed  $itemId  Unique id to identify object item
	 *
	 * @filesource com_community/controllers/system.php
	 * @method ajaxLike
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	function Like($element, $itemId)
	{
		$filter  = JFilterInput::getInstance();
		$element = $filter->clean($element, 'string');
		$itemId  = $filter->clean($itemId, 'int');

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(704); // if user is not logged in or not registered one.
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Load libraries
		CFactory::load('libraries', 'like');
		$like = new CLike;

		if ($element == 'groups.discussion' || $element == 'groups.discussion.reply' || $element == 'photos.album' || $element == 'albums' || $element == 'photos.wall.create')
		{
			$act  = JTable::getInstance('Activity', 'CTable');
			$act->load($itemId);
			$itemId = $act->like_id;
		}
		else
		{
			if (!$like->enabled($element))
			{
				IJReq::setResponse(500); // if element on which like applied is not enabled/bloked to like.
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}

		$like->addLike($element, $itemId); // add like

		// Send push notification params
		if ($element == 'profile')
		{
			$userid = $itemId;
		}
		else
		{
			$act  = JTable::getInstance('Activity', 'CTable');
			$act->load($itemId);
			$userid = $act->actor;
		}

		//===========================================================
		//Send push notification
		$sendpushflag = false;
		switch ($element)
		{
			case 'photo':
				$photo  = JTable::getInstance('Photo', 'CTable');
				$photo->load($itemId);
				if ($photo->id)
				{
					CFactory::load('helpers', 'group');
					$album = JTable::getInstance('Album', 'CTable');
					$album->load($photo->albumid);
					$pushcontentdata['albumdetail']['id']            = $album->id;
					$pushcontentdata['albumdetail']['deleteAllowed'] = intval(($photo->creator == $album->creator or COwnerHelper::isCommunityAdmin($photo->creator)));
					if ($photo->creator == $album->creator)
					{
						$uid = 0;
					}
					else
					{
						$uid = $album->creator;
					}
					$pushcontentdata['albumdetail']['user_id'] = $uid;
					$pushcontentdata['photodetail']['id']      = $photo->id;
					$pushcontentdata['photodetail']['caption'] = $photo->caption;

					$p_url = JURI::base();
					if ($photo->storage == 's3')
					{
						$s3BucketPath = $this->config->get('storages3bucket');
						if (!empty ($s3BucketPath))
							$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
					}
					else
					{
						if (!file_exists(JPATH_SITE . '/' . $photo->image))
							$photo->image = $photo->original;
					}
					$pushcontentdata['photodetail']['thumb'] = $p_url . $photo->thumbnail;
					$pushcontentdata['photodetail']['url']   = $p_url . $photo->image;
					if (SHARE_PHOTOS == 1)
					{
						$pushcontentdata['photodetail']['shareLink'] = JURI::base() . "index.php?option=com_community&view=photos&task=photo&userid={$userId}&albumid={$albumID}#photoid={$photo->id}";
					}

					//likes
					$likes                                      = $this->getLikes('photo', $photo->id, $this->IJUserID);
					$pushcontentdata['photodetail']['likes']    = $likes->likes;
					$pushcontentdata['photodetail']['dislikes'] = $likes->dislikes;
					$pushcontentdata['photodetail']['liked']    = $likes->liked;
					$pushcontentdata['photodetail']['disliked'] = $likes->disliked;

					//comments
					$count                                          = $this->getCommentCount($photo->id, 'photos');
					$pushcontentdata['photodetail']['commentCount'] = $count;

					$query = "SELECT count(id)
							FROM #__community_photos_tag
							WHERE `photoid`={$photo->id}";
					$this->db->setQuery($query);
					$count                                  = $this->db->loadResult();
					$pushcontentdata['photodetail']['tags'] = $count;
					$pushcontentdata['type']                = 'photos';

					$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
						FROM #__ijoomeradv_users
						WHERE `userid`={$photo->creator}";
					$this->db->setQuery($query);
					$puser    = $this->db->loadObject();
					$ijparams = new CParameter($puser->jomsocial_params);
					if ($ijparams->get('pushnotif_photos_like') == 1 && $photo->creator != $this->IJUserID && !empty($puser))
					{
						$sendpushflag = true;
						$usr          = $this->getUserDetail($this->IJUserID);
						$search       = array('{actor}', '{photo}');
						$replace      = array($usr->name, JText::_('COM_COMMUNITY_SINGULAR_PHOTO'));
						$message      = str_replace($search, $replace, JText::_('COM_COMMUNITY_PHOTO_LIKE_EMAIL_SUBJECT'));
					}
					$configText = 'pushnotif_photos_like';
					$toid       = $photo->creator;
				}
				break;
			case 'album':
				break;
			case 'videos':
				$video  = JTable::getInstance('Video', 'CTable');
				$video->load($itemId);
				if ($video->id)
				{
					$video_file = $video->path;
					$p_url      = JURI::root();
					if ($video->type == 'file')
					{
						$ext = JFile::getExt($video->path);

						if ($ext == 'mov' && file_exists(JPATH_SITE . '/' . $video->path))
						{
							$video_file = JURI::root() . $video->path;
						}
						else
						{
							$lastpos = strrpos($video->path, '.');

							$vname = substr($video->path, 0, $lastpos);

							if ($video->storage == 's3')
							{
								$s3BucketPath = $this->config->get('storages3bucket');
								if (!empty ($s3BucketPath))
									$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
							}
							$video_file = $p_url . $vname . ".mp4";
						}
					}

					$pushcontentdata['id']          = $video->id;
					$pushcontentdata['caption']     = $video->title;
					$pushcontentdata['thumb']       = ($video->thumb) ? $p_url . $video->thumb : JURI::base() . 'components/com_community/assets/video_thumb.png';
					$pushcontentdata['url']         = $video_file;
					$pushcontentdata['description'] = $video->description;
					$pushcontentdata['date']        = $this->timeLapse($this->getDate($video->created));
					$pushcontentdata['location']    = $video->location;
					$pushcontentdata['permissions'] = $video->permissions;
					$pushcontentdata['categoryId']  = $video->category_id;

					$usr                             = $this->getUserDetail($video->creator);
					$pushcontentdata['user_id']      = 0;
					$pushcontentdata['user_name']    = $usr->name;
					$pushcontentdata['user_avatar']  = $usr->avatar;
					$pushcontentdata['user_profile'] = $usr->profile;

					//likes
					$likes                       = $this->getLikes('videos', $video->id, $this->IJUserID);
					$pushcontentdata['likes']    = $likes->likes;
					$pushcontentdata['dislikes'] = $likes->dislikes;
					$pushcontentdata['liked']    = $likes->liked;
					$pushcontentdata['disliked'] = $likes->disliked;

					//comments
					$count                            = $this->getCommentCount($video->id, 'videos');
					$pushcontentdata['commentCount']  = $count;
					$pushcontentdata['deleteAllowed'] = intval(($video->creator or COwnerHelper::isCommunityAdmin($video->creator)));
					if (SHARE_VIDEOS)
					{
						$pushcontentdata['shareLink'] = JURI::base() . "index.php?option=com_community&view=videos&task=video&userid={$video->creator}&videoid={$video->id}";
					}
					$pushcontentdata['type'] = 'videos';

					$query = "SELECT count(id)
						FROM #__community_videos_tag
						WHERE `videoid`={$video->id}";
					$this->db->setQuery($query);
					$count                   = $this->db->loadResult();
					$pushcontentdata['tags'] = $count;

					$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
						FROM #__ijoomeradv_users
						WHERE `userid`={$video->creator}";
					$this->db->setQuery($query);
					$puser    = $this->db->loadObject();
					$ijparams = new CParameter($puser->jomsocial_params);

					if ($ijparams->get('pushnotif_videos_like') == 1 && $video->creator != $this->IJUserID && !empty($puser))
					{
						$sendpushflag = true;

						$usr     = $this->getUserDetail($this->IJUserID);
						$search  = array('{actor}', '{video}');
						$replace = array($usr->name, $video->title);
						$message = str_replace($search, $replace, JText::_('COM_COMMUNITY_VIDEO_LIKE_EMAIL_SUBJECT'));
					}
					$configText = 'pushnotif_videos_like';
					$toid       = $video->creator;
				}
				break;
			case 'profile':
				$profile = CFactory::getUser($itemId);
				if ($profile->id)
				{
					$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
						FROM #__ijoomeradv_users
						WHERE `userid`={$profile->id}";
					$this->db->setQuery($query);
					$puser    = $this->db->loadObject();
					$ijparams = new CParameter($puser->jomsocial_params);
					if ($ijparams->get('pushnotif_profile_like') == 1 && $profile->id != $this->IJUserID && !empty($puser))
					{
						$sendpushflag = true;

						$usr     = $this->getUserDetail($this->IJUserID);
						$message = str_replace('{actor}', $usr->name, JText::_('COM_COMMUNITY_PROFILE_LIKE_EMAIL_SUBJECT'));
					}
					$pushcontentdata['id'] = $this->IJUserID;
					$configText            = 'pushnotif_profile_like';
					$toid                  = $profile->id;
				}
				break;
			case 'profile.status':
				$stream  = JTable::getInstance('Activity', 'CTable');
				$stream->load($itemId);

				if ($stream->id)
				{
					$profile = CFactory::getUser($stream->actor);
					$query   = "SELECT `jomsocial_params`,`device_token`,`device_type`
							FROM #__ijoomeradv_users
							WHERE `userid`={$profile->id}";
					$this->db->setQuery($query);
					$puser    = $this->db->loadObject();
					$ijparams = new CParameter($puser->jomsocial_params);

					if ($ijparams->get('pushnotif_profile_stream_like') == 1 && $profile->id != $this->IJUserID && !empty($puser))
					{
						$sendpushflag = true;

						$usr     = $this->getUserDetail($this->IJUserID);
						$search  = array('{actor}', '{stream}');
						$replace = array($usr->name, JText::_('COM_COMMUNITY_SINGULAR_STREAM'));
						$message = str_replace($search, $replace, JText::_('COM_COMMUNITY_PROFILE_STREAM_LIKE_EMAIL_SUBJECT'));

						//$pushcontentdata['id'] = $this->IJUserID;
						CFactory::load('libraries', 'activities');
						$actModel = CFactory::getModel('Activities');
						$html     = $actModel->getActivities('', '', null, 1, true, null, false, $itemId);
						$html     = $html[0];

						$titletag              = isset($html->title) ? $html->title : "";
						$likeAllowed           = intval($html->allowLike());
						$commentAllowed        = intval($html->allowComment());
						$cadmin                = COwnerHelper::isCommunityAdmin($this->IJUserID);
						$pushcontentdata['id'] = $html->id;

						// add user detail
						$usr                                            = $this->getUserDetail($html->actor);
						$pushcontentdata['user_detail']['user_id']      = $usr->id;
						$pushcontentdata['user_detail']['user_name']    = $usr->name;
						$pushcontentdata['user_detail']['user_avatar']  = $usr->avatar;
						$pushcontentdata['user_detail']['user_profile'] = $usr->profile;

						// add content data
						$pushcontentdata['content'] = strip_tags($html->content);
						//add video detail
						if ($html->app == 'videos')
						{
							$pushcontentdata['content_data'] = $videotag;
						}

						$pushcontentdata['date']           = $html->created;
						$pushcontentdata['likeAllowed']    = intval($html->allowLike());
						$pushcontentdata['likeCount']      = intval($html->getLikeCount());
						$pushcontentdata['liked']          = ($html->userLiked == 1) ? 1 : 0;
						$pushcontentdata['commentAllowed'] = intval($html->allowComment());
						$pushcontentdata['commentCount']   = intval($html->getCommentCount());

						$query = "SELECT comment_type,like_type
								FROM #__community_activities
								WHERE id={$html->id}";
						$this->db->setQuery($query);
						$extra = $this->db->loadObject();

						$pushcontentdata['liketype']    = $extra->like_type;
						$pushcontentdata['commenttype'] = $extra->comment_type;

						switch ($html->app)
						{
							case 'friends':
								$pushcontentdata['type'] = 'friends';

								$srch                        = array("&#9658;", "&quot;");
								$rplc                        = array("►", "\"");
								$pushcontentdata['titletag'] = str_replace($srch, $rplc, strip_tags($titletag));

								$usrtar                                          = $this->jomHelper->getUserDetail($html->target);
								$pushcontentdata['content_data']['user_id']      = $usrtar->id;
								$pushcontentdata['content_data']['user_name']    = $usrtar->name;
								$pushcontentdata['content_data']['user_avatar']  = $usrtar->avatar;
								$pushcontentdata['content_data']['user_profile'] = $usrtar->profile;
								$pushcontentdata['deleteAllowed']                = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								break;

							case 'videos':
								$pushcontentdata['type'] = 'videos';

								$content_id = $this->getActivityContentID($html->id);
								$video       = JTable::getInstance('Video', 'CTable');
								$video->load($content_id);
								if ($video->id)
								{
									if ($video->storage == 's3')
									{
										$s3BucketPath = $this->config->get('storages3bucket');
										if (!empty ($s3BucketPath))
											$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
									}
									else
									{
										$p_url = JURI::base();
									}

									if ($video->type == 'file')
									{
										$ext = JFile::getExt($video->path);

										if ($ext == 'mov' && file_exists(JPATH_SITE . '/' . $video->path))
										{
											$video_file = JURI::root() . $video->path;
										}
										else
										{
											$lastpos    = strrpos($video->path, '.');
											$vname      = substr($video->path, 0, $lastpos);
											$video_file = $p_url . $vname . ".mp4";
										}
									}
									else
									{
										$video_file = $video->path;
									}

									$pushcontentdata['content_data']['id']          = $video->id;
									$pushcontentdata['content_data']['caption']     = $video->title;
									$pushcontentdata['content_data']['thumb']       = ($video->thumb) ? $p_url . $video->thumb : JURI::base() . 'components/com_community/assets/video_thumb.png';
									$pushcontentdata['content_data']['url']         = $video_file;
									$pushcontentdata['content_data']['description'] = $video->description;
									$pushcontentdata['content_data']['date']        = $this->jomHelper->timeLapse($this->jomHelper->getDate($video->created));
									$pushcontentdata['content_data']['location']    = $video->location;
									$pushcontentdata['content_data']['permissions'] = $video->permissions;
									$pushcontentdata['content_data']['categoryId']  = $video->category_id;

									if ($type == 'group')
									{
										$pushcontentdata['liked'] = ($html->userLiked >= 0) ? 0 : 1;
									}

									//likes
									$likes                                       = $this->jomHelper->getLikes('videos', $video->id, $this->IJUserID);
									$pushcontentdata['content_data']['likes']    = $likes->likes;
									$pushcontentdata['content_data']['dislikes'] = $likes->dislikes;
									$pushcontentdata['content_data']['liked']    = $likes->liked;
									$pushcontentdata['content_data']['disliked'] = $likes->disliked;

									//comments
									$count                                            = $this->jomHelper->getCommentCount($video->id, 'videos');
									$pushcontentdata['content_data']['commentCount']  = $count;
									$pushcontentdata['content_data']['deleteAllowed'] = intval(($this->IJUserID == $video->creator or COwnerHelper::isCommunityAdmin($this->IJUserID)));

									if (SHARE_VIDEOS)
									{
										$pushcontentdata['content_data']['shareLink'] = JURI::base() . "index.php?option=com_community&view=videos&task=video&userid={$video->creator}&videoid={$video->id}";
									}

									$query = "SELECT count(id)
											FROM #__community_videos_tag
											WHERE `videoid`={$video->id}";
									$this->db->setQuery($query);
									$pushcontentdata['content_data']['tags'] = $this->db->loadResult();

									if ($video->groupid)
									{
										$this->getGroupData($video->groupid, $pushcontentdata['group_data']);

										$srch                        = array("&#9658;", "&quot;", "► " . $usr->name);
										$rplc                        = array("►", "\"", "► " . $pushcontentdata['group_data']['title']);
										$pushcontentdata['titletag'] = str_replace($srch, $rplc, strip_tags($titletag));
									}
									else
									{
										$srch                        = array("&#9658;", "&quot;");
										$rplc                        = array("►", "\"");
										$pushcontentdata['titletag'] = str_replace($srch, $rplc, strip_tags($titletag));
									}
									$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								}
								else
								{
									unset($pushcontentdata);
									$inc--;
								}
								break;

							case 'photos':
								$pushcontentdata['type'] = 'photos';
								$content_id              = $this->getActivityContentID($html->id);
								$album                    = JTable::getInstance('Album', 'CTable');
								$album->load($content_id);
								if ($album->id)
								{
									$photoModel = CFactory::getModel('photos');
									$photo      = $photoModel->getPhoto($album->photoid);

									$pushcontentdata['content_data']['id']          = $album->id;
									$pushcontentdata['content_data']['name']        = $album->name;
									$pushcontentdata['content_data']['description'] = $album->description;
									$pushcontentdata['content_data']['permission']  = $album->permissions;
									$pushcontentdata['content_data']['thumb']       = JURI::base() . $photo->thumbnail;
									$pushcontentdata['content_data']['date']        = $this->jomHelper->timeLapse($this->jomHelper->getDate($album->lastupdated));

									$pushcontentdata['content_data']['count']    = $photoModel->getTotalPhotos($album->id);
									$pushcontentdata['content_data']['location'] = $album->location;

									if ($type == 'group')
									{
										$pushcontentdata['liked'] = ($html->userLiked >= 0) ? 0 : 1;
									}

									//likes
									$likes                                       = $this->jomHelper->getLikes('album', $album->id, $this->IJUserID);
									$pushcontentdata['content_data']['likes']    = $likes->likes;
									$pushcontentdata['content_data']['dislikes'] = $likes->dislikes;
									$pushcontentdata['content_data']['liked']    = $likes->liked;
									$pushcontentdata['content_data']['disliked'] = $likes->disliked;

									//comments
									$count                                           = $this->jomHelper->getCommentCount($album->id, 'albums');
									$pushcontentdata['content_data']['commentCount'] = $count;
									$pushcontentdata['content_data']['shareLink']    = JURI::base() . "index.php?option=com_community&view=photos&task=album&albumid={$value->id}&userid={$value->creator}";

									$str = preg_match_all('|(#\w+=)(\d+)+|', $html->content, $match);
									if ($str)
									{
										foreach ($match[2] as $key => $value)
										{
											$photo = $photoModel->getPhoto($value);
											$p_url = JURI::base();
											if ($photo->storage == 's3')
											{
												$s3BucketPath = $this->config->get('storages3bucket');
												if (!empty ($s3BucketPath))
													$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
											}
											else
											{
												if (!file_exists(JPATH_SITE . '/' . $photo->image))
													$photo->image = $photo->original;
											}
											$pushcontentdata['image_data'][$key]['id']      = $photo->id;
											$pushcontentdata['image_data'][$key]['caption'] = $photo->caption;
											$pushcontentdata['image_data'][$key]['thumb']   = $p_url . $photo->thumbnail;
											$pushcontentdata['image_data'][$key]['url']     = $p_url . $photo->image;
											if (SHARE_PHOTOS == 1)
											{
												$pushcontentdata['image_data'][$key]['shareLink'] = JURI::base() . "index.php?option=com_community&view=photos&task=photo&userid={$photo->creator}&albumid={$photo->albumid}#photoid={$photo->id}";
											}

											//likes
											$likes                                           = $this->jomHelper->getLikes('photo', $photo->id, $this->IJUserID);
											$pushcontentdata['image_data'][$key]['likes']    = $likes->likes;
											$pushcontentdata['image_data'][$key]['dislikes'] = $likes->dislikes;
											$pushcontentdata['image_data'][$key]['liked']    = $likes->liked;
											$pushcontentdata['image_data'][$key]['disliked'] = $likes->disliked;

											//comments
											$count                                               = $this->jomHelper->getCommentCount($photo->id, 'photos');
											$pushcontentdata['image_data'][$key]['commentCount'] = $count;

											$query = "SELECT count(id)
													FROM #__community_photos_tag
													WHERE `photoid`={$photo->id}";
											$this->db->setQuery($query);
											$count                                       = $this->db->loadResult();
											$pushcontentdata['image_data'][$key]['tags'] = $count;
										}
									}

									if ($album->groupid)
									{
										$groupModel                                       = CFactory::getModel('groups');
										$isAdmin                                          = $groupModel->isAdmin($this->IJUserID, $album->groupid);
										$pushcontentdata['content_data']['editAlbum']     = intval($isAdmin);
										$pushcontentdata['content_data']['deleteAllowed'] = intval(($this->IJUserID == $album->creator OR COwnerHelper::isCommunityAdmin($this->IJUserID) OR $isAdmin));
										CFactory::load('helpers', 'group');
										$albums            = $photoModel->getGroupAlbums($album->groupid);
										$allowManagePhotos = CGroupHelper::allowManagePhoto($album->groupid);

										if ($allowManagePhotos && $this->config->get('groupphotos') && $this->config->get('enablephotos'))
										{
											$pushcontentdata['content_data']['uploadPhoto'] = ($albums) ? 1 : 0;
										}
										else
										{
											$pushcontentdata['content_data']['uploadPhoto'] = 0;
										}

										$this->getGroupData($album->groupid, $pushcontentdata['group_data']);
										$srch                        = array("&#9658;", "&quot;", $usr->name);
										$rplc                        = array("►", "\"", $usr->name . " ► " . $pushcontentdata['group_data']['title']);
										$pushcontentdata['titletag'] = str_replace($srch, $rplc, strip_tags($titletag));
									}
									else
									{
										$pushcontentdata['content_data']['deleteAllowed'] = intval(($this->IJUserID == $album->creator or COwnerHelper::isCommunityAdmin($this->IJUserID)));
										$pushcontentdata['content_data']['editAlbum']     = intval($this->IJUserID == $album->creator);
										$srch                                             = array("&#9658;", "&quot;");
										$rplc                                             = array("►", "\"");
										$pushcontentdata['titletag']                      = str_replace($srch, $rplc, strip_tags($titletag));
									}
									$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								}
								else
								{
									unset($pushcontentdata);
									$inc--;
								}
								break;

							case 'groups':
								$srch                        = array("&#9658;", "&quot;");
								$rplc                        = array("►", "\"");
								$pushcontentdata['titletag'] = str_replace($srch, $rplc, strip_tags($titletag));
								$content_id                  = $this->getActivityContentID($html->id);
								$pushcontentdata['type']     = 'group';
								$this->getGroupData($content_id, $pushcontentdata['content_data']);
								$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								break;

							case 'groups.bulletin':
								$pushcontentdata['type'] = 'announcement';
								$content_id              = $this->getActivityContentID($html->id);

								$bulletin  = JTable::getInstance('Bulletin', 'CTable');
								$bulletin->load($content_id);
								if ($bulletin->id)
								{
									$pushcontentdata['content_data']['id']             = $bulletin->id;
									$pushcontentdata['content_data']['title']          = $bulletin->title;
									$pushcontentdata['content_data']['message']        = strip_tags($bulletin->message);
									$usr                                               = $this->jomHelper->getUserDetail($bulletin->created_by);
									$pushcontentdata['content_data']['user_id']        = $usr->id;
									$pushcontentdata['content_data']['user_name']      = $usr->name;
									$pushcontentdata['content_data']['user_avatar']    = $usr->avatar;
									$pushcontentdata['content_data']['user_profile']   = $usr->profile;
									$format                                            = "%A, %d %B %Y";
									$pushcontentdata['content_data']['date']           = CTimeHelper::getFormattedTime($bulletin->date, $format);
									$params                                            = new CParameter($bulletin->params);
									$pushcontentdata['content_data']['filePermission'] = $params->get('filepermission-member');
									if (SHARE_GROUP_BULLETIN == 1)
									{
										$pushcontentdata['content_data']['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewbulletin&groupid={$result->groupid}&bulletinid={$result->id}";
									}
									if ($type == 'group')
									{
										$pushcontentdata['liked'] = ($html->userLiked >= 0) ? 0 : 1;
									}
									$query = "SELECT count(id)
											FROM #__community_files
											WHERE `groupid`={$bulletin->groupid}
											AND `bulletinid`={$bulletin->id}";
									$this->db->setQuery($query);
									$pushcontentdata['content_data']['files'] = $this->db->loadResult();

									// group data.
									$this->getGroupData($bulletin->groupid, $pushcontentdata['group_data']);
									$srch                             = array("&#9658;", "&quot;");
									$rplc                             = array("►", "\"");
									$pushcontentdata['titletag']      = $usr->name . " ► " . $pushcontentdata['group_data']['title'] . "\n" . str_replace($srch, $rplc, strip_tags($titletag));
									$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								}
								else
								{
									unset($pushcontentdata);
									$inc--;
								}
								break;

							case 'groups.discussion.reply':
							case 'groups.discussion':
								$content_id = $this->getActivityContentID($html->id);

								$discussion  = JTable::getInstance('Discussion', 'CTable');
								$discussion->load($content_id);

								if ($discussion->id)
								{
									$pushcontentdata['type']                         = 'discussion';
									$pushcontentdata['content_data']['id']           = $discussion->id;
									$pushcontentdata['content_data']['title']        = $discussion->title;
									$pushcontentdata['content_data']['message']      = strip_tags($discussion->message);
									$usr                                             = $this->jomHelper->getUserDetail($discussion->creator);
									$pushcontentdata['content_data']['user_id']      = $usr->id;
									$pushcontentdata['content_data']['user_name']    = $usr->name;
									$pushcontentdata['content_data']['user_avatar']  = $usr->avatar;
									$pushcontentdata['content_data']['user_profile'] = $usr->profile;

									$format                                      = "%A, %d %B %Y";
									$pushcontentdata['content_data']['date']     = CTimeHelper::getFormattedTime($discussion->lastreplied, $format);
									$pushcontentdata['content_data']['isLocked'] = $discussion->lock;

									if ($type == 'group')
									{
										$pushcontentdata['liked'] = ($html->userLiked >= 0) ? 0 : 1;
									}

									$wallModel                                          = CFactory::getModel('wall');
									$wallContents                                      = $wallModel->getPost('discussions', $discussion->id, 9999999, 0);
									$pushcontentdata['content_data']['topics']         = count($wallContents);
									$params                                            = new CParameter($discussion->params);
									$pushcontentdata['content_data']['filePermission'] = $params->get('filepermission-member');
									if (SHARE_GROUP_DISCUSSION == 1)
									{
										$pushcontentdata['content_data']['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewdiscussion&groupid={$discussion->groupid}2&topicid={$group->id}";
									}
									$query = "SELECT count(id)
											FROM #__community_files
											WHERE `groupid`={$discussion->groupid}
											AND `discussionid`={$discussion->id}";
									$this->db->setQuery($query);
									$pushcontentdata['content_data']['files'] = $this->db->loadResult();

									// group data.
									$this->getGroupData($discussion->groupid, $pushcontentdata['group_data']);
									$srch                             = array("&#9658;", "&quot;");
									$rplc                             = array("►", "\"");
									$pushcontentdata['titletag']      = $usr->name . " ► " . $pushcontentdata['group_data']['title'] . "\n" . str_replace($srch, $rplc, strip_tags($titletag));
									$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								}
								else
								{
									unset($pushcontentdata);
									$inc--;
								}
								break;

							case 'groups.wall':
								$pushcontentdata['type']           = 'groups.wall';
								$srch                              = array("&#9658;", "&quot;");
								$rplc                              = array("►", "\"");
								$pushcontentdata['titletag']       = str_replace($srch, $rplc, strip_tags($titletag));
								$pushcontentdata['id']             = $html->id;
								$pushcontentdata['date']           = $html->created;
								$pushcontentdata['likeAllowed']    = $likeAllowed;
								$pushcontentdata['commentAllowed'] = $commentAllowed;
								$pushcontentdata['likeCount']      = intval($html->likeCount);
								$pushcontentdata['commentCount']   = intval($html->commentCount);
								if ($type == 'group')
								{
									$pushcontentdata['liked'] = ($html->userLiked >= 0) ? 0 : 1;
								}
								else
								{
									$pushcontentdata['liked'] = ($html->userLiked == 1) ? 1 : 0;
								}
								$group  = JTable::getInstance('Group', 'CTable');
								$group->load($html->groupid);
								$pushcontentdata['deleteAllowed'] = intval($this->IJUserID == $html->actor OR COwnerHelper::isCommunityAdmin($this->IJUserID) OR $group->isAdmin($this->IJUserID));
								$pushcontentdata['liketype']      = 'groups.wall';
								$pushcontentdata['commenttype']   = 'groups.wall';

								// event data
								$this->getGroupData($group->id, $pushcontentdata['group_data']);
								$pushcontentdata['titletag']      = $usr->name . " ► " . $pushcontentdata['group_data']['title'] . "\n" . str_replace("&#9658;", "►", str_replace("&quot;", "\"", (strip_tags($titletag))));
								$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id, $group));
								break;

							case 'events':
								$srch                              = array("&#9658;", "&quot;");
								$rplc                              = array("►", "\"");
								$pushcontentdata['titletag']       = str_replace($srch, $rplc, strip_tags($titletag));
								$pushcontentdata['likeAllowed']    = 0;
								$pushcontentdata['commentAllowed'] = 0;
								$pushcontentdata['content']        = '';
								$pushcontentdata['type']           = 'event';
								$content_id                        = $this->getActivityContentID($html->id);

								// event data
								$this->getEventData($content_id, $pushcontentdata['content_data']);
								$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								break;

							case 'events.wall':
								$pushcontentdata['type']           = 'events.wall';
								$pushcontentdata['id']             = $html->id;
								$pushcontentdata['titletag']       = str_replace("&#9658;", "►", str_replace("&quot;", "\"", (strip_tags($titletag))));
								$pushcontentdata['date']           = $html->created;
								$pushcontentdata['likeAllowed']    = $likeAllowed;
								$pushcontentdata['commentAllowed'] = $commentAllowed;
								$pushcontentdata['likeCount']      = intval($html->likeCount);
								$pushcontentdata['commentCount']   = intval($html->commentCount);
								if ($type == 'event')
								{
									$pushcontentdata['liked'] = ($html->userLiked >= 0) ? 0 : 1;
								}
								else
								{
									$pushcontentdata['liked'] = ($html->userLiked == 1) ? 1 : 0;
								}
								$event  = JTable::getInstance('Event', 'CTable');
								$event->load($html->eventid);
								$pushcontentdata['deleteAllowed'] = intval($this->IJUserID == $html->actor OR COwnerHelper::isCommunityAdmin($this->IJUserID) OR $event->isAdmin($this->IJUserID));
								$pushcontentdata['liketype']      = 'events.wall';
								$pushcontentdata['commenttype']   = 'events.wall';

								// event data
								$this->getEventData($event->id, $pushcontentdata['event_data']);
								$srch                             = array("&#9658;", "&quot;");
								$rplc                             = array("►", "\"");
								$pushcontentdata['titletag']      = $usr->name . " ► " . $pushcontentdata['event_data']['title'] . "\n" . str_replace($srch, $rplc, strip_tags($titletag));
								$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id, $event));
								break;

							case 'profile':
								$pushcontentdata['type']          = 'profile';
								$pushcontentdata['deleteAllowed'] = intval($this->my->authorise('community.delete', 'activities.' . $html->id));
								$pushcontentdata['titletag']      = str_replace($srch, $rplc, strip_tags($titletag));
								break;

							default:
								$srch                        = array("&#9658;", "&quot;");
								$rplc                        = array("►", "\"");
								$pushcontentdata['titletag'] = str_replace($srch, $rplc, strip_tags($titletag));
								$pushcontentdata['type']     = '';
								break;
						}
					}
					$configText = 'pushnotif_profile_stream_like';
					$toid       = $profile->id;
				}
				break;
		}

		if ($sendpushflag)
		{
			//change for id based push notification
			$pushOptions['detail']['content_data'] = $pushcontentdata;
			$pushOptions                           = gzcompress(json_encode($pushOptions));

			$obj          = new stdClass;
			$obj->id      = null;
			$obj->detail  = $pushOptions;
			$obj->tocount = 1;
			$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');
			if ($obj->id)
			{
				$this->jsonarray['pushNotificationData']['id']      = $obj->id;
				$this->jsonarray['pushNotificationData']['to']      = $toid;
				$this->jsonarray['pushNotificationData']['message'] = $message;
				$viewType                                           = json_decode(JRequest::getVar('reqObject', ''));
				$viewType                                           = $viewType->extView;
				if ($viewType == 'wall')
				{
					$lType = 'walllike';
				}
				else
				{
					$lType = ($element == 'photo') ? 'photos' : $element;
				}
				$this->jsonarray['pushNotificationData']['type']       = $lType;
				$this->jsonarray['pushNotificationData']['configtype'] = $configText;
			}
		}

		return $this->jsonarray;
		//return true;
	}

	/**
	 * Dislike an item
	 *
	 * @param string $element Can either be core object (photo/album/videos/profile/profile.status) or a plugins (plugins,plugin_name)
	 * @param mixed  $itemId  Unique id to identify object item
	 *
	 * @filesource com_community/controllers/system.php
	 * @method ajaxDislike
	 * @return boolean  true on success or false on failure
	 */
	function Dislike($element, $itemId)
	{
		$filter  = JFilterInput::getInstance();
		$itemId  = $filter->clean($itemId, 'int');
		$element = $filter->clean($element, 'string');

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(704); // if user is not logged in or not registered one.
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Load libraries
		CFactory::load('libraries', 'like');
		$dislike = new CLike;

		if ($element == 'groups.discussion' || $element == 'groups.discussion.reply' || $element == 'photos.album')
		{
			$act  = JTable::getInstance('Activity', 'CTable');
			$act->load($itemId);
			$itemId = $act->like_id;
		}
		else
		{
			if (!$dislike->enabled($element))
			{
				IJReq::setResponse(500); // if element on which like applied is not enabled/bloked to like.
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}

		$dislike->addDislike($element, $itemId);

		return true;
	}

	/**
	 * Unlike an item
	 *
	 * @param string $element Can either be core object (photos/videos) or a plugins (plugins,plugin_name)
	 * @param mixed  $itemId  Unique id to identify object item
	 *
	 * @filesource com_community/controllers/system.php
	 * @method ajaxDislike
	 *
	 * @return boolean  true on success or false on failure
	 */
	function Unlike($element, $itemId)
	{
		$filter  = JFilterInput::getInstance();
		$itemId  = $filter->clean($itemId, 'int');
		$element = $filter->clean($element, 'string');

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(704); // if user is not logged in or not registered one.
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Load libraries
		CFactory::load('libraries', 'like');
		$unlike = new CLike;

		if ($element == 'groups.discussion' || $element == 'groups.discussion.reply' || $element == 'photos.album' || $element == 'albums' || $element == 'photos.wall.create')
		{
			$act  = JTable::getInstance('Activity', 'CTable');
			$act->load($itemId);
			$itemId = $act->like_id;
		}
		else
		{
			if (!$unlike->enabled($element))
			{
				IJReq::setResponse(500); // if element on which like applied is not enabled/bloked to like.
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}

		$unlike->unlike($element, $itemId);

		return true;
	}


	/**
	 * get like details
	 *
	 * @param string $element Can either be core object (photo/album/videos/profile/profile.status) or a plugins (plugins,plugin_name)
	 * @param mixed  $itemId  Unique id to identify object item
	 * @param mixed  $userId
	 *
	 * @return  mixed    jomHelper data object on success, false on failure.
	 */
	function getLikes($element, $itemId, $userId)
	{
		require_once JPATH_SITE . '/components/com_community/tables/like.php';
		$like  = JTable::getInstance('Like', 'CTable');
		$like->loadInfo($element, $itemId);
		CFactory::load('libraries', 'like');
		$likes                   = new CLike;
		$result->userLiked       = $likes->userLiked($element, $itemId, $userId);
		$result->likesInArray    = array();
		$result->dislikesInArray = array();
		$result->likes           = 0;
		$result->dislikes        = 0;
		$result->liked           = intval($result->userLiked > 0);
		$result->disliked        = intval(!$result->userLiked > 0);

		if (!empty ($like->like))
		{
			$result->likesInArray = explode(',', trim($like->like, ','));
			$result->likes        = count($result->likesInArray);
		}

		if (!empty ($like->dislike))
		{
			$result->dislikesInArray = explode(',', trim($like->dislike, ','));
			$result->dislikes        = count($result->dislikesInArray);
		}

		return $result;
	}


	/**
	 * This function returns the user permission over friend permission
	 *
	 * @param $userID   : the user who will be affected by the user permission.
	 * @param $friendID : the user who set the permission.
	 *
	 * @return $access_limit
	 */
	function getUserAccess($userID = null, $friendID = null)
	{
		$userID       = (isset($userID) && $userID) ? $userID : $this->IJUserID;
		$friendID     = (isset($friendID) && $friendID) ? $friendID : $this->IJUserID;
		$user         = CFactory::getUser($userID);
		$access_limit = 0;

		if ($user->id > 0)
		{
			$access_limit = PRIVACY_MEMBERS; // access level for member
		}

		$isfriend = $user->isFriendWith($friendID);
		if ($isfriend)
		{
			$access_limit = PRIVACY_FRIENDS; // access level for friends
		}

		if ($friendID == $this->IJUserID && $user->id != 0)
		{
			$access_limit = PRIVACY_PRIVATE; // access level for private
		}

		return $access_limit;
	}


	/**
	 * This function returns comment count
	 *
	 * @param $uniqueID : id of the element.
	 * @param $type     : type of the comment. // videos, albums, photos, profile.status,
	 *
	 * @return integer  count
	 */
	function getCommentCount($uniqueID, $type)
	{
		$query = "SELECT COUNT(*)
				FROM {$this->db->{JOOMLA_DB_NAMEQOUTE}('#__community_wall')}
				WHERE {$this->db->{JOOMLA_DB_NAMEQOUTE}('contentid')}={$this->db->Quote($uniqueID)}
				AND {$this->db->{JOOMLA_DB_NAMEQOUTE}('type')}={$this->db->Quote($type)}";
		$this->db->setQuery($query);
		$count = $this->db->loadResult();

		return $count;
	}

	/**
	 * This function is use to get user details
	 *
	 * @param   integer  $userID     userid
	 * @param   [type]  $frontUser  frontuser
	 *
	 * @return  $user
	 */
	function getUserDetail($userID, $frontUser = null)
	{
		$userObj    =CFactory::getUser($userID);
		$frontUser = ($frontUser) ? $frontUser : $this->IJUserID;
		//get storage path
		if ($this->config->get('user_avatar_storage') == 'file')
		{
			$p_url = JURI::base();
		}
		else
		{
			$s3BucketPath = $this->config->get('storages3bucket');
			if (!empty($s3BucketPath))
				$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
			else
				$p_url = JURI::base();
		}

		// get access level and profile view permission.
		$params        = $userObj->getParams();
		$access_limit = $this->getUserAccess($frontUser, $userObj->_userid);
		$profileview  = $params->get('privacyProfileView'); // get profile view access
		//get latitude longitude
		if ($userObj->latitude != '255' && $userObj->longitude != '255' && $userObj->latitude != '' && $userObj->longitude != '')
		{
			$latitude  = $userObj->latitude;
			$longitude = $userObj->longitude;
		}
		else
		{
			$query = "SELECT `latitude`, `longitude`
					FROM #__community_users
					WHERE `userid`={$userID}";
			$this->db->setQuery($query);
			$user_detail = $this->db->loadObject();

			if (!empty($user_detail->latitude))
			{
				$latitude  = $user_detail->latitude;
				$longitude = $user_detail->longitude;
				$userObj->set('latitude', $latitude);
				$userObj->set('longitude', $longitude);
				$userObj->save('latitude');
				$userObj->save('longitude');
			}
			else
			{
				$query = "SELECT *
						FROM #__community_fields_values as cfv
						LEFT JOIN #__community_fields as cf ON cfv.field_id=cf.id
						WHERE cfv.user_id={$userID}";
				$this->db->setQuery($query);
				$user_detail = $this->db->loadObjectList();

				$addrss = $city = $state = $country = '';

				if ($user_detail)
				{
					foreach ($user_detail as $detail)
					{
						$addrss  = ($detail->fieldcode == $this->config->get('fieldcodestreet')) ? $detail->value : $addrss;
						$city    = ($detail->fieldcode == $this->config->get('fieldcodecity')) ? $detail->value : $city;
						$state   = ($detail->fieldcode == $this->config->get('fieldcodestate')) ? $detail->value : $state;
						$country = ($detail->fieldcode == $this->config->get('fieldcodecountry')) ? $detail->value : $country;
					}
				}
				$latlong   = $this->GetLatLong($addrss, $city, $state, $country);
				$value     = explode(',', $latlong);
				$latitude  = $value[1];
				$longitude = $value[0];
				$this->updateLatLong($userID, $latitude, $longitude);
			}
		}

		$user         = new stdClass;
		$user->id     = ($this->IJUserID == $userObj->id) ? 0 : intval($userObj->id);
		$user->name   = $this->getName($userObj);
		$user->status = $userObj->_status;
		$user->avatar = ($userObj->_avatar) ? $p_url . $userObj->_avatar : JURI::base() . 'components/com_community/assets/user_thumb.png';
		if (getimagesize($user->avatar) === false)
		{
			$user->avatar = JURI::base() . 'components/com_community/assets/user_thumb.png';
		}
		$user->latitude  = $latitude;
		$user->longitude = $longitude;
		$user->online    = ($userObj->_isonline != '') ? 1 : 0;
		$user->profile   = ($profileview == 40 OR $profileview > $access_limit) ? 0 : 1;
		$user->view      = $userObj->_view;

		return $user;
	}

	/**
	 * function for uploadAudioFile
	 *
	 * @return boolean  true on success or false on failure
	 */
	public function uploadAudioFile()
	{
		jimport('joomla.filesystem.file');

		$audiofile  = JRequest::getVar('voice', null, 'files', 'array');
		$randomname = 'ijoomeradv_' . substr(md5(microtime()), rand(0, 26), 5);

		$filename = JFile::makeSafe($audiofile['name']);
		$fileext  = strtolower(JFile::getExt($filename));
		$src      = $audiofile['tmp_name'];
		$dest3gp  = JPATH_COMPONENT_SITE . '/assets/voice' . '/' . $randomname . '.' . $fileext;
		$destmp3  = JPATH_COMPONENT_SITE . '/assets/voice' . '/' . $randomname . '.mp3';

		if ($fileext == '3gp' or $fileext == 'aac' or $fileext == 'm4a')
		{
			if (JFile::upload($src, $dest3gp))
			{
				$cmd = 'ffmpeg -i ' . $dest3gp . ' -acodec mp3 ' . $destmp3 . '|ffmpeg -i ' . $dest3gp . ' -sameq ' . $destmp3;
				shell_exec($cmd);
				$durationresult = shell_exec("ffmpeg -i " . $destmp3 . ' 2>&1 | grep -o \'Duration: [0-9:.]*\'');
				$duration       = explode(':', str_replace('Duration: ', '', $durationresult));
				$minute         = $duration[1];
				$sec            = explode('.', $duration[2]);

				$voicefiletext = $randomname . '.mp3';
				//$durationtext = $minute.':'.$sec[0];
				$durationtext = (($minute * 60) + $sec[0]);

				$fileinfo['voicetext']    = '{voice}' . $voicefiletext . '&' . $durationtext . '{/voice}';
				$fileinfo['voice3gppath'] = $this->addAudioFile('{voice}' . $voicefiletext . '&' . $durationtext . '{/voice}');

				return $fileinfo;
			}
			else
			{
				//TODO File not uploded sucessfully
				return false;
			}
		}
		else
		{
			//TODO bad extension for file uppload
			return false;
		}
	}

	/**
	 * function for add Audio File
	 *
	 * @param  [type]  $content  content
	 *
	 * @return $content
	 */
	public function addAudioFile($content)
	{
		$matches = array();
		preg_match_all('/{voice}(.*?){\/voice}/', $content, $matches);
		$i = 0;
		foreach ($matches[1] as $match)
		{
			$content = preg_replace('/{voice}(.*?){\/voice}/', '{voice}' . JURI::base() . 'components/com_ijoomeradv/assets/voice' . '/' . $match . '{/voice}', $content, 1);
			$content = str_replace('amp;', '', $content);
			$i++;
		}

		return $content;
	}
}