<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  icms
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * class for icms
 *
 * @package     IJoomer.Extensions
 * @subpackage  icms
 * @since       1.0
 */
class Icms
{
	public $classname = "icms";

	public $sessionWhiteList = array('articles.archive', 'articles.featured', 'articles.singleArticle', 'articles.articleDetail', 'categories.allCategories', 'categories.singleCategory', 'categories.category', 'categories.categoryBlog');

	/**
	 * function for initialization
	 *
	 * @return  void
	 */
	public function init()
	{
		include_once JPATH_SITE . '/components/com_content/models/category.php';
		include_once JPATH_SITE . '/components/com_content/models/archive.php';
		include_once JPATH_SITE . '/components/com_content/helpers/query.php';

		$lang  = JFactory::getLanguage();
		$lang->load('com_content');
		$plugin_path = JPATH_COMPONENT_SITE . '/extensions';
		$lang->load('icms', $plugin_path . '/icms', $lang->getTag(), true);
	}

	/**
	 * function for write configuration
	 *
	 * @param   [type]  &$d  d
	 *
	 * @return  void
	 */
	public function write_configuration(&$d)
	{
		$db    = JFactory::getDbo();
		$query = 'SELECT *
				  FROM #__ijoomeradv_icms_config';
		$db->setQuery($query);
		$my_config_array = $db->loadObjectList();

		foreach ($my_config_array as $ke => $val)
		{
			if (isset($d[$val->name]))
			{
				$sql = "UPDATE #__ijoomeradv_icms_config
						SET value='{$d[$val->name]}'
						WHERE name='{$val->name}'";
				$db->setQuery($sql);
				$db->query();
			}
		}
	}

	/**
	 * function for get configuration
	 *
	 * @return  array  jsonarray
	 */
	public function getconfig()
	{
		$jsonarray = array();

		return $jsonarray;
	}

	/**
	 * function for Prepare custom html for ICMS
	 *
	 * @param   array  &$Config  Configuration array
	 *
	 * @return  void
	 */
	public function prepareHTML(&$Config)
	{
		// TODO : Prepare custom html for ICMS
	}
}

/**
 * class for icms
 *
 * @package     IJoomer.Extensions
 * @subpackage  icms
 * @since       1.0
 */
