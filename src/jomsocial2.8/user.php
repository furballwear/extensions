<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial2.8
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * class for user
 *
 * @package     IJoomer.Extensions
 * @subpackage  icms
 * @since       1.0
 */
class User
{
	private $jomHelper;

	private $date_now;

	private $IJUserID;

	private $mainframe;

	private $db;

	private $my;

	private $config;

	private $jsonarray = array();

	/**
	 * constructor
	 */
	public function __construct()
	{
		$this->jomHelper = new jomHelper;
		$this->date_now  = JFactory::getDate();
		$this->mainframe = JFactory::getApplication();

		// Set database object
		$this->db        = JFactory::getDBO();

		// Get login user id
		$this->IJUserID  = $this->mainframe->getUserState('com_ijoomeradv.IJUserID', 0);

		// Set the login user object
		$this->my        = CFactory::getUser($this->IJUserID);
		$this->config    = CFactory::getConfig();
		$notification    = $this->jomHelper->getNotificationCount();

		if (isset($notification['notification']))
		{
			$this->jsonarray['notification'] = $notification['notification'];
		}
	}

	/**
	 * function profile
	 *
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	function profile()
	{
		$userID = IJReq::getTaskData('userID', $this->IJUserID, 'int');

		if (!$userID)
		{
			// Set error code to restricted access
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$user = CFactory::getUser($userID);

		CFactory::load('helpers', 'friends');

		// Set privacy level
		$access_limit = $this->jomHelper->getUserAccess($this->IJUserID, $user->id);

		if ($access_limit < $user->getParams()->get('privacyProfileView'))
		{
			// Set error code to restricted access
			IJReq::setResponse(706);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Add count to visited user profile.
		$this->profileViewCount($userID);

		$this->jomHelper         = new jomHelper;
		$this->jsonarray['code'] = 200;

		$usr                              = $this->jomHelper->getUserDetail($userID);
		$this->jsonarray['user_name']     = $usr->name;
		$this->jsonarray['viewcount']     = $usr->view;
		$this->jsonarray['isfriend']      = intval(CFriendsHelper::isConnected($this->IJUserID, $user->id));
		$this->jsonarray['isFriendReqBy'] = 0;

		if ($user->getParams()->get('profileVideo'))
		{
			$video = JTable::getInstance('Video', 'CTable');
			$video->load($user->getParams()->get('profileVideo'));
			$this->jsonarray['profile_video']['title'] = $video->title;

			if ($video->type == 'file')
			{
				$this->jsonarray['profile_video']['url'] = JURI::base() . $video->path;
			}
			else
			{
				$this->jsonarray['profile_video']['url'] = $video->path;
			}
		}

		$friendModel  = CFactory::getModel('friends');
		$pendingFren = $friendModel->getPending($this->IJUserID);

		foreach ($pendingFren as $pfriend)
		{
			if ($user->id == $pfriend->id)
			{
				$this->jsonarray['isFriendReqBy'] = 1;
			}
		}

		$this->jsonarray['isFriendReqTo'] = 0;
		$pendingFren                      = $friendModel->getPending($user->id);

		foreach ($pendingFren as $pfriend)
		{
			if ($this->IJUserID == $pfriend->id)
			{
				$this->jsonarray['isFriendReqTo'] = 1;
			}
		}

		$query = "SELECT `status_access`
				FROM #__community_users
				WHERE `userid`={$user->id}";
		$this->db->setQuery($query);
		$status_access = $this->db->loadResult();

		$usr->status                    = $this->jomHelper->addAudioFile($usr->status);
		$this->jsonarray['user_status'] = ($status_access <= $access_limit) ? $usr->status : '';
		$this->jsonarray['user_avatar'] = $usr->avatar;
		$this->jsonarray['user_lat']    = $usr->latitude;
		$this->jsonarray['user_long']   = $usr->longitude;

		$likes = $this->jomHelper->getLikes('profile', $user->id, $this->IJUserID);

		$this->jsonarray['likes']         = $likes->likes;
		$this->jsonarray['dislikes']      = $likes->dislikes;
		$this->jsonarray['liked']         = $likes->liked;
		$this->jsonarray['disliked']      = $likes->disliked;
		$this->jsonarray['isprofilelike'] = ($user->getParams()->get('profileLikes', true)) ? 1 : 0;

		$query = "SELECT coverpic
				FROM #__ijoomeradv_users
				WHERE `userid`={$userID}";
		$this->db->setQuery($query);
		$coverpic = $this->db->loadResult();

		if ($coverpic)
		{
			$photos  = JTable::getInstance('Photo', 'CTable');
			$photos->load($coverpic);

			if (file_exists(JURI::base() . $photos->original))
			{
				$this->jsonarray['coverpic'] = JURI::base() . $photos->original;
			}
			else
			{
				$this->jsonarray['coverpic'] = JURI::base() . $photos->image;
			}
		}

		// Get total group
		if ($user->getParams()->get('privacyGroupsView') <= $access_limit)
		{
			$groupsModel                   = CFactory::getModel('groups');
			$totalgroups                   = $groupsModel->getGroupsCount($user->id);
			$this->jsonarray['totalgroup'] = $totalgroups;
		}

		// Get total friend
		if ($user->getParams()->get('privacyFriendsView') <= $access_limit)
		{
			$totalfriends                    = $user->getFriendCount();
			$this->jsonarray['totalfriends'] = $totalfriends;
		}

		// Get total photos
		if ($user->getParams()->get('privacyPhotoView') <= $access_limit)
		{
			$photosModel                    = CFactory::getModel('photos');
			$totalphotos                    = $photosModel->getPhotosCount($user->id);
			$this->jsonarray['totalphotos'] = $totalphotos;
		}

		// Get total videos
		if ($user->getParams()->get('privacyVideoView') <= $access_limit)
		{
			$videosModel                    = CFactory::getModel('videos');
			$totalvideos                    = $videosModel->getVideosCount($user->id);
			$this->jsonarray['totalvideos'] = $totalvideos;
		}

		return $this->jsonarray;
	}

	/**
	 * this function is used to add a view count to the visited user profile.
	 *
	 * @param   integer  $ID  id
	 *
	 * @return  boolean  true on success or false on failure
	 */
	private function profileViewCount($ID)
	{
		if (!$ID or intval($ID) == intval($this->IJUserID))
		{
			return false;
		}

		$query = "UPDATE #__community_users
				SET `view` = `view`+1
				WHERE `userid` ='{$ID}'";
		$this->db->setQuery($query);
		$this->db->query();

		return true;
	}

