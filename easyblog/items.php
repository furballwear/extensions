<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  easyblog
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;
jimport('joomla.application.component.helper');

/**
 * class for items
 *
 * @package     IJoomer.Extensions
 * @subpackage  easyblog
 * @since       1.0
 */
class Items
{
	private $db;

	/**
	 * constructor
	 */
	public function __construct()
	{
		$this->db = JFactory::getDBO();
	}

	/**
	 * To fetch archive article list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"icms",
	 *        "extView":"articles",
	 *        "extTask":"archive",
	 *        "taskData":{
	 *            "pageNO":"pageno"
	 *        }
	 *    }
	 *
	 * @return  mixed items and total
	 */
	public function archive()
	{
		include_once JPATH_SITE . '/components/com_content/models/archive.php';
		$ContentModelArchive = new ContentModelArchive;
		$items               = $ContentModelArchive->getItems();

		$total = count($items);

		if ($total <= 0)
		{
			$jsonarray['code'] = 204;

			return $jsonarray;
		}

		return $this->getArticleList($items, $total);
	}

	/**
	 * To fetch archive article list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"icms",
	 *        "extView":"articles",
	 *        "extTask":"featured",
	 *        "taskData":{
	 *            "pageNO":"pageno"
	 *        }
	 *    }
	 *
	 * @return  mixed items and total
	 */
	public function featured()
	{
		JModel::addIncludePath(JPATH_SITE . '/components/com_content/models', 'ContentModel');
		$model = JModel::getInstance('Featured', 'ContentModel', array('ignore_request' => true));

		$appParams = JComponentHelper::getParams('com_content');
		$model->setState('params', $appParams);
		$model->setState('filter.frontpage', true);
		$model->setState('filter.access', (bool) (!$appParams->get('show_noauth')));

		$user = JFactory::getUser();

		if ((!$user->authorise('core.edit.state', 'com_content')) && (!$user->authorise('core.edit', 'com_content')))
		{
			// Filter on published for those who do not have edit or edit.state rights.
			$model->setState('filter.published', 1);
		}
		else
		{
			$model->setState('filter.published', array(0, 1, 2));
		}

		$items = $model->getItems();
		$total = count($items);

		if ($total <= 0)
		{
			$jsonarray['code'] = 204;

			return $jsonarray;
		}

		return $this->getArticleList($items, $total);
	}

	/**
	 * To fetch archive article list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"icms",
	 *        "extView":"articles",
	 *        "extTask":"search",
	 *        "taskData":{
	 *            "key":"" // search keyword
	 *        }
	 *    }
	 *
	 * @return  mixed items and total
	 */
	public function search()
	{
		$keyword = IJReq::getTaskData('key', '');

		JModel::addIncludePath(JPATH_SITE . '/components/com_content/models', 'ContentModel');
		$model     = JModel::getInstance('Articles', 'ContentModel', array('ignore_request' => true));
		$appParams = JComponentHelper::getParams('com_content');

		// Set search type
		$appParams->set('filter_field', 'title');

		$model->setState('params', $appParams);
		$model->setState('filter.frontpage', true);
		$model->setState('filter.access', (bool) (!$appParams->get('show_noauth')));

		$user = JFactory::getUser();

		if ((!$user->authorise('core.edit.state', 'com_content')) && (!$user->authorise('core.edit', 'com_content')))
		{
			// Filter on published for those who do not have edit or edit.state rights.
			$model->setState('filter.published', 1);
		}
		else
		{
			$model->setState('filter.published', array(0, 1, 2));
		}

		// Set search keyword
		$model->setState('list.filter', $keyword);

		$items = $model->getItems();
		$total = count($items);

		if ($total <= 0)
		{
			$jsonarray['code'] = 204;

			return $jsonarray;
		}

		return $this->getArticleList($items, $total);
	}