class Icms_Menu
{
	/**
	 * function for get Required Input
	 *
	 * @param   string  $extension    extension
	 * @param   string  $extView      extension view
	 * @param   [type]  $menuoptions  menu options
	 *
	 * @return  array
	 */
	public function getRequiredInput($extension, $extView, $menuoptions)
	{
		$menuoptions = json_decode($menuoptions, true);

		switch ($extView)
		{
			case 'categoryBlog':
				$selvalue = $menuoptions['remoteUse']['id'];
				require_once JPATH_ADMINISTRATOR . '/components/com_categories/models/categories.php';

				$class = new CategoriesModelCategories;
				$query = $class->getListQuery();

				$db = JFactory::getDbo();
				$db->setQuery($query);
				$items = $db->loadObjectList();

				$html = '<fieldset class="panelform">
							<label title="" class="hasTip required" for="jform_request_id" id="jform_request_id-lbl" aria-invalid="false">' . JText::_('COM_IJOOMERADV_ICMS_SELECT_CATEGORY') . '
								<span class="star">&nbsp;*</span>
							</label>';

				$html .= '<select name="jform[request][id]" id="jform_request_id">';

				foreach ($items as $key1 => $value1)
				{
					$selected = ($selvalue == $value1->id) ? 'selected' : '';
					$level    = '';

					for ($i = 1; $i < $value1->level; $i++)
					{
						$level .= '-';
					}

					$html .= '<option value="' . $value1->id . '" ' . $selected . '>' . $level . $value1->title . '</option>';
				}

				$html .= '</select>';
				$html .= '</fieldset>';

				return $html;
				break;

			case 'singleCategory':
				$selvalue = $menuoptions['remoteUse']['id'];
				require_once JPATH_ADMINISTRATOR . '/components/com_categories/models/categories.php';

				$class = new CategoriesModelCategories;
				$query = $class->getListQuery();

				$db = JFactory::getDbo();
				$db->setQuery($query);
				$items = $db->loadObjectList();

				$html = '<fieldset class="panelform">
							<label title="" class="hasTip required" for="jform_request_id" id="jform_request_id-lbl" aria-invalid="false">' . JText::_('COM_IJOOMERADV_ICMS_SELECT_CATEGORY') . '
								<span class="star">&nbsp;*</span>
							</label>';

				$html .= '<select name="jform[request][id]" id="jform_request_id">';

				foreach ($items as $key1 => $value1)
				{
					$selected = ($selvalue == $value1->id) ? 'selected' : '';
					$level    = '';

					for ($i = 1; $i < $value1->level; $i++)
					{
						$level .= '-';
					}

					$html .= '<option value="' . $value1->id . '" ' . $selected . '>' . $level . $value1->title . '</option>';
				}

				$html .= '</select>';
				$html .= '</fieldset>';

				return $html;
				break;

			case 'singleArticle':
				$selvalue = $menuoptions['remoteUse']['id'];
				$db       = JFactory::getDBO();
				$sql      = 'SELECT title FROM #__content
						WHERE id=' . $selvalue;
				$db->setQuery($sql);
				$result = $db->loadResult();
				$title  = ($result) ? $result : 'COM_IJOOMERADV_ICMS_CHANGE_ARTICLE';

				// Load the modal behavior script.
				JHtml::_('behavior.modal', 'a.modal');

				// Build the script.
				$script   = array();
				$script[] = '	function jSelectArticle_jform_request_id(id, title, catid, object) {';
				$script[] = '		document.id("jform_request_id_id").value = id;';
				$script[] = '		document.id("jform_request_id_name").value = title;';
				$script[] = '		SqueezeBox.close();';
				$script[] = '	}';

				// Add the script to the document head.
				JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

				// Setup variables for display.
				$html = array();
				$link = 'index.php?option=com_content&amp;view=articles&amp;layout=modal&amp;tmpl=component&amp;function=jSelectArticle_jform_request_id';

				// The current user display field.
				$html[] = '<div class="fltlft">';
				$html[] = '  <input type="text" id="jform_request_id_name" value="' . JText::_($title) . '" disabled="disabled" size="35" />';
				$html[] = '</div>';

				// The user select button.
				$html[] = '<div class="button2-left">';
				$html[] = '  <div class="blank">';
				$html[] = '	<a class="modal" title="' . JText::_('COM_CONTENT_CHANGE_ARTICLE') . '"  href="' . $link . '&amp;' . JSession::getFormToken() . '=1" rel="{handler: \'iframe\', size: {x: 800, y: 450}}">' . JText::_('COM_IJOOMERADV_ICMS_CHANGE_ARTICLE_BUTTON') . '</a>';
				$html[] = '  </div>';
				$html[] = '</div>';

				$html[] = '<input type="hidden" id="jform_request_id_id" name="jform[request][id]" value="" />';

				return implode("\n", $html);
				break;
		}
	}

	/**
	 * function for set Required Input
	 *
	 * @param   string  $extension    extension name
	 * @param   string  $extView      extension view
	 * @param   string  $extTask      extension task
	 * @param   [type]  $remoteTask   remote task
	 * @param   [type]  $menuoptions  menu option
	 * @param   mixed   $data         mixed data
	 *
	 * @return  void
	 */
	public function setRequiredInput($extension, $extView, $extTask, $remoteTask, $menuoptions, $data)
	{
		$db      = JFactory::getDBO();
		$options = null;

		switch ($extTask)
		{
			case 'categoryBlog':
				$categoryid = $menuoptions['id'];
				$options    = '{"serverUse":{},"remoteUse":{"id":' . $categoryid . '}}';
				break;

			case 'singleCategory':
				$categoryid = $menuoptions['id'];
				$options    = '{"serverUse":{},"remoteUse":{"id":' . $categoryid . '}}';
				break;

			case 'singleArticle':
				$articleid = $menuoptions['id'];
				$options   = '{"serverUse":{},"remoteUse":{"id":' . $articleid . '}}';
				break;
		}

		if ($options)
		{
			$sql = "UPDATE #__ijoomeradv_menu
					SET menuoptions = '" . $options . "'
					WHERE views = '" . $extension . "." . $extView . "." . $extTask . "." . $remoteTask . "'
					AND id='" . $data['id'] . "'";
			$db->setQuery($sql);
			$db->query();
		}
	}
}
