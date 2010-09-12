<?php
/*
 Plugin Name: Evernote Site Memory for WordPress
 Plugin URI: http://wordpress.org/extend/plugins/site-memory-for-wordpress/
 Description: Evernote Site Memory for WordPress
 Version: 0.1
 Author: makoto_kw
 Author URI: http://www.makotokw.com/
 */
/*  Copyright 2010 makoto_kw (email : makoto.kw+wordpress@gmail.com)
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

function site_memory_the_note_it() {
	echo Site_Memory::$current->get_site_memory();
}

class Site_Memory {
	const VERSION = '0.1';
	const SCRIPT_URL = 'http://static.evernote.com/noteit.js';

	var $slug;
	var $url;
	var $option_name;
	var $setting_fileds;
	var $deafult_settings;
	var $settings;
	var $icon_url;
	var $clip_options;
	var $textdomain = 'site-memory';
	
	static $current;
	static function init() {
		self::$current = new Site_Memory();
		self::$current->_init();
	}
	
	function get_site_memory() {
		global $post;
		$options = (array)$this->clip_options;
		if (isset($options['contentId'])) {
			$options['contentId'] = str_replace('%post_id%',$post->ID,$options['contentId']);
		}
		$options['title'] = get_the_title();
		$options['url'] = get_permalink();
		return '<a href="#" onclick=\'Evernote.doClip('.json_encode($options).'); return false;\'>'
			.'<img src="'.$this->settings['buttonImg'].'" alt="'.__('Clip to Evernote', $this->textdomain).'" />'
			.'</a>'
			;
	}
	
	protected function _init() {
		$this->slug = end(explode(DIRECTORY_SEPARATOR, dirname(__FILE__)));
		$this->url = get_bloginfo('url').'/wp-content/plugins/'.end(explode(DIRECTORY_SEPARATOR, dirname(__FILE__)));
		load_plugin_textdomain($this->textdomain, false, $this->slug.'/languages');
		add_action('wp_print_scripts', array($this, 'wp_print_scripts') );
		//add_filter('the_content', array($this, 'the_content'), 8);
		//add_filter('get_the_excerpt', array(this, 'get_the_excerpt'), 9);
		if (is_admin()) {
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('admin_init', array($this, 'admin_init'));
		}
		$this->init_settings();
		$this->clip_options = $this->get_clip_options();
	}
	
	function get_clip_options() {
		$options = array();
		foreach ($this->setting_fileds as $key => $field) {
			if ($field['section'] == 'clip') {
				$value = @$this->settings[$key];
				if (!empty($value)) {
					$options[$key] = $value;
				}
			}
		}
		return $options;
	}
	
	function init_settings() {
		$this->option_name = str_replace('-','_',$this->slug).'_settings';
		
		$buttonImgs = array(
			'article-clipper.png',
			'article-clipper-fr.png',
			'article-clipper-jp.png',
			'article-clipper-remember.png',
			'article-clipper-es.png',
			'article-clipper-rus.png',
			'article-clipper-de.png',
			'site-mem-36.png',
			'site-mem-32.png',
			'site-mem-22.png',
			'site-mem-16.png',
		);
		
		
		$buttonImg_options = array();
		foreach ($buttonImgs as $img) {
			$buttonImg_options['http://static.evernote.com/'.$img] = '<img src="http://static.evernote.com/'.$img.'" />';
		}
		$this->setting_fileds = array(
			'buttonImg'=>array(
				'label'=>__('Button image',$this->textdomain),
				'type'=>'radio',
				'options'=>$buttonImg_options,
				'defaults'=>'http://static.evernote.com/article-clipper.png',
				'section'=>'layout',
			),
			
			'providerName'=>array(
				'label'=>__('Site name',$this->textdomain), 
				'type'=>'text',
				'description'=>__('Name that will be displayed in the Site Memory window. If left blank, it will be your domain name.',$this->textdomain),
				'defaults'=>get_bloginfo('name'),
				'size'=>80,
				'section'=>'clip',
			),
			'suggestNotebook'=>array(
				'label'=>__('Suggested notebook for clips',$this->textdomain), 
				'type'=>'text',
				'description'=>__('Suggest a destination notebook for your content. The notebook will be created if it does not exist.',$this->textdomain),
				'defaults'=>'',
				'size'=>80,
				'section'=>'clip',
			),
			'contentId'=>array(
				'label'=>__('Content to clip',$this->textdomain), 
				'type'=>'text',
				'description'=>__('The ID of the HTML element that Site Memory should clip. If left blank, a simple link to the page will be clipped.',$this->textdomain),
				'defaults'=>'post-%post_id%',
				'size'=>80,
				'section'=>'clip',
			),
			'code'=>array(
				'label'=>__('Evernote referral code',$this->textdomain), 
				'type'=>'text',
				'description'=>__('Your Evernote Affiliate Program referral code or API consumer key. You can leave this blank, or <a href="http://www.evernote.com/about/affiliate" target="_blank">become an affiliate</a>.',$this->textdomain),
				'defaults'=>'',
				'size'=>80,
				'section'=>'clip',
			),
		);
		
		$this->default_settings = array();
		foreach ($this->setting_fileds as $key => $field) {
			$this->default_settings[$key] = $field['defaults'];
		}
		//delete_option($this->option_name);
		$this->settings = wp_parse_args((array)get_option($this->option_name), $this->default_settings );
	}
	
	// action, filter
	function wp_print_scripts() {
		if (!self::can_handle()) return;
		wp_enqueue_script($this->slug, self::SCRIPT_URL, null, self::VERSION);
	}
	function the_content($content) {
		return $content;
	}
	function get_the_excerpt($content) {
		if (!is_feed()) {
			remove_action('the_content', array($this,'the_content'));
		}
		return $content;
	}
	function admin_menu() {
		add_options_page(__('Site Memory',$this->textdomain), __('Site Memory',$this->textdomain), 'manage_options', $this->slug, array($this,'options_page'));
	}
	function admin_init() {
		$page = $this->slug;
		register_setting($this->option_name, $this->option_name, array($this,'validate_settings'));
		add_settings_section($page.'_layout', __('Layout settings',$this->textdomain), array($this, 'add_no_section'), $page);
		add_settings_section($page.'_clip', __('Customize clipping settings',$this->textdomain), array($this, 'add_no_section'), $page);
		foreach ($this->setting_fileds as $key => $field) {
			$label = ($field['type']=='checkbox') ? '' : $field['label'];
			add_settings_field(
				$this->option_name.'_'.$key, 
				$label,
				//array($this,'add_settings_field'),
				array($this,'add_settings_field_'.$key),
				$page,
				$page.'_'.$field['section']
				// , array($key, $field) // not work wordpress 2.9.0 #11143
				);
		}
	}
	
	function can_handle() {
		return (have_posts());
	}
	
	function validate_settings($settings) {
		foreach ($this->setting_fileds as $key => $field) {
			if ($field['type']=='checkbox') {
				$settings[$key] = ($settings[$key] == 'on');
			}
		}
		return $settings;
	}
	
	function add_no_section() {}
	
	function add_settings_field($key, $field) {
		$id = $this->option_name.'_'.$key;
		$name = $this->option_name."[{$key}]";
		$value = $this->settings[$key];
		if (isset($field['html'])) {
			echo $field['html'];
		} else {
			switch ($field['type']) {
				case 'checkbox':
					echo "<input id='{$id}' name='{$name}' type='checkbox' ".checked(true,$value,false)."/>";
					echo "<label for='{$id}'>".$field['label']."</label>";
					break;
				case 'radio':
					foreach ($field['options'] as $v => $content) {
						echo "<input name='{$name}' type='radio' ".checked($v,$value,false)." value='{$v}'>{$content}</input>";
					}
					break;
				case 'select':
					echo "<select id='{$id}' name='{$name}' value='{$value}'>";
					foreach ($field['options'] as $option => $name) {
						echo "<option value='{$option}' ".selected($option,$value,false).">{$name}</option>";
					}
					echo "</select>";
					break;
				case 'text':
				default:
					$size = @$field['size'];
					if ($size<=0) $size = 40;
					echo "<input id='{$id}' name='{$name}' size='{$size}' type='text' value='{$value}' />";
					break;
			}
		}
		if (@$field['description']) {
			echo "<p>".$field['description']."</p>";
		}
	}

	function add_settings_field_buttonImg() { $this->add_settings_field('buttonImg', $this->setting_fileds['buttonImg']); }
	function add_settings_field_providerName() { $this->add_settings_field('providerName', $this->setting_fileds['providerName']); }
	function add_settings_field_suggestNotebook() { $this->add_settings_field('suggestNotebook', $this->setting_fileds['suggestNotebook']); }
	function add_settings_field_contentId() { $this->add_settings_field('contentId', $this->setting_fileds['contentId']); }
	function add_settings_field_code() { $this->add_settings_field('code', $this->setting_fileds['code']); }

	function options_page() {
		$page = $this->slug;
?>
<div class="wrap">
<h2>Site Memory</h2>
<form action="options.php" method="post">
<?php settings_fields($this->option_name); ?>
<?php do_settings_sections($page); ?>
<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>"/>
</form>
</div>
<?php
	}
}
add_action('init', array('Site_Memory', 'init'));

// from http://php.net/manual/en/function.json-encode.php
if (!function_exists('json_encode'))
{
	function json_encode($a=false)
	{
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a))
		{
			if (is_float($a))
			{
				// Always use "." for floats.
				return floatval(str_replace(",", ".", strval($a)));
			}

			if (is_string($a))
			{
				static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
			}
			else
			return $a;
		}
		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a))
		{
			if (key($a) !== $i)
			{
				$isList = false;
				break;
			}
		}
		$result = array();
		if ($isList)
		{
			foreach ($a as $v) $result[] = json_encode($v);
			return '[' . join(',', $result) . ']';
		}
		else
		{
			foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
			return '{' . join(',', $result) . '}';
		}
	}
}