	/**
	 * uses    to fetch user details for a notification user
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"profile",
	 *        "extTask":"updateProfile",
	 *        "taskData":{
	 *            "name":"name"
	 *        }
	 *    }
	 *
	 * avatar image will be post to "image" variable
	 *
	 * status maessage update is removed form update profile. Status message can be added from addWall function from wall.php
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	function updateProfile()
	{
		$name = IJReq::getTaskData('name', '');

		// $message	= IJReq::getTaskData('status','');
		$file = JRequest::getVar('image', '', 'FILES', 'array');

		// Check if avatar is uploaded to change.
		if (isset($file['tmp_name']) && $file['tmp_name'] != '')
		{
			CFactory::setActiveProfile();
			jimport('joomla.filesystem.file');
			jimport('joomla.utilities.utility');
			CFactory::load('helpers', 'image');

			$uploadLimit = (double) $this->config->get('maxuploadsize');
			$uploadLimit = ($uploadLimit * 1024 * 1024);

			// @rule: Limit image size based on the maximum upload allowed.
			if (filesize($file['tmp_name']) > $uploadLimit && $uploadLimit != 0)
			{
				IJReq::setResponse(416, JText::_('COM_COMMUNITY_VIDEOS_IMAGE_FILE_SIZE_EXCEEDED'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			if (!CImageHelper::isValidType($file['type']))
			{
				IJReq::setResponse(415, JText::_('COM_COMMUNITY_IMAGE_FILE_NOT_SUPPORTED'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			if (!CImageHelper::isValid($file['tmp_name']))
			{
				IJReq::setResponse(415, JText::_('COM_COMMUNITY_IMAGE_FILE_NOT_SUPPORTED'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
			else
			{
				// @todo: configurable width?
				$imageMaxWidth = 160;
				$profileType   = $this->my->getProfileType();

				// Get a hash for the file name.
				$fileName      = JUtility::getHash($file['tmp_name'] . time());
				$hashFileName  = JString::substr($fileName, 0, 24);
				$multiprofile   = JTable::getInstance('MultiProfile', 'CTable');
				$multiprofile->load($profileType);

				$useWatermark = $profileType != COMMUNITY_DEFAULT_PROFILE && $this->config->get('profile_multiprofile') && !empty($multiprofile->watermark) ? true : false;

				// @todo: configurable path for avatar storage?
				$storage = JPATH_ROOT . '/' . $this->config->getString('imagefolder') '/avatar';
				$storageImage     = $storage . '/' . $hashFileName . CImageHelper::getExtension($file['type']);
				$storageThumbnail = $storage '/thumb_' . $hashFileName . CImageHelper::getExtension($file['type']);
				$image     = $this->config->getString('imagefolder') . '/avatar' . '/' . $hashFileName . CImageHelper::getExtension($file['type']);
				$thumbnail = $this->config->getString('imagefolder') . '/avatar/thumb_' . $hashFileName . CImageHelper::getExtension($file['type']);
				$userModel = CFactory::getModel('user');

				// Only resize when the width exceeds the max.
				if (!CImageHelper::resizeProportional($file['tmp_name'], $storageImage, $file['type'], $imageMaxWidth))
				{
					IJReq::setResponse(500, JText::sprintf('COM_COMMUNITY_ERROR_MOVING_UPLOADED_FILE', $storageImage));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				// Generate thumbnail
				if (!CImageHelper::createThumb($file['tmp_name'], $storageThumbnail, $file['type']))
				{
					IJReq::setResponse(500, JText::sprintf('COM_COMMUNITY_ERROR_MOVING_UPLOADED_FILE', $storageThumbnail));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				if ($useWatermark)
				{
					// @rule: Before adding the watermark, we should copy the user's original image so that when the admin tries to reset the avatar,
					// it will be able to grab the original picture.
					JFile::copy($storageImage, JPATH_ROOT . '/images/watermarks/original' . '/' . md5($this->my->id . '_avatar') . CImageHelper::getExtension($file['type']));
					JFile::copy($storageThumbnail, JPATH_ROOT . '/images/watermarks/original' . '/' . md5($this->my->id . '_thumb') . CImageHelper::getExtension($file['type']));

					$watermarkPath = JPATH_ROOT . '/' . CString::str_ireplace('/' . '/' . $multiprofile->watermark);

					list($watermarkWidth, $watermarkHeight) = getimagesize($watermarkPath);
					list($avatarWidth, $avatarHeight) = getimagesize($storageImage);
					list($thumbWidth, $thumbHeight) = getimagesize($storageThumbnail);

					$watermarkImage     = $storageImage;
					$watermarkThumbnail = $storageThumbnail;

					// Avatar Properties
					$avatarPosition = CImageHelper::getPositions($multiprofile->watermark_location, $avatarWidth, $avatarHeight, $watermarkWidth, $watermarkHeight);

					// The original image file will be removed from the system once it generates a new watermark image.
					CImageHelper::addWatermark($storageImage, $watermarkImage, 'image/jpg', $watermarkPath, $avatarPosition->x, $avatarPosition->y);

					// Thumbnail Properties
					$thumbPosition = CImageHelper::getPositions($multiprofile->watermark_location, $thumbWidth, $thumbHeight, $watermarkWidth, $watermarkHeight);

					// The original thumbnail file will be removed from the system once it generates a new watermark image.
					CImageHelper::addWatermark($storageThumbnail, $watermarkThumbnail, 'image/jpg', $watermarkPath, $thumbPosition->x, $thumbPosition->y);

					$this->my->set('_watermark_hash', $multiprofile->watermark_hash);
					$this->my->save();
				}

				$userModel->setImage($this->my->id, $image, 'avatar');
				$userModel->setImage($this->my->id, $thumbnail, 'thumb');

				// Update the user object so that the profile picture gets updated.
				$this->my->set('_avatar', $image);
				$this->my->set('_thumb', $thumbnail);

				// @rule: once user changes their profile picture, storage method should always be file.
				$this->my->set('_storage', 'file');

				// Add user points
				CFactory::load('libraries', 'userpoints');
				CFactory::load('libraries', 'activities');

				$msg = JText::_('COM_COMMUNITY_ACTIVITIES_NEW_AVATAR');

				$act               = new stdClass;
				$act->cmd          = 'profile.avatar.upload';
				$act->actor        = $this->my->id;
				$act->target       = 0;
				$act->title        = $this->my->getDisplayName() . " ► " . $msg;
				$act->content      = '';
				$act->app          = 'profile';
				$act->cid          = 0;
				$act->comment_id   = $this->my->id;
				$act->comment_type = 'profile.avatar.upload';

				$act->like_id   = $this->my->id;
				$act->like_type = 'profile.avatar.upload';

				// Add activity logging
				CFactory::load('libraries', 'activities');
				CActivityStream::add($act);

				CUserPoints::assignPoint('profile.avatar.upload');
			}
		}
		else
		{
			$image = '';
		}

		// Update status here..
		if ($message != '')
		{
			$filter  = JFilterInput::getInstance();
			$message = $filter->clean($message, 'string');
			$cache   = CFactory::getFastCache();
			$cache->clean(array('activities'));

			// @rule: In case someone bypasses the status in the html, we enforce the character limit.
			if (JString::strlen($message) > $this->config->get('statusmaxchar'))
			{
				$message = JString::substr($message, 0, $this->config->get('statusmaxchar'));
			}

			// Trim it here so that it wun go into activities stream.
			$message = JString::trim($message);
			CFactory::load('models', 'status');

			// @rule: Spam checks
			if ($this->config->get('antispam_akismet_status'))
			{
				CFactory::load('libraries', 'spamfilter');
				$filter = CSpamFilter::getFilter();
				$filter->setAuthor($this->my->getDisplayName());
				$filter->setMessage($message);
				$filter->setEmail($this->my->email);
				$filter->setURL(CRoute::_('index.php?option=com_community&view=profile&userid=' . $this->my->id));
				$filter->setType('message');
				$filter->setIP($_SERVER['REMOTE_ADDR']);

				if ($filter->isSpam())
				{
					IJReq::setResponse(705, JText::_('COM_COMMUNITY_STATUS_MARKED_SPAM'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}
			}

			$this->update($this->my->id, $message);

			jimport('joomla.utilities.date');

			// Set user status for current session.
			$today  = JFactory::getDate();

			$this->my->set('_status', $message);
			$this->my->set('_posted_on', $today->toMySQL());

			CFactory::load('helpers', 'string');
			$message = CStringHelper::escape($message);

			if (!empty($message))
			{
				$act         = new stdClass;
				$act->cmd    = 'profile.status.update';
				$act->actor  = $this->my->id;
				$act->target = $this->my->id;
				CFactory::load('helpers', 'linkgenerator');

				// @rule: Autolink hyperlinks
				$message = CLinkGeneratorHelper::replaceURL($message);

				// @rule: Autolink to users profile when message contains @username
				$message = CLinkGeneratorHelper::replaceAliasURL($message);
				CFactory::load('libraries', 'activities');
				$privacyParams = $this->my->getParams();

				$act->title        = $this->my->getDisplayName() . " ► " . $message;
				$act->content      = '';
				$act->app          = 'profile';
				$act->cid          = $this->my->id;
				$act->access       = $privacyParams->get('privacyProfileView');
				$act->comment_id   = CActivities::COMMENT_SELF;
				$act->comment_type = 'profile.status';
				$act->like_id      = CActivities::LIKE_SELF;
				$act->like_type    = 'profile.status';

				CActivityStream::add($act);

				// Add user points
				CFactory::load('libraries', 'userpoints');
				CUserPoints::assignPoint('profile.status.update');
			}
		}

		// Check if name passed to update
		if (isset($name) && !empty($name))
		{
			$query = "UPDATE `#__users`
					SET `name`='{$name}'
					WHERE `id`={$this->my->id}";
			$this->db->setQuery($query);
			$this->db->Query();
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * function for update
	 *
	 * @param   integer  $id      id
	 * @param   [type]   $status  status
	 *
	 * @return  boolean  true on success or false on failure
	 */
	private function update($id, $status)
	{
		$my = CFactory::getUser($id);

		require_once COMMUNITY_COM_PATH . '/libraries/apps.php';

		$appsLib  = CAppPlugins::getInstance();
		$appsLib->loadApplications();

		$args   = array();

		// Userid
		$args[] = $my->id;

		// Old status
		$args[] = $my->getStatus();

		// New status
		$args[] = $status;
		$appsLib->triggerEvent('onProfileStatusUpdate', $args);

		$today            = JFactory::getDate();
		$data            = new stdClass;
		$data->userid    = $id;
		$data->status    = $status;
		$data->posted_on = $today->toMySQL();

		$this->db->updateObject('#__community_users', $data, 'userid');

		return true;
	}

