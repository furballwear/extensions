<?php 
/*--------------------------------------------------------------------------------
# Ijoomeradv Extension : EASYBLOG_1.5 (ccompatible with easybBlog 3.8.14427)
# ------------------------------------------------------------------------
# author Tailored Solutions - ijoomer.com
# copyright Copyright (C) 2010 Tailored Solutions. All rights reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://www.ijoomer.com
# Technical Support: Forum - http://www.ijoomer.com/Forum/
----------------------------------------------------------------------------------*/

defined( '_JEXEC' ) or die( 'Restricted access' );

class categories
{
	
	private $db;
	
	function __construct()
	{
		$this->db =& JFactory::getDBO();
	}
	
	/**
     * @uses Category list
     * @example the json string will be like, : 
	 * 	{
	 * 		"extName":"icms",
	 *		"extView":"categories",
 	 *		"extTask":"allCategories",
	 * 		"taskData":{}
	 * 	}
     * 
     */
	/*public function category(){
		$id = IJReq::getTaskData('id',0,'int');
		$categories	= $this->getCategories($id);
		$articles 	= ($id <= 0) ? array() : $this->getArticles($id);
		return $this->prepareObject($articles,$categories);
	}*/
	
	// to fetch parent/children categories
	/*private function getCategories($id){
		JRequest::setVar('id',$id);
		include_once ( JPATH_SITE . DS . 'libraries' . DS . 'joomla' . DS . 'application' . DS . 'categories.php' );
		include_once ( JPATH_SITE . DS . 'components' . DS . 'com_content' . DS . 'models' . DS . 'categories.php' );
		
		if($id == 0){
			$ContentModelCategories = new ContentModelCategories();
			$categories 	= $ContentModelCategories->getItems();
		}else{
			$ContentModelCategory = new ContentModelCategory();
			$categories 	= $ContentModelCategory->getChildren();
		}
		return (json_decode(json_encode($categories)));
	}*/
	
	// to fetch articles
	/*private function getArticles($id){
		JRequest::setVar('id',$id);
		include_once ( JPATH_SITE . DS . 'libraries' . DS . 'joomla' . DS . 'application' . DS . 'categories.php' );
		include_once ( JPATH_SITE . DS . 'components' . DS . 'com_content' . DS . 'models' . DS . 'categories.php' );
		
		if($id == 0){
			$articles 	= array();
		}else{
			$ContentModelCategory = new ContentModelCategory();
			$articles 	= $ContentModelCategory->getItems();
			$articles	= json_decode(json_encode($articles));
		}
		return (json_decode(json_encode($articles)));
	}*/
	
	/**
	 * Function for prepare object with list of articles and categories
	 *
	 * @param Array $articles
	 * @param Array $categories
	 * @return Array
	 */
	/*private function prepareObject($articles,$categories){
		$totalarticles = count($articles);
		$totalcategories = count($categories);
		$articlepageno = IJReq::getTaskData('pageNO',1,'int');
		
		if($totalarticles <= 0 && $totalcategories <= 0){
			$jsonarray['code']		= 204;
			return $jsonarray; 
		}
		
		if($totalarticles<=0){
			$articleArray['articles'] 	= array();
		}else{
			require_once JPATH_COMPONENT.DS.'extensions'.DS.'icms'.DS.'articles.php';
			$articlesObj = new articles();
			$articleArray = $articlesObj->getArticleList($articles,$totalarticles,true);
		}
		
		if($totalcategories <= 0 or $articlepageno>1){
			$categoryArray['categories'] = array();
		}else{
			require_once JPATH_SITE.DS.'components'.DS.'com_content'.DS.'models'.DS.'category.php';
			$categoryObj = new ContentModelCategory();
			$inc=0;
			$categoryArray = array();
			foreach ($categories as $value){
				$subcategory = $this->getCategories($value->id);
				$subcategorycount = count($subcategory);
				$ischild = false;
				$ischild = ($subcategorycount > 0 or $value->numitems > 0) ? true : $this->getChildCount($value->id);
				if($ischild){
					$categoryArray['categories'][$inc]['categoryid'] 	= $value->id;
					$categoryArray['categories'][$inc]['title'] 		= $value->title;
					$categoryArray['categories'][$inc]['description'] 	= strip_tags($value->description);
					
					$images=array();
					preg_match_all('/(src)=("[^"]*")/i',$value->description, $images);
					$imgpath=str_replace(array('src="','"'),"",$images[0]);
					if(!empty($imgpath[0])){
						$image_properties=parse_url($imgpath[0]);
						if(empty($image_properties['host'])){
							$imgpath[0] = JUri::base().$imgpath[0];
						}
					}
					
					$categoryArray['categories'][$inc]['image'] 		= ($imgpath) ? $imgpath[0]:'';
					$categoryArray['categories'][$inc]['parent_id'] 	= $value->parent_id;
					$categoryArray['categories'][$inc]['hits'] 			= $value->hits;
					$categoryArray['categories'][$inc]['totalarticles']	= ($value->numitems)?$value->numitems:0;
					
					$query = 'SELECT count(id)
				  			  FROM #__categories
				  			  WHERE parent_id='.$value->id.' AND published = 1';
					$this->db->setQuery($query);
					$categoryArray['categories'][$inc]['totalcategories']	= $this->db->loadResult();
					$inc++;
				}
			}
			if(!$categoryArray){
				$categoryArray['categories'] = array();
			}
		}
		if(!isset($categoryArray['categories']) && !isset($articleArray['articles']) or (empty($categoryArray) && empty($articleArray))){
			$jsonarray['code']		= 204;
			return $jsonarray;
		}
		$jsonarray['code']		= 200;
		$jsonarray['total']		= $totalarticles;
		$jsonarray['pageLimit']	= ICMS_ARTICLE_LIMIT;
		$jsonarray['articles']	= $articleArray['articles'];
		$jsonarray['categories']= $categoryArray['categories'];
		
		return $jsonarray;
	}*/
	
	// to fetch category child count.
	/*private function getChildCount($id){
		$childcategory	= $this->getCategories($id);
		foreach ($childcategory as $value){
			if($value->numitems>0){
				return true;
			}else{
				$this->getChildCount($value->id);
			}
		}
		return false;*/
	}
}