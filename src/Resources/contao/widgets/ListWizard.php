<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao;


/**
 * Provide methods to handle list items.
 *
 * @property integer $maxlength
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ListWizard extends \Widget
{

	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';


	/**
	 * Add specific attributes
	 *
	 * @param string $strKey
	 * @param mixed  $varValue
	 */
	public function __set($strKey, $varValue)
	{
		switch ($strKey)
		{
			case 'maxlength':
				if ($varValue > 0)
				{
					$this->arrAttributes['maxlength'] = $varValue;
				}
				break;

			default:
				parent::__set($strKey, $varValue);
				break;
		}
	}


	/**
	 * Generate the widget and return it as string
	 *
	 * @return string
	 */
	public function generate()
	{
		$arrButtons = array('copy', 'drag', 'up', 'down', 'delete');
		$strCommand = 'cmd_' . $this->strField;

		// Change the order
		if (\Input::get($strCommand) && is_numeric(\Input::get('cid')) && \Input::get('id') == $this->currentRecord)
		{
			$this->import('Database');

			switch (\Input::get($strCommand))
			{
				case 'copy':
					$this->varValue = array_duplicate($this->varValue, \Input::get('cid'));
					break;

				case 'up':
					$this->varValue = array_move_up($this->varValue, \Input::get('cid'));
					break;

				case 'down':
					$this->varValue = array_move_down($this->varValue, \Input::get('cid'));
					break;

				case 'delete':
					$this->varValue = array_delete($this->varValue, \Input::get('cid'));
					break;
			}

			$this->Database->prepare("UPDATE " . $this->strTable . " SET " . $this->strField . "=? WHERE id=?")
						   ->execute(serialize($this->varValue), $this->currentRecord);

			$this->redirect(preg_replace('/&(amp;)?cid=[^&]*/i', '', preg_replace('/&(amp;)?' . preg_quote($strCommand, '/') . '=[^&]*/i', '', \Environment::get('request'))));
		}

		// Make sure there is at least an empty array
		if (!is_array($this->varValue) || empty($this->varValue))
		{
			$this->varValue = array('');
		}

		// Initialize the tab index
		if (!\Cache::has('tabindex'))
		{
			\Cache::set('tabindex', 1);
		}

		$tabindex = \Cache::get('tabindex');
		$return = '<ul id="ctrl_'.$this->strId.'" class="tl_listwizard" data-tabindex="'.$tabindex.'">';

		// Add input fields
		for ($i=0, $c=count($this->varValue); $i<$c; $i++)
		{
			$return .= '
    <li><input type="text" name="'.$this->strId.'[]" class="tl_text" tabindex="'.$tabindex++.'" value="'.specialchars($this->varValue[$i]).'"' . $this->getAttributes() . '> ';

			// Add buttons
			foreach ($arrButtons as $button)
			{
				$class = ($button == 'up' || $button == 'down') ? ' class="button-move"' : '';

				if ($button == 'drag')
				{
					$return .= \Image::getHtml('drag.gif', '', 'class="drag-handle" title="' . sprintf($GLOBALS['TL_LANG']['MSC']['move']) . '"');
				}
				else
				{
					$return .= '<a href="'.$this->addToUrl('&amp;'.$strCommand.'='.$button.'&amp;cid='.$i.'&amp;id='.$this->currentRecord).'"' . $class . ' title="'.specialchars($GLOBALS['TL_LANG']['MSC']['lw_'.$button]).'" onclick="Backend.listWizard(this,\''.$button.'\',\'ctrl_'.$this->strId.'\');return false">'.\Image::getHtml($button.'.gif', $GLOBALS['TL_LANG']['MSC']['lw_'.$button], 'class="tl_listwizard_img"').'</a> ';
				}
			}

			$return .= '</li>';
		}

		// Store the tab index
		\Cache::set('tabindex', $tabindex);

		return $return.'
  </ul>';
	}


	/**
	 * Return a form to choose a CSV file and import it
	 *
	 * @param DataContainer $dc
	 *
	 * @return string
	 *
	 * @throws \Exception
	 * @throws \Contao\CoreBundle\Exception\RedirectResponseException
	 *
	 * @deprecated Since 4.2 to be removed in 5.0. Use the BackendCsvImportController instead.
	 */
	public function importList(DataContainer $dc)
	{
		$service = System::getContainer()->get('contao.controller.backend_csv_import');

		return $service->importListWizardAction($dc);
	}
}