	/**
	 * uses    to add like to the user profile
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"like",
	 *        "taskData":{
	 *            "userID":"userID" // optional, if not passed then logged in user id will be used
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function like()
	{
		$userID = IJReq::getTaskData('userID', 0, 'int');
		$userID = ($userID) ? $userID : $this->my->id;

		$result = $this->jomHelper->Like('profile', $userID);

		if (!empty($result))
		{
			$this->jsonarray         = $result;
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * uses    to add dislike to the user profile
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"dislike",
	 *        "taskData":{
	 *            "userID":"userID" // optional, if not passed then logged in user id will be used
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function dislike()
	{
		$userID = IJReq::getTaskData('userID', 0, 'int');
		$userID = ($userID) ? $userID : $this->my->id;

		if ($this->jomHelper->Dislike('profile', $userID))
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * uses    to unlike like/dislike value to the user profile
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"unlike",
	 *        "taskData":{
	 *            "userID":"userID" // optional, if not passed then logged in user id will be used
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function unlike()
	{
		$userID = IJReq::getTaskData('userID', 0, 'int');
		$userID = ($userID) ? $userID : $this->my->id;

		if ($this->jomHelper->Unlike('profile', $userID))
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * uses    to get/set the user detail
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"userDetail",
	 *        "taskData":{
	 *            "userID":"userID"
	 *            "form":"0/1" (0=to post form, 1=to get form)
	 *        }
	 *    }
	 * @return  some value
	 */
	public function userDetail()
	{
		if (IJReq::getTaskData('form', 0, 'int') === 1)
		{
			return $this->getUserDetail();
		}
		else
		{
			return $this->setUserDetail();
		}
	}

