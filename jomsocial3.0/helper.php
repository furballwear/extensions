<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial3.0
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

/**
 * class for jomhelper
 *
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial3.0
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
	 * construct function
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
	 * getName function
	 *
	 * @param   [type]  $obj  object
	 *
	 * @return  boolean        retuns value
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
	 * isconnected function
	 *
	 * @param   [type]  $id1  for id
	 * @param   [type]  $id2  for id
	 *
	 * @return  boolean        returns value
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
	 * isMember function
	 *
	 * @param   integer  $id1  for id
	 *
	 * @return  boolean        returns value
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
	 * getjomsocialversion
	 *
	 * @return  boolean  returns value
	 */
	function getjomsocialversion()
	{

		$xmlfile = JPATH_ROOT . '/administrator/components/com_community/community.xml';
		$xml     = JFactory::getXML($xmlfile, 1);
		$version = (string) $xml->version;

		return $version;
	}

	/**
	 * getNotificationParams
	 *
	 * @param   integer  $userid  id of user
	 *
	 * @return  boolean            returns result
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
	 * GetLatLong function
	 *
	 * @param   string  $addrss   address
	 * @param   string  $city     city name
	 * @param   string  $state    state name
	 * @param   string  $country  country name
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

		$url = "http://maps.google.com/maps/geo?q={$q}&output=json&oe=utf8&sensor=true_or_false&key={$myKey}";

		$init = curl_init();
		curl_setopt($init, CURLOPT_URL, $url);
		curl_setopt($init, CURLOPT_HEADER, 0);
		curl_setopt($init, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($init, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($init);
		curl_close($init);

		$l = ",";
		if (!empty($response))
		{
			$arr = json_decode($response, true);
			if (is_array($arr))
			{
				if ($arr['Status']['code'] == 200)
				{
					$l = implode(',', $arr['Placemark'][0]['Point']['coordinates']);
					if (trim($country) != "")
					{
						foreach ($arr['Placemark'] As $placemark)
						{
							if ($country == $placemark['AddressDetails']['Country']['CountryName'])
							{
								$l = implode(',', $placemark['Point']['coordinates']);
								break;
							}
						}
					}
				}
			}
		}

		return $l;
	}

	/**
	 * googleAuthenticate function
	 *
	 * @param   [type]  $username  name of user
	 * @param   [type]  $password  passwoed
	 * @param   [type]  $service   service
	 *
	 * @return  boolean             matches
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
	 * sendMessageToAndroid function
	 *
	 * @param   integer  $authCode              authenticatin code
	 * @param   integer  $deviceRegistrationId  id of device
	 * @param   [type]   $msgType               type of message
	 * @param   [type]   $messageText           text message
	 * @param   string   $totMsg                total message
	 * @param   [type]   $whentype              type
	 *
	 * @return  boolean                         returns value
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
	 *send_push_notification function
	 *
	 * @param   [type]   $device_token  device token
	 * @param   string   $message       message
	 * @param   integer  $badge         badge
	 * @param   string   $type          type
	 *
	 * @return  boolean                  address
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

		$fp = stream_socket_client($server, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
		// for production change the server to ssl://gateway.push.apple.com:219

		if (!$fp)
		{
			return;
		}
		$payload = json_encode($body);

		$msg = chr(0) . pack("n", 32) . pack('H*', str_replace(' ', '', $device_token)) . pack("n", strlen($payload)) . $payload;
		fwrite($fp, $msg);
		fclose($fp);
	}
	/**
	 * updateLatLong function
	 *
	 * @param   integer  $uid   user id
	 * @param   integer  $lat   [description]
	 * @param   integer  $long  [description]
	 *
	 * @return  void
	 */
	function updateLatLong($uid = 0, $lat = 255, $long = 255)
	{
		$db =  JFactory::getDBO();
		if ($uid == 0)
			return false;

		$query = "UPDATE #__community_users
				SET `latitude`='{$lat}', `longitude`='{$long}'
				WHERE `userid`='{$uid}'";
		$this->db->setQuery($query);
		$this->db->Query();
	}

	/**
	 * getaddress function
	 *
	 * @param   [type]  $lattitude  [description]
	 * @param   [type]  $longitude  [description]
	 *
	 * @return  boolean              address
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
	 * gettitle function
	 *
	 * @param   [type]  $location  location
	 *
	 * @return  boolean             returns value
	 */
	function gettitle($location)
	{
		if ($location != '')
		{
			//
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
	 * timeLapse
	 *
	 * @param   integer  $date  date
	 *
	 * @return  boolean         lapse
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
	 * getDate function
	 *
	 * @param   string   $str  string
	 * @param   integer  $off  [description]
	 *
	 * @return  boolean         returns date
	 */
	function getDate($str = '', $off = 0)
	{
		require_once JPATH_ROOT . '/components/com_community/libraries/core.php';

		$extraOffset = $this->config->get('daylightsavingoffset');
		//convert to utc time first.
		$utc_date = new JDate($str);
		$date     = new JDate($utc_date->toUnix() + $off * 3600);

		$my  =  JFactory::getUser();
		$cMy = CFactory::getUser();

		//J1.6 returns timezone as string, not integer offset.
		if (method_exists('JDate', 'getOffsetFromGMT'))
		{
			$systemOffset = new JDate('now', $this->mainframe->getCfg('offset'));
			$systemOffset = $systemOffset->getOffsetFromGMT(true);
		}
		else
		{
			$systemOffset = $this->mainframe->getCfg('offset');
		}

		if (!$my->id)
		{
			$date->setTimezone($systemOffset + $extraOffset);
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
				$date->setTimezone($offset);
			}
			else
				$date->setTimezone($systemOffset + $extraOffset);
		}

		return $date;
	}
	/**
	 * showDate function
	 *
	 * @param   [type]  $time    time
	 * @param   string  $mode    mode
	 * @param   string  $tz      [description]
	 * @param   [type]  $offset  [description]
	 *
	 * @return  boolean           date
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
			$date->setTimezone($offset);
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
	 *
	 * @uses to get the notification count for logged in user
	 *
	 */
	function getNotificationCount()
	{
		// get message notification count
		$query = "SELECT count(b.`id`)
				FROM #__community_msg_recepient as a, #__community_msg as b
				WHERE a.`to` = {$this->IJUserID}
				AND `is_read` = 0
				AND a.`deleted` = 0
				AND b.`id` = a.`msg_id`";
		$this->db->setQuery($query);
		$unreadInbox = $this->db->loadResult();
		if ((int) $unreadInbox > 0)
		{
			$jsonarray['notification']['messageNotification'] = intval($unreadInbox);
		}

		// getting pending friend request count
		$friendModel                                     =  CFactory::getModel('friends');
		$pendingFren                                     = $friendModel->countPending($this->IJUserID);
		$jsonarray['notification']['friendNotification'] = intval($pendingFren);

		// get globaknotification
		$eventModel = CFactory::getModel('events');
		$groupModel = CFactory::getModel('groups');

		$frenHtml         = '';
		$ind              = 0;
		$globalinvItation = 0;

		//getting pending event request
		$pendingEvent = $eventModel->getPending($this->IJUserID);
		$globalinvItation += count($pendingEvent);
		//getting pending group request
		$pendingGroup = $groupModel->getGroupInvites($this->IJUserID);
		$globalinvItation += count($pendingGroup);
		//geting pending private group join request
		//Find Users Groups Admin
		$allGroups = $groupModel->getAdminGroups($this->IJUserID, COMMUNITY_PRIVATE_GROUP);
		$globalinvItation += count($allGroups);
		//non require action notification
		CFactory::load('helpers', 'content');
		$notifCount        = 5;
		$notificationModel = CFactory::getModel('notification');
		$myParams          =  $this->my->getParams();

		//$notifications = $notificationModel->getNotificationCount($this->IJUserID,'0',$myParams->get('lastnotificationlist',''));
		$sinceWhere = '';
		$type       = 0;
		$since      = $myParams->get('lastnotificationlist', '');
		if (!empty($since))
		{
			$sinceWhere = ' AND ' . $this->db->quoteName('created') . ' >= ' . $this->db->Quote($since);
		}

		$query = 'SELECT COUNT(*)  FROM ' . $this->db->quoteName('#__community_notifications') . ' AS a '
			. 'WHERE a.' . $this->db->quoteName('target') . '=' . $this->db->Quote($this->IJUserID)
			. $sinceWhere
			. ' AND a.' . $this->db->quoteName('type') . '=' . $this->db->Quote($type)
			. ' AND a.' . $this->db->quoteName('cmd_type') . '!=' . $this->db->Quote('notif_inbox_create_message');
		$this->db->setQuery($query);
		$total = $this->db->loadResult();
		$globalinvItation += $total;

		$jsonarray['notification']['globalNotification'] = intval($globalinvItation);

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
	 *
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
			$act =  JTable::getInstance('Activity', 'CTable');
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
			$act =  JTable::getInstance('Activity', 'CTable');
			$act->load($itemId);
			$userid = $act->actor;
		}

		//===========================================================
		//Send push notification
		$sendpushflag = false;
		switch ($element)
		{
			case 'photo':
				$photo =  JTable::getInstance('Photo', 'CTable');
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
				$video =  JTable::getInstance('Video', 'CTable');
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
				$stream =  JTable::getInstance('Activity', 'CTable');
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
								$video      =  JTable::getInstance('Video', 'CTable');
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
								$album                   =  JTable::getInstance('Album', 'CTable');
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

								$bulletin =  JTable::getInstance('Bulletin', 'CTable');
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

								$discussion =  JTable::getInstance('Discussion', 'CTable');
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

									$wallModel                                         =  CFactory::getModel('wall');
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
								$group =  JTable::getInstance('Group', 'CTable');
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
								$event =  JTable::getInstance('Event', 'CTable');
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
	 * Like_bkp function
	 *
	 * @param   string  	$element  [description]
	 * @param   integer  $itemId   id
	 */
	function Like_bkp($element, $itemId)
	{
		$filter  = JFilterInput::getInstance();
		$element = $filter->clean($element, 'string');
		$itemId  = $filter->clean($itemId, 'int');

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(401); // if user is not logged in or not registered one.
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Load libraries
		CFactory::load('libraries', 'like');
		$like = new CLike;

		if ($element == 'groups.discussion' || $element == 'groups.discussion.reply' || $element == 'photos.album' || $element == 'albums' || $element == 'photos.wall.create')
		{
			$act =  JTable::getInstance('Activity', 'CTable');
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
			$act =  JTable::getInstance('Activity', 'CTable');
			$act->load($itemId);
			$userid = $act->actor;
		}

		//===========================================================
		//Send push notification
		$sendpushflag = false;
		switch ($element)
		{
			case 'photo':
				$photo =  JTable::getInstance('Photo', 'CTable');
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
				}
				break;
			case 'album':
				break;
			case 'videos':
				$video =  JTable::getInstance('Video', 'CTable');
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
				}
				break;
			case 'profile.status':
				$stream =  JTable::getInstance('Activity', 'CTable');
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
					}
				}
				break;
		}

		if ($sendpushflag)
		{
			if (IJOOMER_PUSH_ENABLE_IPHONE == 1 && $puser->device_type == 'iphone')
			{
				$options                        = array();
				$options['device_token']        = $puser->device_token;
				$options['live']                = intval(IJOOMER_PUSH_DEPLOYMENT_IPHONE);
				$options['aps']['message']      = $message;
				$options['aps']['type']         = $element;
				$options['aps']['content_data'] = $pushcontentdata;
				IJPushNotif::sendIphonePushNotification($options);
			}

			if (IJOOMER_PUSH_ENABLE_ANDROID == 1 && $puser->device_type == 'android')
			{
				$options                         = array();
				$options['registration_ids']     = array($puser->device_token);
				$options['data']['message']      = $message;
				$options['data']['type']         = ($element == 'photo') ? 'photos' : $element;
				$options['data']['content_data'] = $pushcontentdata;
				IJPushNotif::sendAndroidPushNotification($options);
			}
		}

		return true;
	}

	/**
	 * Dislike an item
	 *
	 * @param   string  $element Can either be core object (photo/album/videos/profile/profile.status) or a plugins (plugins,plugin_name)
	 * @param   mixed   $itemId  Unique id to identify object item
	 *
	 * @filesource com_community/controllers/system.php
	 * @method ajaxDislike
	 *
	 */
	function Dislike($element, $itemId)
	{
		$filter  = JFilterInput::getInstance();
		$itemId  = $filter->clean($itemId, 'int');
		$element = $filter->clean($element, 'string');

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(401); // if user is not logged in or not registered one.
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Load libraries
		CFactory::load('libraries', 'like');
		$dislike = new CLike;

		if ($element == 'groups.discussion' || $element == 'groups.discussion.reply' || $element == 'photos.album')
		{
			$act =  JTable::getInstance('Activity', 'CTable');
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
	 */
	function Unlike($element, $itemId)
	{
		$filter  = JFilterInput::getInstance();
		$itemId  = $filter->clean($itemId, 'int');
		$element = $filter->clean($element, 'string');

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(401); // if user is not logged in or not registered one.
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Load libraries
		CFactory::load('libraries', 'like');
		$unlike = new CLike;

		if ($element == 'groups.discussion' || $element == 'groups.discussion.reply' || $element == 'photos.album' || $element == 'albums' || $element == 'photos.wall.create')
		{
			$act =  JTable::getInstance('Activity', 'CTable');
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
	 */
	function getLikes($element, $itemId, $userId)
	{
		require_once JPATH_SITE . '/components/com_community/tables/like.php';
		$like =  JTable::getInstance('Like', 'CTable');
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
	 */
	function getCommentCount($uniqueID, $type)
	{
		$query = "SELECT COUNT(*)
				FROM {$this->db->quoteName('#__community_wall')}
				WHERE {$this->db->quoteName('contentid')}={$this->db->Quote($uniqueID)}
				AND {$this->db->quoteName('type')}={$this->db->Quote($type)}";
		$this->db->setQuery($query);
		$count = $this->db->loadResult();

		return $count;
	}


	/**
	 * This function is use to get user details
	 */
	function getUserDetail($userID, $frontUser = null)
	{
		$userObj   = CFactory::getUser($userID);
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
		$params       =  $userObj->getParams();
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
			$query = "SELECT *
					FROM #__community_fields_values as cfv
					LEFT JOIN #__community_fields as cf ON cfv.field_id=cf.id
					WHERE cfv.user_id={$userID}";
			$this->db->setQuery($query);
			$user_detail = $this->db->loadObjectList();

			if ($user_detail)
			{
				foreach ($user_detail as $detail)
				{
					$addrss  = ($detail->fieldcode == $this->config->get('fieldcodestreet')) ? $detail->value : '';
					$city    = ($detail->fieldcode == $this->config->get('fieldcodecity')) ? $detail->value : '';
					$state   = ($detail->fieldcode == $this->config->get('fieldcodestate')) ? $detail->value : '';
					$country = ($detail->fieldcode == $this->config->get('fieldcodecountry')) ? $detail->value : '';
				}
			}
			else
			{
				$addrss = $city = $state = $country = '';
			}
			$latlong   = $this->GetLatLong($addrss, $city, $state, $country);
			$value     = explode(',', $latlong);
			$latitude  = $value[1];
			$longitude = $value[0];
			$this->updateLatLong($userID, $latitude, $longitude);
		}

		$user            = new stdClass;
		$user->id        = ($this->IJUserID == $userObj->id) ? 0 : intval($userObj->id);
		$user->name      = $this->getName($userObj);
		$user->status    = $userObj->_status;
		$user->avatar    = $userObj->getAvatar();//($userObj->_avatar) ? $p_url.$userObj->_avatar : JURI::base().'components/com_community/assets/user_thumb.png';
		$user->latitude  = $latitude;
		$user->longitude = $longitude;
		$user->online    = ($userObj->_isonline != '') ? 1 : 0;
		$user->profile   = ($profileview == 40 OR $profileview > $access_limit) ? 0 : 1;
		$user->view      = $userObj->_view;
		$user->cover     = $userObj->getCover();

		return $user;
	}
	/**
	 * getTitleTag function
	 *
	 * @param   [type]  $html_data  html data
	 *
	 * @return  boolean              titletag
	 */
	public function getTitleTag($html_data)
	{
		$titletag = isset($html_data->title) ? $html_data->title : '';
		$user     = CFactory::getUser($html_data->actor);
		$username = $user->getDisplayName();
		$param    = new JRegistry($html_data->params);
		$action   = $param->get('action');

		switch ($html_data->app)
		{
			case 'friends.connect':
				$user1 = CFactory::getUser($act->actor);
				$user2 = CFactory::getUser($act->target);

				$my    = CFactory::getUser();
				$you   = null;
				$other = null;

				if ($my->id == $user1->id)
				{
					$you   = $user1;
					$other = $user2;
				}

				if ($my->id == $user2->id)
				{
					$you   = $user2;
					$other = $user1;
				}

				if (!is_null($you))
				{
					$titletag = JText::sprintf('COM_COMMUNITY_STREAM_MY_FRIENDS', $other->getDisplayName(), CUrlHelper::userLink($other->id));
				}
				else
				{
					$titletag = JText::sprintf('COM_COMMUNITY_STREAM_OTHER_FRIENDS', $user1->getDisplayName(), $user2->getDisplayName(), CUrlHelper::userLink($user1->id), CUrlHelper::userLink($user2->id));
				}
				break;

			case 'profile.avatar.upload':
				$titletag = $username . JText::_('COM_COMMUNITY_ACTIVITIES_NEW_AVATAR');
				break;

			case 'photos':
				if ($param->get('style') == COMMUNITY_STREAM_STYLE || strpos($html_data->title, '{multiple}'))
				{
					$count = $param->get('count', 1);
					if (CStringHelper::isPlural($count))
					{
						$titletag = $username . JText::sprintf('COM_COMMUNITY_ACTIVITY_PHOTO_UPLOAD_TITLE_MANY', $count, '', CStringHelper::escape($html_data->album->name));
					}
					else
					{
						$titletag = $username . JText::sprintf('COM_COMMUNITY_ACTIVITY_PHOTO_UPLOAD_TITLE', '', CStringHelper::escape($html_data->album->name));;
					}
				}
				break;

			case 'photos.comment';
				$photo = JTable::getInstance('Photo', 'CTable');
				$photo->load($html_data->cid);
				$titletag = $username . ' ' . JText::sprintf('COM_COMMUNITY_ACTIVITIES_WALL_POST_PHOTO', $photo->getPhotoLink(), $photo->caption);
				break;

			case 'events':
				$event = JTable::getInstance('Event', 'CTable');
				$event->load($html_data->eventid);
				$actors = $param->get('actors');

				$titletag = $username . JText::sprintf('COM_COMMUNITY_EVENTS_ACTIVITIES_NEW_EVENT', CUrlHelper::eventLink($event->id), $event->title);
				break;

			case 'events.attend':
				$event = JTable::getInstance('Event', 'CTable');
				$event->load($html_data->eventid);
				if ($action == 'events.attendence.attend')
				{
					$actors = $param->get('actors');
					$users  = explode(',', $actors);
					foreach ($users as $actor)
					{
						if (!$actor)
						{
							$actor = $html_data->actor;
						}
						$user         = CFactory::getUser($actor);
						$actorsHTML[] = $user->getDisplayName();
					}
					$titletag = implode(', ', $actorsHTML) . JText::sprintf('COM_COMMUNITY_ACTIVITIES_EVENT_ATTEND', $event->getLink(), $event->title);
				}
				break;

			case 'videos':
				$titletag = CVideos::getActivityTitleHTML($html_data);
				break;

			case 'groups':
			case 'groups.join':
			case 'groups.discussion':
			case 'groups.discussion.reply':
				$group = JTable::getInstance('Group', 'CTable');
				$group->load($html_data->groupid);
				$actors = $param->get('actors');

				switch ($action)
				{
					case 'group.create':
						$titletag = $username . JText::sprintf('COM_COMMUNITY_GROUPS_NEW_GROUP', $group->getLink(), $group->name);
						break;

					case 'group.join':
						$users = explode(',', $actors);
						foreach ($users as $actor)
						{
							$user         = CFactory::getUser($actor);
							$actorsHTML[] = $user->getDisplayName();
						}
						$users    = implode(', ', $actorsHTML);
						$titletag = $users . JText::sprintf('COM_COMMUNITY_GROUPS_GROUP_JOIN', $group->getLink(), $group->name);
						break;

					case 'group.discussion.create':
					case 'group.discussion.reply':
						$config     = CFactory::getConfig();
						$discussion = JTable::getInstance('Discussion', 'CTable');
						$discussion->load($html_data->cid);
						$discussionLink = CRoute::_('index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $group->id . '&topicid=' . $discussion->id);

						$titletag = $username;
						$titletag .= ($action == 'group.discussion.create') ? JText::sprintf('COM_COMMUNITY_GROUPS_NEW_GROUP_DISCUSSION', $discussionLink, $discussion->title) : JText::sprintf('COM_COMMUNITY_GROUPS_REPLY_DISCUSSION', CRoute::_('index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $discussion->groupid . '&topicid=' . $discussion->id), $discussion->title);
						$titletag .= '➜' . $group->name . "\n";
						$titletag .= JHTML::_('string.truncate', ''/*$discussion->message*/, $config->getInt('streamcontentlength'), true, false);
						break;
				}
				break;

			case 'groups.bulletin':
				$group = JTable::getInstance('Group', 'CTable');
				$group->load($html_data->groupid);
				$config   = CFactory::getConfig();
				$bulletin = JTable::getInstance('Bulletin', 'CTable');
				$bulletin->load($html_data->cid);

				$titletag = $username;
				$titletag .= JText::sprintf('COM_COMMUNITY_GROUPS_NEW_GROUP_NEWS', CRoute::_('index.php?option=com_community&view=groups&task=viewbulletin&groupid=' . $group->id . '&bulletinid=' . $bulletin->id), $bulletin->title);
				$titletag .= '➜' . $group->name . "\n";
				$titletag .= JHTML::_('string.truncate', $bulletin->message, $config->getInt('streamcontentlength'), true, false);
				break;

			case 'albums.comment':
			case 'albums':
				$album = JTable::getInstance('Album', 'CTable');
				$album->load($html_data->cid);
				$wall = JTable::getInstance('Wall', 'CTable');
				$wall->load($param->get('wallid'));

				$titletag = $users . JText::sprintf('COM_COMMUNITY_ACTIVITIES_WALL_POST_ALBUM', CRoute::_($album->getURI()), $album->name);
				break;

			case 'system.message':
			case 'system.videos.popular':
			case 'system.photos.popular':
			case 'system.members.popular':
			case 'system.photos.total':
			case 'system.groups.popular':
			case 'system.members.registered':
				switch ($action)
				{
					case 'registered_users':
						$usersModel = CFactory::getModel('user');
						$now        = new JDate;
						$date       = CTimeHelper::getDate();

						$users           = $usersModel->getUserRegisteredByMonth($now->format('Y-m'));
						$totalRegistered = count($users);

						$titletag = JText::_('COM_COMMUNITY_TOTAL_USERS_REGISTERED_THIS_MONTH');
						$titletag .= "\n" . JText::sprintf('COM_COMMUNITY_TOTAL_USERS_REGISTERED_THIS_MONTH_ACTIVITY_TITLE', $totalRegistered, $date->monthToString($now->format('%m')));
						break;

					case 'total_photos':
						$photosModel = CFactory::getModel('photos');
						$total       = $photosModel->getTotalSitePhotos();
						$titletag    = JText::sprintf('COM_COMMUNITY_TOTAL_PHOTOS_ACTIVITY_TITLE', CRoute::_('index.php?option=com_community&view=photos'), $total);
						break;

					case 'top_videos':
						$titletag = JText::_('COM_COMMUNITY_ACTIVITIES_TOP_VIDEOS');
						break;

					case 'top_photos':
						$titletag = JText::_('COM_COMMUNITY_ACTIVITIES_TOP_PHOTOS');
						break;

					case 'top_users':
						$titletag = JText::_('COM_COMMUNITY_ACTIVITIES_TOP_MEMBERS');
						break;

					case 'top_groups':
						$groupsModel = CFactory::getModel('groups');
						$activeGroup = $groupsModel->getMostActiveGroup();

						if (is_null($activeGroup))
						{
							$titletag = JText::_('COM_COMMUNITY_GROUPS_NONE_CREATED');
						}
						else
						{
							$titletag = JText::sprintf('COM_COMMUNITY_MOST_POPULAR_GROUP_ACTIVITY_TITLE', CRoute::_('index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $activeGroup->id), $activeGroup->name);

						}
						break;

					case 'message':
						$titletag = CActivities::format($html_data->title);
						break;
				}
				break;

			case 'profile.like':
			case 'groups.like':
			case 'events.like':
			case 'photo.like':
			case 'videos.like':
			case 'album.like':
				$param     = new CParameter($html_data->params);
				$actors    = $param->get('actors');
				$user      = CFactory::getUser($html_data->actor);
				$users     = explode(',', $actors);
				$userCount = count($users);
				switch ($html_data->app)
				{
					case 'profile.like':
						$cid     = CFactory::getUser($html_data->cid);
						$urlLink = CUrlHelper::userLink($cid->id);
						$name    = $cid->getDisplayName();
						$element = 'COM_COMMUNITY_STREAM_LIKES_ELEMENT_PROFILE';
						break;
					case 'groups.like':
						$cid = JTable::getInstance('Group', 'CTable');
						$cid->load($html_data->groupid);
						$urlLink = $cid->getLink();
						$name    = $cid->name;
						$element = 'COM_COMMUNITY_STREAM_LIKES_ELEMENT_GROUP';
						break;
					case 'events.like':
						$cid = JTable::getInstance('Event', 'CTable');
						$cid->load($html_data->eventid);
						$urlLink = $cid->getLink();
						$name    = $cid->title;
						$element = 'COM_COMMUNITY_STREAM_LIKES_ELEMENT_EVENT';
					case 'photo.like':
						$cid = JTable::getInstance('Photo', 'CTable');
						$cid->load($html_data->cid);

						$urlLink = $cid->getPhotoLink();
						$name    = $cid->caption;
						$element = 'COM_COMMUNITY_STREAM_LIKES_ELEMENT_PHOTO';
						break;
					case 'videos.like':
						$cid = JTable::getInstance('Video', 'CTable');
						$cid->load($html_data->cid);

						$urlLink = $cid->getViewURI();
						$name    = $cid->getTitle();
						$element = 'COM_COMMUNITY_STREAM_LIKES_ELEMENT_VIDEO';
						break;
					case 'album.like':
						$cid = JTable::getInstance('Album', 'CTable');
						$cid->load($html_data->cid);

						$urlLink = $cid->getURI();
						$name    = $cid->name;
						$element = 'COM_COMMUNITY_STREAM_LIKES_ELEMENT_ALBUM';
						break;
				}

				foreach ($users as $actor)
				{
					$user         = CFactory::getUser($actor);
					$actorsHTML[] = '<a class="cStream-Author" href="' . CUrlHelper::userLink($user->id) . '">' . $user->getDisplayName() . '</a>';
				}

				$others = '';
				if ($userCount > 2)
				{
					$others = JText::sprintf('COM_COMMUNITY_STREAM_OTHERS_JOIN_GROUP', $userCount - 1);
				}
				$jtext = ($userCount > 1) ? 'COM_COMMUNITY_STREAM_LIKES_PLURAL' : 'COM_COMMUNITY_STREAM_LIKES_SINGULAR';

				$titletag = implode(' ' . JText::_('COM_COMMUNITY_AND') . ' ', $actorsHTML) . $others . JText::sprintf($jtext, $urlLink, $name, JText::_($element));
				break;

			case 'cover.upload':
				$user         = CFactory::getUser($html_data->actor);
				$params       = new JRegistry($html_data->params);
				$type         = $params->get('type');
				$extraMessage = '';
				if (strtolower($type) !== 'profile')
				{
					$id     = $type . 'id';
					$cTable = JTable::getInstance(ucfirst($type), 'CTable');
					$cTable->load($html_data->$id);

					if ($type == 'group')
					{
						$extraMessage = JText::sprintf('COM_COMMUNITY_PHOTOS_COVER_TYPE_LINK', $cTable->getLink(), $cTable->name);
					}
					if ($type == 'event')
					{
						$extraMessage = JText::sprintf('COM_COMMUNITY_PHOTOS_COVER_TYPE_LINK', CUrlHelper::eventLink($cTable->id), $cTable->title);
					}
				}

				$titletag = $user->getDisplayName() . ' ' . JText::sprintf('COM_COMMUNITY_PHOTOS_COVER_UPLOAD', strtolower($type)) . $extraMessage;
				break;

		}

		return trim(strip_tags($titletag));
	}
	/**
	 * getAlbumContetn function
	 *
	 * @param   [type]  $html_data  htmldata
	 *
	 * @return  boolean              photos
	 */
	public function getAlbumContent($html_data)
	{
		$db      = JFactory::getDBO();
		$param   = new CParameter($html_data->params);
		$photoid = $param->get('photoid', false);
		$count   = $param->get('count', 1);

		$photos =  JTable::getInstance('photo', 'CTable');
		$photos->load($photoid);

		$album =  JTable::getInstance('Album', 'CTable');
		$album->load($photos->albumid);

		if ($count == 1 && !empty($html_data->title))
		{
			$sql = "SELECT * FROM #__community_photos WHERE `albumid`=" . $album->id
				. " AND `id`=" . $photoid;
			$db->setQuery($sql);
			$photoresult = $db->loadObjectList();
		}
		else
		{
			$sql = "SELECT * FROM #__community_photos WHERE `albumid`=" . $album->id
				. " ORDER BY `id` DESC LIMIT 0, $count";
			$db->setQuery($sql);
			$photoresult = $db->loadObjectList();
		}

		$photos = array();
		foreach ($photoresult as $row)
		{
			$photo = JTable::getInstance('Photo', 'CTable');
			$photo->bind($row);
			$photos[] = $photo;
		}

		return $photos;
	}

	/**
	 * getVideos function
	 *
	 * @param   [type]  $html_data  html data
	 *
	 * @return  boolean              video
	 */
	public function getVideos($html_data)
	{
		$video = array();
		if ($html_data->app == 'videos')
		{
			$data                = CVideos::getActivityTitleHTML($html_data);
			$video['video_icon'] = JUri::base() . $html_data->video->thumb;
			$video['video_path'] = $html_data->video->path;
		}

		return $video;
	}

	/**
	 * uploadAudioFile function
	 *
	 * @return  boolean  file information
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

				$voicefiletext = $randomname . '.' . $fileext;
				$durationtext  = (($minute * 60) + $sec[0]);

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
	 * addAudioFile function
	 *
	 * @param  [type]  $content  content
	 */
	public function addAudioFile($content)
	{
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