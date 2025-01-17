<?php
/*
Plugin Name: TweetButton
Plugin URI: http://goredmonster.com
Description: Adds official Twitter button to posts. | <a href="options-general.php?page=tweetbutton-options">Plugin Settings</a> | <a href="http://wiki.github.com/paulredmond/TwitterButton-Wordpress-Plugin/">Wiki (on GitHub.com)</a>
Version: 1.0.2
Author: Paul Redmond
Author URI: http://goredmonster.com
*/

define('_TB_DS_', DIRECTORY_SEPARATOR);
define('_TB_PLUGIN_PATH_', dirname(__FILE__));
define('_TB_VIEWS_', _TB_PLUGIN_PATH_ . _TB_DS_ . 'views');
define('_TB_PLUGIN_URL_', WP_PLUGIN_URL . '/' . basename(dirname(__FILE__)) );
define('_TB_ASSETS_URL_', _TB_PLUGIN_URL_ . '/assets' );
define('_TB_IMAGES_URL_', _TB_ASSETS_URL_ . '/images' );

class TweetButton
{
	private $widgets_js_src = 'http://platform.twitter.com/widgets.js';
	
	private $share_url = 'http://twitter.com/share';
	
	private $option_prefix = 'tweetbutton-';
	
	private $option_group = 'tweetbutton-options';
	
	private $disable_auto = false;
	
	/**
	 * Default position where TweetButton is rendered (after the_content()).
	 */
	private $position = 'after';
	
	private $data_attributes = array(
		'data-url',
		'data-via',
		'data-text',
		'data-related',
		'data-count',
	);

	private function __construct()
	{
		if ( is_admin() ) { 
			add_action('admin_init', array($this, 'registerSettings'));
			add_action('admin_menu', array($this, 'addOptionsPage'));
			
			add_action('admin_print_styles-settings_page_tweetbutton-options', array($this, 'styleAssets'));
			add_action('admin_print_scripts-settings_page_tweetbutton-options', array($this, 'scriptAssets'));
			
			# Add options if they don't exist.
			add_option('tweetbutton-data-via');
			add_option('tweetbutton-data-related');
			add_option('tweetbutton-data-count');
			add_option('tweetbutton-disable-auto');
			add_option('tweetbutton-position');
			add_option('tweetbutton-display-on-home');
			add_option('tweetbutton-display-on-pages');
		}
		
		$this->Html = new HtmlHelper();
		
		$this->disable_auto = get_option('tweetbutton-disable-auto');
		$this->position = get_option('tweetbutton-position');
		
		if($this->getDisableAuto() !== true)
		{
			add_filter('the_content', array($this, 'theContentFilter'));
		}
	}
	
	public static function getInstance()
	{
		static $instance = array();
			if (!$instance) {
				$instance[0] = new TweetButton();
			}
			return $instance[0];
	}
	
	public function scriptAssets()
	{
		wp_enqueue_script('tweetbuttonScripts');
	}
	
	public function styleAssets()
	{
		wp_enqueue_style('tweetbuttonStyles');
	}
	
	public function registerSettings()
	{
		wp_register_style('tweetbuttonStyles', _TB_ASSETS_URL_ . '/css/tweetbutton.css');
		wp_register_script('tweetbuttonScripts', _TB_ASSETS_URL_ . '/js/tweetbutton.js');
		
		register_setting('tweetbutton-options', $this->option_prefix . 'data-via');
		register_setting('tweetbutton-options', $this->option_prefix . 'data-related');
		register_setting('tweetbutton-options', $this->option_prefix . 'data-count');
		register_setting('tweetbutton-options', $this->option_prefix . 'disable-auto');
		register_setting('tweetbutton-options', $this->option_prefix . 'position');
		register_setting('tweetbutton-options', $this->option_prefix . 'display-on-home');
		register_setting('tweetbutton-options', $this->option_prefix . 'display-on-pages');
	}
	
	public function addOptionsPage()
	{
		add_options_page('Tweet Button Options', 'TweetButton', 'administrator', 'tweetbutton-options', array($this, 'tweetButtonOptions'));
	}
	
	public function theContentFilter($content)
	{
		$out = $this->buildTwitterHtml( $this->getDataAttributes( get_permalink(), get_the_title() ) );
		
		$display = false; # Default
		
		if(is_home()) {
			$display = (boolean) get_option($this->option_prefix . 'display-on-home');
		} elseif(is_page()) {
			$display = (boolean) get_option($this->option_prefix . 'display-on-pages');
		} elseif(is_single()) {
			$display = true;	
		}
		
		if($display == false)
		{
			return $content;
		}
		
		if($this->getPosition() == 'before')
		{
			return $out . "\n" . $content;
		}
		
		return $content . "\n" . $out;
	}

	public function tweetButtonOptions()
	{
		require_once _TB_VIEWS_ . _TB_DS_ . 'options.php';
		renderTweetButtonOptions($this);
	}
	
	public function getOptionGroup()
	{
		return $this->option_group;
	}
	
	public function getDisableAuto()
	{
		return (boolean) $this->disable_auto;
	}
	
	public function getPosition()
	{
		return $this->position;
	}
	