	/**
	 * used to get the user detail form along with user data
	 *
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	private function getUserDetail()
	{
		$userID  = IJReq::getTaskData('userID', $this->IJUserID, 'int');
		$visitor = CFactory::getUser($userID);

		$access_limit = $this->jomHelper->getUserAccess($this->IJUserID, $visitor->id);

		$query = "SELECT field_id
				FROM #__community_profiles_fields AS cpf
				WHERE cpf.parent = {$visitor->_profile_id}";
		$this->db->setQuery($query);
		$fields_ids = $this->db->loadResultArray();

		$fields_cond = '';

		if (count($fields_ids) > 0)
		{
			$fields_cond = "AND `id` IN('" . implode("','", $fields_ids) . "') ";
		}

		$query = "SELECT *
				FROM #__community_fields
				WHERE published=1
				AND visible=1
				{$fields_cond}
				AND type NOT IN ('templates', 'profiletypes')
				ORDER BY ordering";
		$this->db->setQuery($query);
		$fields                  = $this->db->loadObjectList();
		$inc                     = -1;
		$this->jsonarray['code'] = 200;

		foreach ($fields as $field)
		{
			if ($field->type == 'group')
			{
				$inc++;
				$this->jsonarray['fields']['group'][$inc]['group_name'] = $field->name;
				$incj                                                   = 0;
			}
			else
			{
				$query = "SELECT cfv.value, cfv.access
						FROM #__community_fields_values as cfv
						LEFT JOIN #__community_fields as cf ON cfv.field_id=cf.id
						WHERE cfv.user_id='{$userID}'
						AND cfv.field_id='{$field->id}'
						AND cfv.access<={$access_limit}";
				$this->db->setQuery($query);
				$field_value = $this->db->loadObject();

				if (!($field_value->value) && $this->IJUserID != $userID)
				{
					continue;
				}

				$this->jsonarray['fields']['group'][$inc]['field'][$incj]['id']                 = $field->id;
				$this->jsonarray['fields']['group'][$inc]["field"][$incj]['caption']            = $field->name;
				$this->jsonarray['fields']['group'][$inc]["field"][$incj]['privacy']['value']   = (isset($field_value->access)) ? $field_value->access : '0';
				$this->jsonarray['fields']['group'][$inc]["field"][$incj]['privacy']['options'] = array(
					0 => array('value' => 0, 'caption' => 'Public'),
					1 => array('value' => 20, 'caption' => 'Site Members'),
					2 => array('value' => 30, 'caption' => 'Friend'),
					3 => array('value' => 40, 'caption' => 'Only Me'));

				if ($field->type == 'birthdate')
				{
					$field->type = 'date';

					if (isset($field_value->value))
					{
						$tm                                                                = explode(' ', $field_value->value);
						$dt                                                                = $tm[0];
						$this->jsonarray['fields']['group'][$inc]['field'][$incj]['value'] = ($dt) ? $dt : '';
					}
					else
					{
						$this->jsonarray['fields']['group'][$inc]['field'][$incj]['value'] = '';
					}
				}
				else
				{
					$this->jsonarray['fields']['group'][$inc]['field'][$incj]['value'] = (isset($field_value->value)) ? $field_value->value : '';
				}

				$this->jsonarray['fields']['group'][$inc]['field'][$incj]['required'] = $field->required;

				if ($field->type == 'checkbox' || $field->type == 'list')
				{
					$field->type = 'multipleselect';
				}

				if ($field->type == 'singleselect' || $field->type == 'radio' || $field->type == 'country')
				{
					$field->type = 'select';
				}

				if ($field->type == 'email' || $field->type == 'url')
				{
					$field->type = 'text';
				}

				if ($field->fieldcode == 'FIELD_CITY' || $field->fieldcode == 'FIELD_STATE')
				{
					$field->type = 'map';
				}

				$this->jsonarray['fields']['group'][$inc]['field'][$incj]['type'] = $field->type;

				if (isset($field->options) && !empty($field->options))
				{
					$option = explode("\n", $field->options);
					$i      = 0;

					foreach ($option as $val)
					{
						$this->jsonarray['fields']['group'][$inc]['field'][$incj]['options'][$i]['value'] = $val;
						$i++;
					}
				}

				$incj++;
			}
		}

		foreach ($this->jsonarray['fields']['group'] as $key => $value)
		{
			if (!isset($value['field']))
			{
				unset($this->jsonarray['fields']['group'][$key]);
			}
		}

		if (empty($this->jsonarray['fields']['group']))
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		return $this->jsonarray;
	}

	/**
	 * function for setthe User Detail
	 *
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	private function setUserDetail()
	{
		$fields = IJReq::getTaskData('formData');
		$flag   = true;

		foreach ($fields as $key => $fvalue)
		{
			$fid = str_replace("f", "", $key);

			$query = "SELECT COUNT(*)
					FROM #__community_fields_values
					WHERE `user_id`='{$this->IJUserID}' AND `field_id`='{$fid}'";
			$this->db->setQuery($query);
			$isNew = ($this->db->loadResult() <= 0) ? true : false;

			if (!$isNew)
			{
				$query = " UPDATE #__community_fields_values
						SET `value`='$fvalue[0]', `access`=$fvalue[1]
						WHERE `user_id`=$this->IJUserID AND `field_id`=$fid";
			}
			else
			{
				$query = "INSERT INTO #__community_fields_values (user_id,field_id,value,access)
						VALUES ({$this->IJUserID}, {$fid}, '{$fvalue[0]}', '{$fvalue[1]}')";
			}

			$this->db->setQuery($query);

			if (!$this->db->query())
			{
				IJReq::setResponse(500);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * uses    to get notification
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"notification"
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function notification()
	{
		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$this->jsonarray['code'] = 200;
		}

		$inboxModel = CFactory::getModel('inbox');
		$messages   = $inboxModel->getUnReadInbox();
		$ind        = 0;

		if (!empty($messages))
		{
			foreach ($messages as $key => $message)
			{
				$this->jsonarray['notifications']['messages'][$ind]['id']           = $message->id;
				$this->jsonarray['notifications']['messages'][$ind]['parent']       = $message->parent;
				$this->jsonarray['notifications']['messages'][$ind]['subject']      = $message->subject;
				$format                                                             = JText::_('COM_COMMUNITY_DATE_FORMAT_LC2_24H');
				$timezone                                                           = JFactory::getConfig()->get('offset');
				$dtz                                                                = new DateTimeZone($timezone);
				$dt                                                                 = new DateTime("now", $dtz);
				$offset                                                             = timezone_offset_get($dtz, $dt) / 3600;
				$date                                                               = CTimeHelper::getFormattedUTC($message->posted_on, $offset);
				$date                                                               = CTimeHelper::getFormattedTime($date, $format);
				$this->jsonarray['notifications']['messages'][$ind]['date']         = $date;
				$this->jsonarray['notifications']['messages'][$ind]['outgoing']     = 0;
				$this->jsonarray['notifications']['messages'][$ind]['read']         = 0;
				$usr                                                                = $this->jomHelper->getUserDetail($message->from);
				$this->jsonarray['notifications']['messages'][$ind]['user_id']      = $usr->id;
				$this->jsonarray['notifications']['messages'][$ind]['user_name']    = $usr->name;
				$this->jsonarray['notifications']['messages'][$ind]['user_avatar']  = $usr->avatar;
				$this->jsonarray['notifications']['messages'][$ind]['user_profile'] = $usr->profile;
				$ind++;
			}
		}

		// Getting friend request
		$ind         = 0;
		$friendModel = CFactory::getModel('friends');
		$pendingFren = $friendModel->getPending($this->IJUserID);

		if (!empty($pendingFren))
		{
			foreach ($pendingFren as $key => $pendingFrnd)
			{
				$usr = $this->jomHelper->getUserDetail($pendingFrnd->id);

				$query = "SELECT msg
						FROM #__community_connection
						WHERE connection_id ={$pendingFrnd->connection_id}";
				$this->db->setQuery($query);
				$msg = $this->db->loadResult();

				$this->jsonarray['notifications']['friends'][$ind]['user_id']       = $usr->id;
				$this->jsonarray['notifications']['friends'][$ind]['user_name']     = $usr->name;
				$this->jsonarray['notifications']['friends'][$ind]['user_avatar']   = $usr->avatar;
				$this->jsonarray['notifications']['friends'][$ind]['user_profile']  = $usr->profile;
				$this->jsonarray['notifications']['friends'][$ind]['message']       = $msg;
				$this->jsonarray['notifications']['friends'][$ind]['connection_id'] = $pendingFrnd->connection_id;
				$ind++;
			}
		}

		$eventModel = CFactory::getModel('events');
		$groupModel = CFactory::getModel('groups');

		$frenHtml  = '';
		$notiTotal = 0;

		$ind = 0;

		if ($this->config->get('user_avatar_storage') == 'file')
		{
			$p_url = JURI::base();
		}
		elseif ($this->config->get('groups_avatar_storage') == 'file')
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

		// Getting pending event request
		$pendingEvent = $eventModel->getPending($this->IJUserID);
		$event         = JTable::getInstance('Event', 'CTable');

		if (is_array($pendingEvent))
		{
			$notiTotal += count($pendingEvent);

			foreach ($pendingEvent as $value)
			{
				$event->load($value->eventid);
				$this->jsonarray['notifications']['global'][$ind]['id']          = $event->id;
				$this->jsonarray['notifications']['global'][$ind]['title']       = $event->title;
				$this->jsonarray['notifications']['global'][$ind]['location']    = $event->location;
				$format                                                          = ($this->config->get('eventshowampm')) ? JText::_('COM_COMMUNITY_DATE_FORMAT_LC2_12H') : JText::_('COM_COMMUNITY_DATE_FORMAT_LC2_24H');
				$this->jsonarray['notifications']['global'][$ind]['startdate']   = CTimeHelper::getFormattedTime($event->startdate, $format);
				$this->jsonarray['notifications']['global'][$ind]['enddate']     = CTimeHelper::getFormattedTime($event->enddate, $format);
				$this->jsonarray['notifications']['global'][$ind]['date']        = strtoupper(CEventHelper::formatStartDate($event, $this->config->get('eventdateformat')));
				$this->jsonarray['notifications']['global'][$ind]['avatar']      = ($event->avatar != '') ? $p_url . $event->avatar : JURI::base() . 'components/com_community/assets/event_thumb.png';
				$this->jsonarray['notifications']['global'][$ind]['past']        = (strtotime($event->enddate) < time()) ? 1 : 0;
				$this->jsonarray['notifications']['global'][$ind]['ongoing']     = (strtotime($event->startdate) <= time() and strtotime($event->enddate) > time()) ? 1 : 0;
				$this->jsonarray['notifications']['global'][$ind]['confirmed']   = $event->confirmedcount;
				$this->jsonarray['notifications']['global'][$ind]['type']        = 'events';
				$usr                                                             = $this->jomHelper->getUserDetail($value->invited_by);
				$this->jsonarray['notifications']['global'][$ind]['notif_title'] = strip_tags(JText::sprintf('COM_COMMUNITY_EVENTS_INVITED_NOTIFICATION', $usr->name, $event->title));
				$ind++;
			}
		}

		// Getting pending group request
		$pendingGroup   = $groupModel->getGroupInvites($this->IJUserID);
		$group           = JTable::getInstance('Group', 'CTable');
		$groupNotiTotal = 0;

		if (is_array($pendingGroup))
		{
			$groupNotiTotal += count($pendingGroup);

			foreach ($pendingGroup as $value)
			{
				$group->load($value->groupid);
				$this->jsonarray['notifications']['global'][$ind]['id']          = $group->id;
				$this->jsonarray['notifications']['global'][$ind]['title']       = $group->name;
				$this->jsonarray['notifications']['global'][$ind]['description'] = strip_tags($group->description);
				$this->jsonarray['notifications']['global'][$ind]['avatar']      = ($group->avatar == "") ? JURI::base() . 'components/com_community/assets/group.png' : $p_url . $group->avatar;
				$this->jsonarray['notifications']['global'][$ind]['members']     = intval($group->membercount);
				$this->jsonarray['notifications']['global'][$ind]['walls']       = intval($group->wallcount);
				$this->jsonarray['notifications']['global'][$ind]['discussions'] = intval($group->discusscount);
				$this->jsonarray['notifications']['global'][$ind]['type']        = 'groups';
				$usr                                                             = $this->jomHelper->getUserDetail($value->creator);
				$this->jsonarray['notifications']['global'][$ind]['notif_title'] = strip_tags(JText::sprintf('COM_COMMUNITY_GROUPS_INVITED_NOTIFICATION', $usr->name, $group->name));
				$ind++;
			}
		}

		// Geting pending private group join request
		// Find Users Groups Admin
		$allGroups = $groupModel->getAdminGroups($this->IJUserID, COMMUNITY_PRIVATE_GROUP);

		if (is_array($allGroups))
		{
			foreach ($allGroups as $value)
			{
				$group->load($value->id);
				$members = $groupModel->getMembers($group->id, 0, false);

				if (!empty($members))
				{
					foreach ($members as $member)
					{
						$this->jsonarray['notifications']['global'][$ind]['id']          = $group->id;
						$this->jsonarray['notifications']['global'][$ind]['title']       = $group->name;
						$this->jsonarray['notifications']['global'][$ind]['description'] = strip_tags($group->description);
						$this->jsonarray['notifications']['global'][$ind]['avatar']      = ($group->avatar == "") ? JURI::base() . 'components/com_community/assets/group.png' : $p_url . $group->avatar;
						$this->jsonarray['notifications']['global'][$ind]['members']     = intval($group->membercount);
						$this->jsonarray['notifications']['global'][$ind]['walls']       = intval($group->wallcount);
						$this->jsonarray['notifications']['global'][$ind]['discussions'] = intval($group->discusscount);
						$this->jsonarray['notifications']['global'][$ind]['type']        = 'groups';
						$this->jsonarray['notifications']['global'][$ind]['notif_title'] = strip_tags(JText::sprintf('COM_COMMUNITY_GROUPS_REQUESTED_NOTIFICATION', $member->name, $group->name));
						$ind++;
					}
				}
			}
		}

		// Non require action notification
		CFactory::load('helpers', 'content');
		$notifCount        = 5;
		$notificationModel = CFactory::getModel('notification');
		$myParams           = $this->my->getParams();

		$notifications = $notificationModel->getNotification($this->IJUserID, '0', $notifCount, $myParams->get('lastnotificationlist', ''));
		$photos         = JTable::getInstance('Photo', 'CTable');
		$videos         = JTable::getInstance('Video', 'CTable');
		$message        = JTable::getInstance('Message', 'CTable');

		if (!empty($notifications))
		{
			foreach ($notifications as $key => $value)
			{
				switch ($value->cmd_type)
				{
					case "notif_videos_tagging":
					case "notif_videos_like":
						$params  = new CParameter($value->params);
						$str     = preg_match_all('|videoid=(\d+)|', $params->get('url'), $match);
						$videoid = $match[1][0];
						$str     = preg_match_all('|groupid=(\d+)|', $params->get('url'), $match);
						$groupid = $match[1][0];
						$videos->load($videoid);

						$video_file = $videos->path;
						$p_url      = JURI::root();

						if ($videos->type == 'file')
						{
							$ext = JFile::getExt($videos->path);

							if ($ext == 'mov' && file_exists(JPATH_SITE . '/' . $videos->path))
							{
								$video_file = JURI::root() . $videos->path;
							}
							else
							{
								$lastpos = strrpos($videos->path, '.');
								$vname   = substr($videos->path, 0, $lastpos);

								if ($videos->storage == 's3')
								{
									$s3BucketPath = $this->config->get('storages3bucket');
									if (!empty ($s3BucketPath))
										$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
								}

								$video_file = $p_url . $vname . ".mp4";
							}
						}

						$this->jsonarray['notifications']['global'][$ind]['id']           = $videos->id;
						$this->jsonarray['notifications']['global'][$ind]['caption']      = $videos->title;
						$this->jsonarray['notifications']['global'][$ind]['thumb']        = ($videos->thumb) ? $p_url . $videos->thumb : JURI::base() . 'components/com_community/assets/video_thumb.png';
						$this->jsonarray['notifications']['global'][$ind]['url']          = $video_file;
						$this->jsonarray['notifications']['global'][$ind]['description']  = $videos->description;
						$this->jsonarray['notifications']['global'][$ind]['date']         = $this->jomHelper->timeLapse($this->jomHelper->getDate($videos->created));
						$this->jsonarray['notifications']['global'][$ind]['location']     = $videos->location;
						$this->jsonarray['notifications']['global'][$ind]['permissions']  = $videos->permissions;
						$this->jsonarray['notifications']['global'][$ind]['categoryId']   = $videos->category_id;
						$usr                                                              = $this->jomHelper->getUserDetail($value->actor);
						$this->jsonarray['notifications']['global'][$ind]['user_id']      = $usr->id;
						$this->jsonarray['notifications']['global'][$ind]['user_name']    = $usr->name;
						$this->jsonarray['notifications']['global'][$ind]['user_avatar']  = $usr->avatar;
						$this->jsonarray['notifications']['global'][$ind]['user_profile'] = $usr->profile;

						// Likes
						$likes                                                        = $this->jomHelper->getLikes('videos', $videos->id, $this->IJUserID);
						$this->jsonarray['notifications']['global'][$ind]['likes']    = $likes->likes;
						$this->jsonarray['notifications']['global'][$ind]['dislikes'] = $likes->dislikes;
						$this->jsonarray['notifications']['global'][$ind]['liked']    = $likes->liked;
						$this->jsonarray['notifications']['global'][$ind]['disliked'] = $likes->disliked;

						// Comments
						$count                                                             = $this->jomHelper->getCommentCount($videos->id, 'videos');
						$this->jsonarray['notifications']['global'][$ind]['commentCount']  = $count;
						$this->jsonarray['notifications']['global'][$ind]['deleteAllowed'] = intval(($this->IJUserID == $video->creator or COwnerHelper::isCommunityAdmin($this->IJUserID)));

						if (SHARE_VIDEOS)
						{
							$this->jsonarray['notifications']['global'][$ind]['shareLink'] = JURI::base() . "index.php?option=com_community&view=videos&task=video&userid={$video->creator}&videoid={$video->id}";
						}

						$query = "SELECT count(id)
								FROM #__community_videos_tag
								WHERE `videoid`={$videos->id}";
						$this->db->setQuery($query);
						$count                                                           = $this->db->loadResult();
						$this->jsonarray['notifications']['global'][$ind]['tags']        = $count;
						$this->jsonarray['notifications']['global'][$ind]['type']        = 'video';
						$srch                                                            = array('{actor}', '{video}');
						$rplc                                                            = array($usr->name, $params->get('video'));
						$this->jsonarray['notifications']['global'][$ind]['notif_title'] = str_replace($srch, $rplc, $value->content);
						$ind++;
						break;

					case "notif_photos_tagging":
					case "notif_photos_like":
						$params  = new CParameter($value->params);
						$str     = preg_match_all('|photoid=(\d+)|', $params->get('url'), $match);
						$photoid = $match[1][0];
						$str     = preg_match_all('|groupid=(\d+)|', $params->get('url'), $match);
						$groupid = $match[1][0];
						$str     = preg_match_all('|albumid=(\d+)|', $params->get('url'), $match);
						$albumid = $match[1][0];
						$photos->load($photoid);
						$this->jsonarray['notifications']['global'][$ind]['id']      = $photos->id;
						$this->jsonarray['notifications']['global'][$ind]['caption'] = $photos->caption;

						$p_url = JURI::base();

						if ($photo->storage == 's3')
						{
							$s3BucketPath = $this->config->get('storages3bucket');
							if (!empty ($s3BucketPath))
								$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
						}
						else
						{
							if (!file_exists(JPATH_SITE . '/' . $photos->image))
								$photos->image = $photos->original;
						}

						$this->jsonarray['notifications']['global'][$ind]['thumb'] = $p_url . $photos->thumbnail;
						$this->jsonarray['notifications']['global'][$ind]['url']   = $p_url . $photos->image;

						if (SHARE_PHOTOS == 1)
						{
							$this->jsonarray['notifications']['global'][$ind]['shareLink'] = JURI::base() . "index.php?option=com_community&view=photos&task=photo&userid={$photos->creator}&albumid={$albumid}#photoid={$photoid}";
						}

						// Likes
						$likes                                                        = $this->jomHelper->getLikes('photo', $photoid, $this->IJUserID);
						$this->jsonarray['notifications']['global'][$ind]['likes']    = $likes->likes;
						$this->jsonarray['notifications']['global'][$ind]['dislikes'] = $likes->dislikes;
						$this->jsonarray['notifications']['global'][$ind]['liked']    = $likes->liked;
						$this->jsonarray['notifications']['global'][$ind]['disliked'] = $likes->disliked;

						// Comments
						$count                                                            = $this->jomHelper->getCommentCount($photoid, 'photos');
						$this->jsonarray['notifications']['global'][$ind]['commentCount'] = $count;

						$query = "SELECT count(id)
								FROM #__community_photos_tag
								WHERE `photoid`={$photoid}";
						$this->db->setQuery($query);
						$count                                                           = $this->db->loadResult();
						$this->jsonarray['notifications']['global'][$ind]['tags']        = $count;
						$usr                                                             = $this->jomHelper->getUserDetail($value->actor);
						$srch                                                            = array('{actor}', '{photo}');
						$rplc                                                            = array($usr->name, $params->get('photo'));
						$this->jsonarray['notifications']['global'][$ind]['notif_title'] = str_replace($srch, $rplc, $value->content);
						$this->jsonarray['notifications']['global'][$ind]['type']        = 'photo';
						$ind++;
						break;

					case "notif_profile_like":
					case "notif_profile_stream_like":
					case "notif_friends_create_connection":
					case "notif_friends_request_connection":
					case "notif_profile_status_update":
						$usr                                                              = $this->jomHelper->getUserDetail($value->actor);
						$search                                                           = array('{actor}', '{friend}', '{stream}');
						$replace                                                          = array($usr->name, $usr->name, JText::_('COM_COMMUNITY_SINGULAR_STREAM'));
						$this->jsonarray['notifications']['global'][$ind]['notif_title']  = str_replace($search, $replace, $value->content);
						$this->jsonarray['notifications']['global'][$ind]['type']         = 'profile';
						$this->jsonarray['notifications']['global'][$ind]['user_id']      = $usr->id;
						$this->jsonarray['notifications']['global'][$ind]['user_name']    = $usr->name;
						$this->jsonarray['notifications']['global'][$ind]['user_avatar']  = $usr->avatar;
						$this->jsonarray['notifications']['global'][$ind]['user_profile'] = $usr->profile;
						$ind++;
						break;

					case "notif_inbox_create_message":
						$usr                                                             = $this->jomHelper->getUserDetail($value->actor);
						$search                                                          = array('{actor}', '{msg}');
						$replace                                                         = array($usr->name, 'private message');
						$this->jsonarray['notifications']['global'][$ind]['notif_title'] = str_replace($search, $replace, $value->content);
						$this->jsonarray['notifications']['global'][$ind]['type']        = 'message';

						$params    = new CParameter($value->params);
						$str       = preg_match_all('|msgid=(\d+)|', $params->get('url'), $match);
						$messageid = $match[1][0];
						$message   = $inboxModel->getMessage($messageid);

						$this->jsonarray['notifications']['global'][$ind]['id']       = $message->id;
						$this->jsonarray['notifications']['global'][$ind]['parent']   = $message->parent;
						$this->jsonarray['notifications']['global'][$ind]['subject']  = $message->subject;
						$format                                                       = JText::_('COM_COMMUNITY_DATE_FORMAT_LC2_24H');
						$timezone                                                     = JFactory::getConfig()->get('offset');
						$dtz                                                          = new DateTimeZone($timezone);
						$dt                                                           = new DateTime("now", $dtz);
						$offset                                                       = timezone_offset_get($dtz, $dt) / 3600;
						$date                                                         = CTimeHelper::getFormattedUTC($message->posted_on, $offset);
						$date                                                         = CTimeHelper::getFormattedTime($date, $format);
						$this->jsonarray['notifications']['global'][$ind]['date']     = $date;
						$this->jsonarray['notifications']['global'][$ind]['outgoing'] = 0;
						$this->jsonarray['notifications']['global'][$ind]['read']     = 0;
						$usr                                                          = $this->jomHelper->getUserDetail($value->actor);

						$this->jsonarray['notifications']['global'][$ind]['user_id']      = $usr->id;
						$this->jsonarray['notifications']['global'][$ind]['user_name']    = $usr->name;
						$this->jsonarray['notifications']['global'][$ind]['user_avatar']  = $usr->avatar;
						$this->jsonarray['notifications']['global'][$ind]['user_profile'] = $usr->profile;
						$ind++;
						break;

					case "notif_groups_member_join":
					case "notif_groups_wall_create":
					case "notif_photos_reply_wall":
					case "notif_photos_submit_wall":
					case "notif_profile_activity_add_comment":
					case "notif_profile_activity_reply_comment":
					default:
						$usr                                                              = $this->jomHelper->getUserDetail($value->actor);
						$this->jsonarray['notifications']['global'][$ind]['notif_title']  = str_replace('{actor}', $usr->name, $value->content);
						$this->jsonarray['notifications']['global'][$ind]['type']         = 'profile';
						$this->jsonarray['notifications']['global'][$ind]['user_id']      = $usr->id;
						$this->jsonarray['notifications']['global'][$ind]['user_name']    = $usr->name;
						$this->jsonarray['notifications']['global'][$ind]['user_avatar']  = $usr->avatar;
						$this->jsonarray['notifications']['global'][$ind]['user_profile'] = $usr->profile;
						$ind++;
						break;
				}
			}
		}

		// Update the last notification viewing to user params
		$date  = JFactory::getDate();
		$myParams->set('lastnotificationlist', $date->toMySQL());
		$this->my->save('params');

		// Update notification counter
		return $this->jsonarray;
	}

	/**
	 * uses    function to get activities
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"userPrivacy",
	 *        "taskData":{
	 *            "sessionID":"sessionID",
	 *            "form":"0/1"(0=form post, 1=get the form)
	 *        }
	 *    }
	 * @return some value
	 */
	public function preferences()
	{
		if (IJReq::getTaskData('form', 0, 'int'))
		{
			return $this->getPreferences();
		}
		else
		{
			return $this->setPreferences();
		}
	}