	/**
	 * this function provide welformed list of articles
	 *
	 * @param   object   $articles     Object of articles
	 * @param   int      $total        Total article counts
	 * @param   boolean  $applayLimit  applayLimit
	 *
	 * @return  array   jsonarray
	 */
	public function getArticleList($articles, $total, $applayLimit = true)
	{
		$jsonarray['code']      = 200;
		$jsonarray['total']     = $total;
		$jsonarray['pageLimit'] = ICMS_ARTICLE_LIMIT;

		if ($applayLimit)
		{
			$startno = IJReq::getTaskData('pageNO', 1, 'int');
			$limit   = ICMS_ARTICLE_LIMIT;
			$startno = ($startno == 1 || $startno == 0) ? 0 : ($limit * ($startno - 1));
			$cout    = ($startno + $limit >= count($articles)) ? count($articles) : $startno + $limit;
		}
		else
		{
			$startno = 0;
			$cout    = count($articles);
		}

		for ($inc = $startno, $i = 0; $inc < $cout; $inc++, $i++)
		{
			if ($articles[$inc])
			{
				$jsonarray['articles'][$i]['articleid'] = $articles[$inc]->id;
				$jsonarray['articles'][$i]['title']     = $articles[$inc]->title;
				$jsonarray['articles'][$i]['introtext'] = strip_tags($articles[$inc]->introtext);

				if ($articles[$inc]->images)
				{
					$articlesimages = json_decode($articles[$inc]->images);

					if ($articlesimages->image_intro)
					{
						$jsonarray['articles'][$i]['image'] = $this->formatImageUri($articlesimages->image_intro);
					}
					else
					{
						$images = array();
						preg_match_all('/(src)=("[^"]*")/i', $articles[$inc]->introtext, $images);
						$imgpath                            = str_replace(array('src="', '"'), "", $images[0]);
						$jsonarray['articles'][$i]['image'] = ($imgpath) ? $this->formatImageUri($imgpath[0]) : '';
					}
				}
				else
				{
					$images = array();
					preg_match_all('/(src)=("[^"]*")/i', $articles[$inc]->introtext, $images);
					$imgpath                            = str_replace(array('src="', '"'), "", $images[0]);
					$jsonarray['articles'][$i]['image'] = ($imgpath) ? $this->formatImageUri($imgpath[0]) : '';
				}

				$jsonarray['articles'][$i]['created']       = $articles[$inc]->created;
				$jsonarray['articles'][$i]['created_by_id'] = $articles[$inc]->created_by;
				$jsonarray['articles'][$i]['author']        = $articles[$inc]->author;
				$jsonarray['articles'][$i]['catid']         = $articles[$inc]->catid;
				$jsonarray['articles'][$i]['parent_id']     = $articles[$inc]->parent_id;
				$jsonarray['articles'][$i]['parent_title']  = $articles[$inc]->parent_title;
				$jsonarray['articles'][$i]['shareLink']     = JURI::base() . "index.php?option=com_content&view=article&id={$articles[$inc]->id}:{$articles[$inc]->alias}&catid={$articles[$inc]->catid}";
			}
		}

		return $jsonarray;
	}

	/**
	 * To fetch archive article list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"icms",
	 *        "extView":"articles",
	 *        "extTask":"singleArticle",
	 *        "taskData":""
	 *    }
	 *
	 * @return  integer $id
	 */
	public function singleArticle()
	{
		$id = ICMS_SINGLE_ARTICLE_ID;

		return $this->getarticleDetail($id);
	}

	/**
	 * To fetch archive article list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"icms",
	 *        "extView":"articles",
	 *        "extTask":"articleDetail",
	 *        "taskData":""
	 *    }
	 *
	 * @return  integer $id
	 */
	public function articleDetail()
	{
		$id = IJReq::getTaskData('id', null, 'int');

		return $this->getarticleDetail($id);
	}

