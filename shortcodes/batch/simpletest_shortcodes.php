<?php

/**
 * @file
 * Shortcodes for "simpletest" plugin.
 */

if(!defined('e107_INIT'))
{
	exit;
}

// [PLUGINS]/simpletest/languages/[LANGUAGE]/[LANGUAGE]_front.php
e107::lan('simpletest');


/**
 * Class simpletest_shortcodes.
 */
class simpletest_shortcodes extends e_shortcode
{

	/**
	 * Constructor.
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * @return string
	 */
	function sc_panel_title()
	{
		$html = '';

		if((bool) $this->var['options']['collapsible'] === true)
		{
			$html .= '<a data-toggle="collapse" href="#' . $this->var['options']['id'] . '">';
		}

		if(is_bool($this->var['options']['success']))
		{
			if($this->var['options']['success'] === true)
			{
				$html .= e107::getParser()->toGlyph('glyphicon-ok') . ' ';
			}
			else
			{
				$html .= e107::getParser()->toGlyph('glyphicon-remove') . ' ';
			}
		}

		$html .= $this->var['title'];

		if((bool) $this->var['options']['collapsible'] === true)
		{
			$html .= '</a>';
		}

		if((bool) $this->var['options']['checkbox'] === true)
		{
			return e107::getForm()->checkbox('cb', 1) . ' ' . $html;
		}

		return $html;
	}

	/**
	 * @return string
	 */
	function sc_panel_class()
	{
		$class = 'panel panel-default simpletest-widget-panel';

		if(is_bool($this->var['options']['success']))
		{
			if($this->var['options']['success'] === true)
			{
				// $class .= ' panel-success';
			}
			else
			{
				// $class .= ' panel-danger';
			}
		}

		return $class;
	}

	/**
	 * @return string
	 */
	function sc_panel_body()
	{
		return $this->var['body'];
	}

	/**
	 * @return string
	 */
	function sc_panel_id()
	{
		return $this->var['options']['id'];
	}

	/**
	 * @return string
	 */
	function sc_panel_body_class()
	{
		$class = 'panel-collapse collapse';

		if((bool) $this->var['options']['collapsible'] === false || (bool) $this->var['options']['collapsed'] === false)
		{
			$class .= ' in';
		}

		return $class;
	}

	/**
	 * @return string
	 */
	function sc_panel_help()
	{
		if(empty($this->var['help']))
		{
			return '';
		}

		return '<span class="help-block">' . $this->var['help'] . '</span>';
	}

}