	/**
	 * used to get the form for user privacy settings
	 *
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	private function getPreferences()
	{
		CFactory::setActiveProfile();
		$params  = $this->my->getParams();

		$query = "SELECT `jomsocial_params`
				FROM #__ijoomeradv_users
				WHERE `userid`={$this->IJUserID}";
		$this->db->setQuery($query);
		$iparams  = $this->db->loadResult();
		$ijparams = new CParameter($iparams);

		$this->jsonarray['code'] = 200;

		$privacyLevel = array(
			array("name" => JText::_('COM_COMMUNITY_PRIVACY_PUBLIC'), "value" => 0),
			array("name" => JText::_('COM_COMMUNITY_PRIVACY_SITE_MEMBERS'), "value" => 20),
			array("name" => JText::_('COM_COMMUNITY_PRIVACY_FRIENDS'), "value" => 30)
		);

		$privacyLevel1 = array(
			array("name" => JText::_('COM_COMMUNITY_PRIVACY_PUBLIC'), "value" => 0),
			array("name" => JText::_('COM_COMMUNITY_PRIVACY_SITE_MEMBERS'), "value" => 20),
			array("name" => JText::_('COM_COMMUNITY_PRIVACY_FRIENDS'), "value" => 30),
			array("name" => JText::_('COM_COMMUNITY_PRIVACY_ME'), "value" => 40)
		);

		$general = array(
			array(
				'title'   => JText::_('COM_COMMUNITY_PROFILE_LIKE_ENABLE'),
				'name'    => 'profileLikes',
				'type'    => 'checkbox',
				'value'   => $params->get('profileLikes', true),
				'options' => null
			),
			array(
				'title'   => JText::_('COM_COMMUNITY_PRIVACY_PROFILE_FIELD'),
				'name'    => 'privacyProfileView',
				'type'    => 'select',
				'value'   => $params->get('privacyProfileView'),
				'options' => $privacyLevel
			)
		);

		$i                                           = 0;
		$this->jsonarray['fields'][$i]['group_name'] = JText::_('COM_COMMUNITY_EDIT_PREFERENCES');

		foreach ($general as $key => $value)
		{
			$this->jsonarray['fields'][$i]['field'][] = $value;
		}

		$privacy = array(
			array('title'   => JText::_('COM_COMMUNITY_PRIVACY_FRIENDS'),
					'name'    => 'privacyFriendsView',
					'type'    => 'select',
					'value'   => $params->get('privacyFriendsView'),
					'options' => $privacyLevel1
			),
			array('title'   => JText::_('COM_COMMUNITY_PRIVACY_PHOTOS_FIELD'),
					'name'    => 'privacyPhotoView',
					'type'    => 'select',
					'value'   => $params->get('privacyPhotoView'),
					'options' => $privacyLevel1
			),
			array('title'   => JText::_('COM_COMMUNITY_PRIVACY_VIDEOS_FIELD'),
					'name'    => 'privacyVideoView',
					'type'    => 'select',
					'value'   => $params->get('privacyVideoView'),
					'options' => $privacyLevel1
			),
			array('title'   => JText::_('COM_COMMUNITY_PRIVACY_GROUPS_FIELD'),
					'name'    => 'privacyGroupsView',
					'type'    => 'select',
					'value'   => $params->get('privacyGroupsView'),
					'options' => $privacyLevel1
			)
		);

		foreach ($privacy as $key => $value)
		{
			$this->jsonarray['fields'][$i]['field'][] = $value;
		}

		$notification = array(
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONGROUP_PROFILE'),
					'name'    => null,
					'type'    => 'label',
					'value'   => null,
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PROFILE_ACTIVITYCOMMENT'),
					'name'    => array("etype_profile_activity_add_comment", "notif_profile_activity_add_comment", "pushnotif_profile_activity_add_comment"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_profile_activity_add_comment'), $params->get('notif_profile_activity_add_comment'), $ijparams->get('pushnotif_profile_activity_add_comment')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PROFILE_ACTIVITYREPLY'),
					'name'    => array("etype_profile_activity_reply_comment", "notif_profile_activity_reply_comment", "pushnotif_profile_activity_reply_comment"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_profile_activity_reply_comment'), $params->get('notif_profile_activity_reply_comment'), $ijparams->get('pushnotif_profile_activity_reply_comment')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PROFILE_STATUSUPDATE'),
					'name'    => array("etype_profile_status_update", "notif_profile_status_update", "pushnotif_profile_status_update"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_profile_status_update'), $params->get('notif_profile_status_update'), $ijparams->get('pushnotif_profile_status_update')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PROFILE_LIKE'),
					'name'    => array("etype_profile_like", "notif_profile_like", "pushnotif_profile_like"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_profile_like'), $params->get('notif_profile_like'), $ijparams->get('pushnotif_profile_like')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PROFILE_STREAM_LIKE'),
					'name'    => array("etype_profile_stream_like", "notif_profile_stream_like", "pushnotif_profile_stream_like"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_profile_stream_like'), $params->get('notif_profile_stream_like'), $ijparams->get('pushnotif_profile_stream_like')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_FRIENDS_INVITE'),
					'name'    => array("etype_friends_request_connection", "notif_friends_request_connection", "pushnotif_friends_request_connection"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_friends_request_connection'), $params->get('notif_friends_request_connection'), $ijparams->get('pushnotif_friends_request_connection')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_FRIENDS_CONNECTION'),
					'name'    => array("etype_friends_create_connection", "notif_friends_create_connection", "pushnotif_friends_create_connection"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_friends_create_connection'), $params->get('notif_friends_create_connection'), $ijparams->get('pushnotif_friends_create_connection')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_OTHERS_INBOXMSG'),
					'name'    => array("etype_inbox_create_message", "notif_inbox_create_message", "pushnotif_inbox_create_message"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_inbox_create_message'), $params->get('notif_inbox_create_message'), $ijparams->get('pushnotif_inbox_create_message')),
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONGROUP_GROUPS'),
					'name'    => null,
					'type'    => 'label',
					'value'   => null,
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_INVITE'),
					'name'    => array("etype_groups_invite", "notif_groups_invite", "pushnotif_groups_invite"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_invite'), $params->get('notif_groups_invite'), $ijparams->get('pushnotif_groups_invite')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_DISCUSSIONREPLY'),
					'name'    => array("etype_groups_discussion_reply", "notif_groups_discussion_reply", "pushnotif_groups_discussion_reply"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_discussion_reply'), $params->get('notif_groups_discussion_reply'), $ijparams->get('pushnotif_groups_discussion_reply')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_WALLUPDATE'),
					'name'    => array("etype_groups_wall_create", "notif_groups_wall_create", "pushnotif_groups_wall_create"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_wall_create'), $params->get('notif_groups_wall_create'), $ijparams->get('pushnotif_groups_wall_create')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_NEWDISCUSSION'),
					'name'    => array("etype_groups_create_discussion", "notif_groups_create_discussion", "pushnotif_groups_create_discussion"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_create_discussion'), $params->get('notif_groups_create_discussion'), $ijparams->get('pushnotif_groups_create_discussion')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_NEWBULLETIN'),
					'name'    => array("etype_groups_create_news", "notif_groups_create_news", "pushnotif_groups_create_news"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_create_news'), $params->get('notif_groups_create_news'), $ijparams->get('pushnotif_groups_create_news')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_NEWALBUM'),
					'name'    => array("etype_groups_create_album", "notif_groups_create_album", "pushnotif_groups_create_album"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_create_news'), $params->get('notif_groups_create_album'), $ijparams->get('pushnotif_groups_create_album')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_NEWVIDEO'),
					'name'    => array("etype_groups_create_video", "notif_groups_create_video", "pushnotif_groups_create_video"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_create_video'), $params->get('notif_groups_create_video'), $ijparams->get('pushnotif_groups_create_video')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_NEWEVENT'),
					'name'    => array("etype_groups_create_event", "notif_groups_create_event", "pushnotif_groups_create_event"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_create_event'), $params->get('notif_groups_create_event'), $ijparams->get('pushnotif_groups_create_event')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_MASSEMAIL'),
					'name'    => array("etype_groups_sendmail", "notif_groups_sendmail", "pushnotif_groups_sendmail"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_sendmail'), $params->get('notif_groups_sendmail'), $ijparams->get('pushnotif_groups_sendmail')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_NEWMEMBER'),
					'name'    => array("etype_groups_member_approved", "notif_groups_member_approved", "pushnotif_groups_member_approved"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_member_approved'), $params->get('notif_groups_member_approved'), $ijparams->get('pushnotif_groups_member_approved')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_NEWMEMBER_REQUEST'),
					'name'    => array("etype_groups_member_join", "notif_groups_member_join", "pushnotif_groups_member_join"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_member_join'), $params->get('notif_groups_member_join'), $ijparams->get('pushnotif_groups_member_join')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_CREATION_APPROVED'),
					'name'    => array("etype_groups_notify_creator", "notif_groups_notify_creator", "pushnotif_groups_notify_creator"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_notify_creator'), $params->get('notif_groups_notify_creator'), $ijparams->get('pushnotif_groups_notify_creator')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_GROUPS_DISCUSSION_NEWFILE'),
					'name'    => array("etype_groups_discussion_newfile", "notif_groups_discussion_newfile", "pushnotif_groups_discussion_newfile"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_groups_discussion_newfile'), $params->get('notif_groups_discussion_newfile'), $ijparams->get('pushnotif_groups_discussion_newfile')),
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONGROUP_EVENTS'),
					'name'    => null,
					'type'    => 'label',
					'value'   => null,
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_EVENTS_INVITATION'),
					'name'    => array("etype_events_invite", "notif_events_invite", "pushnotif_events_invite"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_events_invite'), $params->get('notif_events_invite'), $ijparams->get('pushnotif_events_invite')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_EVENTS_INVITATION_APPROVED'),
					'name'    => array("etype_events_invitation_approved", "notif_events_invitation_approved", "pushnotif_events_invitation_approved"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_events_invitation_approved'), $params->get('notif_events_invitation_approved'), $ijparams->get('pushnotif_events_invitation_approved')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_EVENTS_MASSEMAIL'),
					'name'    => array("etype_events_sendmail", "notif_events_sendmail", "pushnotif_events_sendmail"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_events_sendmail'), $params->get('notif_events_sendmail'), $ijparams->get('pushnotif_events_sendmail')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_EVENTS_CREATION_APPROVED'),
					'name'    => array("etype_event_notify_creator", "notif_event_notify_creator", "pushnotif_event_notify_creator"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_events_sendmail'), $params->get('notif_events_sendmail'), $ijparams->get('pushnotif_event_notify_creator')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_EVENTS_JOIN_REQUEST'),
					'name'    => array("etype_event_join_request", "notif_event_join_request", "pushnotif_event_join_request"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_event_join_request'), $params->get('notif_event_join_request'), $ijparams->get('pushnotif_event_join_request')),
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONGROUP_VIDEOS'),
					'name'    => null,
					'type'    => 'label',
					'value'   => null,
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_VIDEOS_WALLCOMMENT'),
					'name'    => array("etype_videos_submit_wall", "notif_videos_submit_wall", "pushnotif_videos_submit_wall"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_videos_submit_wall'), $params->get('notif_videos_submit_wall'), $ijparams->get('pushnotif_videos_submit_wall')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_VIDEOS_WALLCOMMENT_REPLY'),
					'name'    => array("etype_videos_reply_wall", "notif_videos_reply_wall", "pushnotif_videos_reply_wall"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_videos_reply_wall'), $params->get('notif_videos_reply_wall'), $ijparams->get('pushnotif_videos_reply_wall')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_VIDEOS_TAG'),
					'name'    => array("etype_videos_tagging", "notif_videos_tagging", "pushnotif_videos_tagging"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_videos_tagging'), $params->get('notif_videos_tagging'), $ijparams->get('pushnotif_videos_tagging')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_VIDEO_LIKE'),
					'name'    => array("etype_videos_like", "notif_videos_like", "pushnotif_videos_like"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_videos_like'), $params->get('notif_videos_like'), $ijparams->get('pushnotif_videos_like')),
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONGROUP_PHOTOS'),
					'name'    => null,
					'type'    => 'label',
					'value'   => null,
					'options' => null
			),

			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PHOTOS_WALLCOMMENT'),
					'name'    => array("etype_photos_submit_wall", "notif_photos_submit_wall", "pushnotif_photos_submit_wall"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_photos_submit_wall'), $params->get('notif_photos_submit_wall'), $ijparams->get('pushnotif_photos_submit_wall')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PHOTOS_WALLCOMMENT_REPLY'),
					'name'    => array("etype_photos_reply_wall", "notif_photos_reply_wall", "pushnotif_photos_reply_wall"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_photos_reply_wall'), $params->get('notif_photos_reply_wall'), $ijparams->get('pushnotif_photos_reply_wall')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PHOTOS_TAG'),
					'name'    => array("etype_photos_tagging", "notif_photos_tagging", "pushnotif_photos_tagging"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_photos_tagging'), $params->get('notif_photos_tagging'), $ijparams->get('pushnotif_photos_tagging')),
					'options' => null
			),
			array('title'   => JText::_('COM_COMMUNITY_NOTIFICATIONTYPE_PHOTOS_LIKE'),
					'name'    => array("etype_photos_like", "notif_photos_like", "pushnotif_photos_like"),
					'type'    => array("checkbox", "checkbox", "checkbox"),
					'value'   => array($params->get('etype_photos_like'), $params->get('notif_photos_like'), $ijparams->get('pushnotif_photos_like')),
					'options' => null
			)
		);

		$i++;
		$this->jsonarray['fields'][$i]['group_name'] = JText::_('COM_COMMUNITY_PROFILE_NOTIFICATIONS');

		foreach ($notification as $key => $value)
		{
			$this->jsonarray['fields'][$i]['field'][] = $value;
		}

		return $this->jsonarray;
	}

	/**
	 * uses to set the user privacy settings
	 *
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	private function setPreferences()
	{
		$formData = IJReq::getTaskData('formData');
		$params    = $this->my->getParams();

		foreach ($formData as $key => $value)
		{
			if (strpos($value->name, 'pushnotif') !== false)
			{
				$push[$value->name] = $value->value;
			}
			else
			{
				$params->set($value->name, $value->value);
			}
		}

		// Save params
		$this->my->save('params');

		$push = json_encode($push);

		$query = "UPDATE #__ijoomeradv_users
				SET `jomsocial_params`='{$push}'
				WHERE `userid`={$this->IJUserID}";
		$this->db->setQuery($query);

		if ($this->db->Query())
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500);

			return false;
		}
	}

	/**
	 * function for timeLapse
	 *
	 * @param   date  $date  date
	 *
	 * @return  $lapse
	 */
	private function timeLapse($date)
	{
		jimport('joomla.utilities.date');
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
				$lapse = JText::_("COM_COMMUNITY_ACTIVITIES_MOMENT_AGO");
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
	 * @param   integer  $off  offset
	 *
	 * @return  $date
	 */
	private function getDate($str = '', $off = 0)
	{
		$extraOffset = $this->config->get('daylightsavingoffset');

		// Convert to utc time first.
		$utc_date = new CDate($str);
		$date     = new CDate($utc_date->toUnix() + $off * 3600);

		$cMy = CFactory::getUser();

		// J1.6 returns timezone as string, not integer offset.
		if (method_exists('JDate', 'getOffsetFromGMT'))
		{
			$systemOffset = new CDate('now', $this->mainframe->getCfg('offset'));
			$systemOffset = $systemOffset->getOffsetFromGMT(true);
		}
		else
		{
			$systemOffset = $this->mainframe->getCfg('offset');
		}

		if (!$this->my->id)
		{
			$date->setOffset($systemOffset + $extraOffset);
		}
		else
		{
			if (!empty($this->my->params))
			{
				$pos = JString::strpos($this->my->params, 'timezone');

				$offset = $systemOffset + $extraOffset;

				if ($pos === false)
				{
					$offset = $systemOffset + $extraOffset;
				}
				else
				{
					$offset = $this->my->getParam('timezone', -100);

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
	 * uses    function to get activities
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"profileTypes",
	 *        "taskData":{
	 *        }
	 *    }
	 * @return array  jsonarray
	 */
	public function profileTypes()
	{
		$profiles = array();
		$multi    = $this->config->get('profile_multiprofile');

		if ($multi > 0)
		{
			$query = "SELECT *
					FROM #__community_profiles as cp
					WHERE cp.published=1";
			$this->db->setQuery($query);
			$profiles = $this->db->loadObjectList();
		}

		$this->jsonarray['code'] = 200;
		$inc                     = 0;

		if (count($profiles) > 0)
		{
			foreach ($profiles as $profile)
			{
				$this->jsonarray['profiletype'][$inc]['id']          = $profile->id;
				$this->jsonarray['profiletype'][$inc]['name']        = $profile->name;
				$this->jsonarray['profiletype'][$inc]['description'] = $profile->description;
				$inc++;
			}
		}
		else
		{
			$this->jsonarray['profiletype'][$inc]['id']          = '0';
			$this->jsonarray['profiletype'][$inc]['name']        = JText::_('DEFAULT');
			$this->jsonarray['profiletype'][$inc]['description'] = '';
		}

		return $this->jsonarray;
	}

	/**
	 * uses    function to get terms and condition value
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"getTermsNCondition"
	 *    }
	 * @return array  jsonarray
	 */
	public function getTermsNCondition()
	{
		$jsonarray['code']            = 200;
		$jsonarray['termsNcondition'] = $this->config->get('registrationTerms');

		return $jsonarray;
	}

	/**
	 * uses    function to get advance search
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"user",
	 *        "extTask":"advanceSearch",
	 *        "taskData":{
	 *            "form":"form" // 0: (default) to post data, 1: to get form
	 *            "pageNO":"pageNO",
	 *            "formData":[
	 *                {
	 *                    "fieldid":"FIELD_ID",
	 *                    "field":"FIELD_CODE",
	 *                    "condition":"FIELD_CONDITION",
	 *                    "fieldType":"FIELD_TYPE",
	 *                    "value":"FIELD_VALUE" // if range=1 then value will be indexed array containing both value
	 *                }
	 *            ]
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function advanceSearch()
	{
		$form = IJReq::getTaskData('form', 0, 'int');

		if ($this->my->id == 0 && !$this->config->get('guestsearch'))
		{
			IJReq::setResponse(706);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// If form=1 passed then return advance search form
		if ($form)
		{
			// Condition criteria for text, textarea, time, lablel
			$textcondition = array(
				array("name" => JText::_('COM_COMMUNITY_CONTAIN'), "value" => "contain", "range" => 0, "valuetype" => "text"),
				array("name" => JText::_('COM_COMMUNITY_EQUAL'), "value" => "equal", "range" => 0, "valuetype" => "text"),
				array("name" => JText::_('COM_COMMUNITY_NOT_EQUAL'), "value" => "notequal", "range" => 0, "valuetype" => "text")
			);

			// Condition criteria for select, selectlist, multiselect, radio
			$selectcondition = array(
				array("name" => JText::_('COM_COMMUNITY_EQUAL'), "value" => "equal", "range" => 0, "valuetype" => "select"),
				array("name" => JText::_('COM_COMMUNITY_NOT_EQUAL'), "value" => "notequal", "range" => 0, "valuetype" => "select")
			);

			// Condition criteria for checkbox
			$checkboxcondition = array(
				array("name" => JText::_('COM_COMMUNITY_EQUAL'), "value" => "equal", "range" => 0, "valuetype" => "checkbox"),
				array("name" => JText::_('COM_COMMUNITY_NOT_EQUAL'), "value" => "notequal", "range" => 0, "valuetype" => "checkbox")
			);

			// Condition criteria for date
			$datecondition = array(
				array("name" => JText::_('COM_COMMUNITY_BETWEEN'), "value" => "between", "range" => 1, "valuetype" => "text"),
				array("name" => JText::_('COM_COMMUNITY_EQUAL'), "value" => "equal", "range" => 0, "valuetype" => "text"),
				array("name" => JText::_('COM_COMMUNITY_NOT_EQUAL'), "value" => "notequal", "range" => 0, "valuetype" => "text"),
				array("name" => JText::_('COM_COMMUNITY_LESS_THAN_OR_EQUAL'), "value" => "lessthanorequal", "range" => 0, "valuetype" => "text"),
				array("name" => JText::_('COM_COMMUNITY_GREATER_THAN_OR_EQUAL'), "value" => "greaterthanorequal", "range" => 0, "valuetype" => "text")
			);

			$query = "SELECT `id`, `type`, `name`, `options`, `fieldcode`
					FROM `#__community_fields`
					WHERE `published`=1
					AND `visible`=1
					AND `searchable`=1
					ORDER BY `ordering`";
			$this->db->setQuery($query);
			$result = $this->db->loadobjectList();

			foreach ($result as $key => &$value)
			{
				if (!empty($value->options))
				{
					$options        = explode("\n", $value->options);
					$value->options = array();

					foreach ($options as $k => $option)
					{
						$value->options[] = array(
							'name'  => $option,
							'value' => $option
						);
					}
				}

				switch ($value->type)
				{
					case 'group':
						unset($value->options);
						unset($value->fieldcode);
						break;

					case 'date':
					case 'birthdate':
						$value->condition = $datecondition;
						break;

					case 'select':
					case 'singleselect':
					case 'list':
					case 'radio':
						if ($value->type == 'list')
						{
							$value->type = 'select';
						}

						$value->condition = $selectcondition;
						break;

					case 'checkbox':
						$value->condition = $checkboxcondition;
						break;

					case 'country':
						unset($value->options);
					case 'text':
					case 'textarea':
					case 'url':
					case 'time':
					case 'lable':
					default:
						$value->condition = $textcondition;
				}
			}

			$this->jsonarray['code']   = 200;
			$this->jsonarray['fields'] = $result;

			$count = count($result);

			$obj                               = new stdClass;
			$obj->id                           = 93;
			$obj->type                         = 'group';
			$obj->name                         = 'Name';
			$this->jsonarray['fields'][$count] = $obj;
			$count++;

			$obj                               = new stdClass;
			$obj->id                           = 94;
			$obj->type                         = 'text';
			$obj->name                         = 'Name';
			$obj->options                      = null;
			$obj->fieldcode                    = 'username';
			$obj->condition                    = Array(Array(
				'name'      => JText::_('COM_COMMUNITY_CONTAIN'),
				'value'     => 'containa',
				'range'     => 0,
				'valuetype' => 'text'
			),

				Array(
					'name'      => JText::_('COM_COMMUNITY_EQUAL'),
					'value'     => 'equal',
					'range'     => 0,
					'valuetype' => 'text'
				),

				Array(
					'name'      => JText::_('COM_COMMUNITY_NOT_EQUAL'),
					'value'     => 'notequal',
					'range'     => 0,
					'valuetype' => 'text'
				)
			);
			$this->jsonarray['fields'][$count] = $obj;
			$count++;

			$obj                         = new stdClass;
			$obj->id                     = 95;
			$obj->type                   = 'text';
			$obj->name                   = 'E-mail';
			$obj->options                = null;
			$obj->fieldcode              = 'useremail';
			$obj->condition              = Array(
				Array(
					'name'      => JText::_('COM_COMMUNITY_EQUAL'),
					'value'     => 'equal',
					'range'     => 0,
					'valuetype' => 'text'
				)
			);
			$this->jsonarray['fields'][] = $obj;

			return $this->jsonarray;
		}

		// If form=0 passed then process posted data.
		$formData   = IJReq::getTaskData('formData');
		$pageNO     = IJReq::getTaskData('pageNO', 0, 'int');
		$operator   = IJReq::getTaskData('operator', 'and');
		$avatarOnly = IJReq::getTaskData('avatarOnly', 0, 'bool');
		$limit      = PAGE_MEMBER_LIMIT;

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$searchModel = CFactory::getModel('search');

		$query = $searchModel->_buildCustomQuery($formData, $operator, $avatarOnly);

		// Lets try temporary table here
		$tmptablename = 'tmpadv';
		$drop         = 'DROP TEMPORARY TABLE IF EXISTS ' . $tmptablename;
		$this->db->setQuery($drop);
		$this->db->query();

		$query = 'CREATE TEMPORARY TABLE ' . $tmptablename . ' ' . $query;
		$this->db->setQuery($query);
		$this->db->query();
		$total = $this->db->getAffectedRows();

		// Setting pagination object.
		$this->_pagination = new JPagination($total, $limitstart, $limit);

		$query = 'SELECT * FROM ' . $tmptablename;

		// @rule: Sorting if required.
		if (!empty($sorting))
		{
			$query .= $searchModel->_getSort($sorting);
		}

		// Execution of master query
		$query .= ' LIMIT ' . $startFrom . ',' . $limit;

		$this->db->setQuery($query);
		$results = $this->db->loadResultArray();

		if ($this->db->getErrorNum())
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (count($results) <= 0)
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $total;
		}

		foreach ($results as $key => $result)
		{
			$usr                                             = $this->jomHelper->getUserDetail($result);
			$this->jsonarray['member'][$key]['user_id']      = $usr->id;
			$this->jsonarray['member'][$key]['user_name']    = $usr->name;
			$this->jsonarray['member'][$key]['user_avatar']  = $usr->avatar;
			$this->jsonarray['member'][$key]['user_lat']     = $usr->latitude;
			$this->jsonarray['member'][$key]['user_long']    = $usr->longitude;
			$this->jsonarray['member'][$key]['user_online']  = $usr->online;
			$this->jsonarray['member'][$key]['user_profile'] = $usr->profile;
		}

		for ($i = 0, $inc = count($this->jsonarray['member']); $i < $inc; $i++)
		{
			for ($j = $i + 1, $inc = count($this->jsonarray['member']); $j < $inc; $j++)
			{
				$firstRecord  = $this->jsonarray['member'][$i];
				$secondRecord = $this->jsonarray['member'][$j];

				if ($firstRecord['online'] < $secondRecord['online'])
				{
					$this->jsonarray['member'][$i] = $secondRecord;
					$this->jsonarray['member'][$j] = $firstRecord;
				}
			}
		}

		return $this->jsonarray;
	}
}