	/**
	 *  Function for get article detail
	 *
	 * @param   integer  $id  article id
	 *
	 * @return  array   $jsonarray
	 */
	private function getarticleDetail($id)
	{
		include_once JPATH_SITE . '/components/com_content/models/article.php';
		$ContentModelArticle = new ContentModelArticle;
		$items               = $ContentModelArticle->getItem($id);

		if ($items->params->get('access-view'))
		{
			$result = array();
			preg_match_all('/<img[^>]+>/i', $items->introtext, $result);

			foreach ($result[0] as $key => $value)
			{
				$imgpath = array();
				preg_match_all('/src="[^"]+"/', $value, $imgpath);
				$imgpath          = str_replace(array('src="', '"'), "", $imgpath[0][0]);
				$imgpath          = $this->formatImageUri($imgpath);
				$items->introtext = str_replace($value, '<img src="' . $imgpath . '">', $items->introtext);
			}

			preg_match_all('/<img[^>]+>/i', $items->fulltext, $result);

			foreach ($result[0] as $key => $value)
			{
				preg_match_all('/src="[^"]+"/', $value, $imgpath);
				$imgpath         = str_replace(array('src="', '"'), "", $imgpath[0][0]);
				$imgpath         = $this->formatImageUri($imgpath);
				$items->fulltext = str_replace($value, '<img src="' . $imgpath . '">', $items->fulltext);
			}

			$anchors = array();
			preg_match_all('#<a\s+href=[\'"]([^\'"]+)[\'"]\s*(?:title=[\'"]([^\'"]+)[\'"])?\s*>((?:(?!</a>).)*)</a>#i', $items->introtext, $anchors);

			foreach ($anchors[0] as $key => $value)
			{
				$hrefPath = array();
				preg_match_all('/href="[^"]+"/', $value, $hrefPath);

				if ($hrefPath[0])
				{
					$match = array();
					preg_match('/href="(.+)"/', $hrefPath[0][0], $match);

					if (!parse_url($match[1], PHP_URL_HOST))
					{
						$link = JUri::base() . $match[1];
					}
					elseif (parse_url($match[1], PHP_URL_HOST) == JUri::base())
					{
						$link = $match[1];
					}
					else
					{
						$link = null;
					}

					if ($link)
					{
						$uri    = JURI::getInstance($link);
						$router = JApplication::getRouter();
						$result = $router->parse($uri);

						if ($result['option'] == 'com_content')
						{
							$view   = $result['view'];
							$id     = (array_key_exists('id', $result)) ? '&id=' . $result['id'] : '';
							$Itemid = $result['Itemid'];

							$text             = $anchors[3][$key];
							$customlink       = '<a href="' . JURI::base() . 'index.php?option=com_content&view=' . $view . $id . '">' . $text . '</a>';
							$items->introtext = str_replace($value, $customlink, $items->introtext);
						}
					}
				}
			}

			preg_match_all('#<a\s+href=[\'"]([^\'"]+)[\'"]\s*(?:title=[\'"]([^\'"]+)[\'"])?\s*>((?:(?!</a>).)*)</a>#i', $items->fulltext, $anchors);

			foreach ($anchors[0] as $key => $value)
			{
				preg_match_all('/href="[^"]+"/', $value, $hrefPath);

				if ($hrefPath[0])
				{
					preg_match('/href="(.+)"/', $hrefPath[0][0], $match);

					if (!parse_url($match[1], PHP_URL_HOST))
					{
						$link = JUri::base() . $match[1];
					}
					elseif (parse_url($match[1], PHP_URL_HOST) == JUri::base())
					{
						$link = $match[1];
					}
					else
					{
						$link = null;
					}

					if ($link)
					{
						$uri    = JURI::getInstance($link);
						$router = JApplication::getRouter();
						$result = $router->parse($uri);

						if ($result['option'] == 'com_content')
						{
							$view = $result['view'];
							$id   = (array_key_exists('id', $result)) ? '&id=' . $result['id'] : '';

							$text            = $anchors[3][$key];
							$customlink      = '<a href="' . JURI::base() . 'index.php?option=com_content&view=' . $view . $id . '">' . $text . '</a>';
							$items->fulltext = str_replace($value, $customlink, $items->fulltext);
						}
					}
				}
			}

			$jsonarray['code']                        = 200;
			$jsonarray['article']['id']               = $items->id;
			$jsonarray['article']['title']            = $items->title;
			$jsonarray['article']['alias']            = $items->alias;
			$jsonarray['article']['introtext']        = $items->introtext;
			$jsonarray['article']['fulltext']         = $items->introtext . $items->fulltext;
			$jsonarray['article']['catid']            = $items->catid;
			$jsonarray['article']['category_title']   = $items->category_title;
			$jsonarray['article']['category_alias']   = $items->category_alias;
			$jsonarray['article']['parent_id']        = $items->parent_id;
			$jsonarray['article']['parent_title']     = $items->parent_title;
			$jsonarray['article']['created_by_id']    = $items->created_by;
			$jsonarray['article']['created_by_alias'] = $items->created_by_alias;
			$jsonarray['article']['publish_up']       = $items->publish_up;
			$jsonarray['article']['publish_down']     = $items->publish_down;

			$itemsimages = json_decode($items->images);

			if (isset($itemsimages->image_intro))
			{
				$itemsimages->image_intro = $this->formatImageUri($itemsimages->image_intro);
			}

			if (isset($itemsimages->image_fulltext))
			{
				$itemsimages->image_fulltext = $this->formatImageUri($itemsimages->image_fulltext);
			}

			$jsonarray['article']['image_intro']    = (isset($itemsimages->image_intro)) ? $itemsimages->image_intro : '';
			$jsonarray['article']['image_fulltext'] = (isset($itemsimages->image_fulltext)) ? $itemsimages->image_fulltext : '';

			$itemsurls                    = json_decode($items->urls);
			$jsonarray['article']['urls'] = array();
			$i                            = 0;

			if (isset($itemsurls->urla) && !empty($itemsurls->urla))
			{
				$jsonarray['article']['urls'][$i]['url']     = $itemsurls->urla;
				$jsonarray['article']['urls'][$i]['urltext'] = $itemsurls->urlatext;
				$i++;
			}

			if (isset($itemsurls->urlb) && !empty($itemsurls->urlb))
			{
				$jsonarray['article']['urls'][$i]['url']     = $itemsurls->urlb;
				$jsonarray['article']['urls'][$i]['urltext'] = $itemsurls->urlbtext;
				$i++;
			}

			if (isset($itemsurls->urlc) && !empty($itemsurls->urlc))
			{
				$jsonarray['article']['urls'][$i]['url']     = $itemsurls->urlc;
				$jsonarray['article']['urls'][$i]['urltext'] = $itemsurls->urlctext;
				$i++;
			}

			unset($i);
			$jsonarray['article']['created']   = $items->created;
			$jsonarray['article']['author']    = $items->author;
			$jsonarray['article']['shareLink'] = JURI::base() . "index.php?option=com_content&view=article&id={$items->id}:{$items->alias}&catid={$items->catid}";
		}
		else
		{
			$jsonarray['code'] = 706;
		}

		return $jsonarray;
	}

	/**
	 * function for formatImageUri
	 *
	 * @param   string  $imagepath  path of image
	 *
	 * @return  string  $imagepath
	 */
	private function formatImageUri($imagepath)
	{
		$image_properties = parse_url($imagepath);

		if (empty($image_properties['host']))
		{
			$imagepath = JUri::base() . $imagepath;
		}

		return $imagepath;
	}
}