	public function buildTwitterHtml($data_attributes=array())
	{
		# Get attributes
		$link_attributes = array_merge(
			array('class' => 'twitter-share-button', 'data-count' => 'vertical'),
			$data_attributes
		);
		
		# Create HTML
		$out  = $this->Html->comment('Start TwitterButton');
		$out .= $this->Html->javascriptlink($this->widgets_js_src);
		$out .= $this->Html->div(
			$this->Html->link('Tweet', $this->share_url, $link_attributes),
			array('class' => "twitter-button")
		);
		return $out;
	}
	
	/**
	 * Get options for twitter button from options table.
	 */
	public function getDataAttributes($data_url, $data_text)
	{
		$attributes = array();
		$option_prefix = 'twitter-button-';
		
		foreach($this->data_attributes as $attr)
		{
			switch($attr)
			{
				case 'data-url':
					$val = $data_url;
				break;
				case 'data-text':
					$val = $data_text;
				break;
				default:
					$val = get_option($this->option_prefix . $attr);
				break;
			}
			
			if(!empty($val)) {
				$attributes[$attr] = $val;
			}
			unset($val);
		}
		return $attributes;
	}
}

$tweet_button = TweetButton::getInstance(); # Init plugin.


/**
 * Function that can be used in themes to create a tweet button anywhere on the page.
 *
 * $attributes array options include 'data-url', 'data-via', 'data-text', 'data-related', 'data-count'
 * Options in the attributes array trumps options in the database.
 * 
 * @param array $attributes array of data attributes.
 * @param boolean $override Determines if calls made to renderTweetButton() will take effect even if auto-output is true.
 * @param boolean $disable_options Setting to true will stop database option data from being merged with passed attributes.
 * @return null echos output to page from function.
 */
function renderTweetButton($attributes=array(), $override=false, $disable_options=false)
{
	$tweet_button = TweetButton::getInstance();
	
	# Wont output if auto-output is enabled.
	if($tweet_button->getDisableAuto() == true && $override === false) { return false; }
	
	# Options set in admin will be merged.
	if($disable_options == false)
	{
		$data = $tweet_button->getDataAttributes($url, $text); # @todo update to use attributes array.
		$attributes = array_merge($data, $attributes);
	}
	$out = $tweet_button->buildTwitterHtml($attributes);
	echo $out;
}


/**
 * Basic helper for this plugin.
 */
class HtmlHelper
{
	public $tags = array(
		'javascriptlink' => '<script type="text/javascript" src="%s"></script>',
		'div'            => '<div%s>%s</div>',
		'link'           => '<a href="%s"%s>%s</a>',
		'comment'        => "<!-- %s -->\n",
		'select'         => '<select name="%s"%s>%s</select>',
		'option'         => '<option value="%s"%s>%s</option>',
		'input'          => '<input type="%s" name="%s" value="%s"%s />',
		'para'           => '<p%s>%s</p>',
		'submit'         => '<input type="submit" name="Submit" value="%s" class="%s" />'
	);

	public function javascriptlink($src)
	{
		return sprintf($this->tags['javascriptlink'], $src);
	}
	
	public function div($innerHTML, $attributes=array())
	{
		$attrs = $this->_parseAttributes($attributes);
		return sprintf($this->tags['div'], $attrs, $innerHTML);
	}
	
	public function link($text, $href, $attributes=array(), $escape=true)
	{
		$attrs = $this->_parseAttributes($attributes);
		if($escape === true)
		{
			$text = htmlentities($text);
		}
		return sprintf($this->tags['link'], $href, $attrs, $text);
	}
	
	public function comment($comment)
	{
		return sprintf($this->tags['comment'], $comment);
	}
	
	public function select($name, $options, $attributes=array())
	{
		$option_selected = get_option($name);
		$output = '';
		foreach($options as $value => $label)
		{
			$option_attr = array();
			if($option_selected == $value)
			{
				$option_attr['selected'] = 'selected';
			}
			$output .= sprintf($this->tags['option'], $value, $this->_parseAttributes($option_attr), $label) . "\n";
		}
		
		if(!array_key_exists('id', $attributes))
		{
			$attributes['id'] = $name;
		}
		
		return sprintf($this->tags['select'], $name, $this->_parseAttributes($attributes), "\n" . $output . "\n");
	}
	
	public function input($type, $name, $value=null, $attributes=array())
	{
		$input_value = get_option($name);
		if($type == 'radio' || $type == 'checkbox')
		{
			if($input_value == $value) {
				$attributes['checked'] = 'checked';
			}
			# just to make sure the right value set for checkboxes/radios.
			$input_value = $value;
		}
		
		if(!array_key_exists('id', $attributes))
		{
			$attributes['id'] = $name;
		}
		
		return sprintf($this->tags['input'], $type, $name, $input_value, $this->_parseAttributes($attributes));
	}
	
	public function para($innerHTML, $attributes=array())
	{
		return sprintf($this->tags['para'], $this->_parseAttributes($attributes), $innerHTML);
	}
	
	public function submit($value='Save Changes', $class='button-primary')
	{
		return $this->para(sprintf($this->tags['submit'], $value, $class), array('class' => 'submit'));
	}
	
	private function _parseAttributes($attributes)
	{
		if(empty($attributes)) { return false; }
		
		$pattern = '%s="%s"';
		$out = array();
		foreach($attributes as $attr => $val)
		{
			$out[] = sprintf($pattern, $attr, $val);
		}
		return ' ' . implode(" ", $out); # extra space at beginning.
	}
}