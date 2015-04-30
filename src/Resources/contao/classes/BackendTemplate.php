<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao;

use Contao\CoreBundle\Event\ContaoEvents;
use Contao\CoreBundle\Event\TemplateEvent;
use Symfony\Component\HttpKernel\KernelInterface;


/**
 * Provide methods to handle back end templates.
 *
 * @property string $ua
 * @property array  $javascripts
 * @property array  $stylesheets
 * @property string $mootools
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class BackendTemplate extends \Template
{

	/**
	 * Add a hook to modify the template output
	 *
	 * @return string
	 */
	public function parse()
	{
		$strBuffer = parent::parse();

		/** @var KernelInterface $kernel */
		global $kernel;

		// Dispatch the contao.parse_backend_template event
		$event = new TemplateEvent($strBuffer, $this->strTemplate, $this);
		$kernel->getContainer()->get('event_dispatcher')->dispatch(ContaoEvents::PARSE_BACKEND_TEMPLATE, $event);

		$strBuffer = $event->getBuffer();

		// HOOK: add custom parse filters
		if (isset($GLOBALS['TL_HOOKS']['parseBackendTemplate']) && is_array($GLOBALS['TL_HOOKS']['parseBackendTemplate']))
		{
			foreach ($GLOBALS['TL_HOOKS']['parseBackendTemplate'] as $callback)
			{
				$this->import($callback[0]);
				$strBuffer = $this->$callback[0]->$callback[1]($strBuffer, $this->strTemplate);
			}
		}

		return $strBuffer;
	}


	/**
	 * Compile the template
	 *
	 * @internal
	 */
	protected function compile()
	{
		// User agent class (see #3074 and #6277)
		$this->ua = \Environment::get('agent')->class;

		// Style sheets
		if (!empty($GLOBALS['TL_CSS']) && is_array($GLOBALS['TL_CSS']))
		{
			$strStyleSheets = '';

			foreach (array_unique($GLOBALS['TL_CSS']) as $stylesheet)
			{
				$options = \String::resolveFlaggedUrl($stylesheet);
				$strStyleSheets .= \Template::generateStyleTag($this->addStaticUrlTo($stylesheet), $options->media);
			}

			$this->stylesheets = $strStyleSheets;
		}

		// JavaScripts
		if (!empty($GLOBALS['TL_JAVASCRIPT']) && is_array($GLOBALS['TL_JAVASCRIPT']))
		{
			$strJavaScripts = '';

			foreach (array_unique($GLOBALS['TL_JAVASCRIPT']) as $javascript)
			{
				$options = \String::resolveFlaggedUrl($javascript);
				$strJavaScripts .= \Template::generateScriptTag($this->addStaticUrlTo($javascript), $options->async) . "\n";
			}

			$this->javascripts = $strJavaScripts;
		}

		// MooTools scripts (added at the page bottom)
		if (!empty($GLOBALS['TL_MOOTOOLS']) && is_array($GLOBALS['TL_MOOTOOLS']))
		{
			$strMootools = '';

			foreach (array_unique($GLOBALS['TL_MOOTOOLS']) as $script)
			{
				$strMootools .= "\n" . trim($script) . "\n";
			}

			$this->mootools = $strMootools;
		}

		$this->strBuffer = $this->parse();
		$this->strBuffer = static::replaceOldBePaths($this->strBuffer);

		/** @var KernelInterface $kernel */
		global $kernel;

		// Dispatch the contao.output_backend_template event
		$event = new TemplateEvent($this->strBuffer, $this->strTemplate, $this);
		$kernel->getContainer()->get('event_dispatcher')->dispatch(ContaoEvents::OUTPUT_BACKEND_TEMPLATE, $event);

		$this->strBuffer = $event->getBuffer();

		// HOOK: add custom output filter
		if (isset($GLOBALS['TL_HOOKS']['outputBackendTemplate']) && is_array($GLOBALS['TL_HOOKS']['outputBackendTemplate']))
		{
			foreach ($GLOBALS['TL_HOOKS']['outputBackendTemplate'] as $callback)
			{
				$this->import($callback[0]);
				$this->strBuffer = $this->$callback[0]->$callback[1]($this->strBuffer, $this->strTemplate);
			}
		}

		parent::compile();
	}


	/**
	 * Return the locale string
	 *
	 * @return string
	 */
	protected function getLocaleString()
	{
		return
			'var Contao={'
				. 'theme:"' . \Backend::getTheme() . '",'
				. 'lang:{'
					. 'close:"' . $GLOBALS['TL_LANG']['MSC']['close'] . '",'
					. 'collapse:"' . $GLOBALS['TL_LANG']['MSC']['collapseNode'] . '",'
					. 'expand:"' . $GLOBALS['TL_LANG']['MSC']['expandNode'] . '",'
					. 'loading:"' . $GLOBALS['TL_LANG']['MSC']['loadingData'] . '",'
					. 'apply:"' . $GLOBALS['TL_LANG']['MSC']['apply'] . '",'
					. 'picker:"' . $GLOBALS['TL_LANG']['MSC']['pickerNoSelection'] . '"'
				. '},'
				. 'script_url:"' . TL_ASSETS_URL . '",'
				. 'path:"' . \Environment::get('path') . '",'
				. 'request_token:"' . REQUEST_TOKEN . '",'
				. 'referer_id:"' . TL_REFERER_ID . '"'
			. '};';
	}


	/**
	 * Return the datepicker string
	 *
	 * Fix the MooTools more parsers which incorrectly parse ISO-8601 and do
	 * not handle German date formats at all.
	 *
	 * @return string
	 */
	protected function getDateString()
	{
		return
			'Locale.define("en-US","Date",{'
				. 'months:["' . implode('","', $GLOBALS['TL_LANG']['MONTHS']) . '"],'
				. 'days:["' . implode('","', $GLOBALS['TL_LANG']['DAYS']) . '"],'
				. 'months_abbr:["' . implode('","', $GLOBALS['TL_LANG']['MONTHS_SHORT']) . '"],'
				. 'days_abbr:["' . implode('","', $GLOBALS['TL_LANG']['DAYS_SHORT']) . '"]'
			. '});'
			. 'Locale.define("en-US","DatePicker",{'
				. 'select_a_time:"' . $GLOBALS['TL_LANG']['DP']['select_a_time'] . '",'
				. 'use_mouse_wheel:"' . $GLOBALS['TL_LANG']['DP']['use_mouse_wheel'] . '",'
				. 'time_confirm_button:"' . $GLOBALS['TL_LANG']['DP']['time_confirm_button'] . '",'
				. 'apply_range:"' . $GLOBALS['TL_LANG']['DP']['apply_range'] . '",'
				. 'cancel:"' . $GLOBALS['TL_LANG']['DP']['cancel'] . '",'
				. 'week:"' . $GLOBALS['TL_LANG']['DP']['week'] . '"'
			. '});';
	}
}
