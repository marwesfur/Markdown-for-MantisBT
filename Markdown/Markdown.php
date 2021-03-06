<?php
# Copyright (C) 2012-2013 Frank Bültge
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

require_once( config_get( 'class_path' ) . 'MantisFormattingPlugin.class.php' );

class MarkdownPlugin extends MantisFormattingPlugin {

	/**
	 * A method that populates the plugin information and minimum requirements.
	 * 
	 * @return  void
	 */
	public function register() {
		
		$this->name        = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page        = 'config';
		$this->version     = '1.1.0';
		$this->requires    = array(
			'MantisCore'           => '1.2.0',
			'MantisCoreFormatting' => '1.0a'
		);
		$this->author  = 'Frank B&uuml;ltge';
		$this->contact = 'frank@bueltge.de';
		$this->url     = 'http://bueltge.de';
	}
	
	/**
	 * Set options on Core Formattting plugin
	 * 
	 * @return  boolean
	 */
	public function install() {
		
		helper_ensure_confirmed( 
			plugin_lang_get( 'install_message' ), lang_get( 'plugin_install' )
		);
		
		config_set( 'plugin_format_process_text', OFF );
		config_set( 'plugin_format_process_urls', OFF );
		
		return TRUE;
	}
	
	/**
	 * Default plugin configuration.
	 * 
	 * @return  array default settings
	 */
	public function config() {
		
		return array(
			'process_markdown_text'          => ON,
			'process_markdown_email'         => OFF,
			'process_markdown_rss'           => OFF,
			'process_markdown_extra'         => OFF,
			'process_markdown_extended'      => OFF,
			'process_markdown_view_php'      => ON,
			'process_markdown_html_decode'   => OFF,
			'process_markdown_bbcode_filter' => OFF
		);
	}
	
	/**
	 * Filter string and fomrat with markdown function
	 * 
	 * @param  string  $p_string    Unformatted text
	 * @param   boolean $p_multiline Multiline text
	 * @return  string  $p_string
	 */
	public function string_process_markdown( $p_string, $p_multiline = TRUE ) {
		
		if ( 1 == plugin_config_get( 'process_markdown_extended' ) ) {
			
			// Kudos to
			// @see https://github.com/kierate/php-markdown-extra-extended
			require_once( dirname(__FILE__) . '/inc/markdown_extended.php' );
		} else if ( 1 == plugin_config_get( 'process_markdown_extra' ) ) {
			
			// Kudos to Michel Fortin
			// @see http://michelf.com/projects/php-markdown/
			require_once( dirname(__FILE__) . '/inc/markdown-extra.php' );
		} else {
			
			require_once( dirname(__FILE__) . '/inc/markdown.php' );
		}
		
		if ( 1 == plugin_config_get( 'process_markdown_extended' ) )
			$g_plugin_markdown_object = new MarkdownExtraExtended_Parser();
		else
			$g_plugin_markdown_object = new Markdown_Parser();  
		
		$t_change_quotes = FALSE;
		if ( ini_get_bool( 'magic_quotes_sybase' ) ) {
			$t_change_quotes = TRUE;
			ini_set( 'magic_quotes_sybase', FALSE );
		}
		
		// exclude, if bbcode inside string 
		if ( 1 == plugin_config_get( 'process_markdown_bbcode_filter' ) ) {
			if ( ! preg_match( '/\[*\]([^\[]*)\[/', $p_string, $matches ) )
				$p_string = $g_plugin_markdown_object->transform( $p_string, $p_multiline = TRUE );
		} else {
			$p_string = $g_plugin_markdown_object->transform( $p_string, $p_multiline = TRUE );
		}
		
		// Convert special HTML entities from Markdown-Function back to characters
		if ( 1 == plugin_config_get( 'process_markdown_html_decode' ) ) {
			$p_string = preg_replace_callback(
				'#(<code.*?>)(.*?)(</code>)#imsu',
				create_function(
					'$i',
					'return $i[1] . htmlspecialchars_decode( $i[2] ) . $i[3];'
				),
				$p_string
			);
		}
		
		if ( $t_change_quotes )
			ini_set( 'magic_quotes_sybase', TRUE );
		
		return $p_string;
	}
	
	/**
	 * Formatted text processing.
	 * 
	 * @param  string  $p_event     Event name
	 * @param  string  $p_string    Unformatted text
	 * @param  boolean $p_multiline Multiline text
	 * @return array   $p_string    Array with formatted text and multiline parameter
	 */
	public function formatted( $p_event, $p_string, $p_multiline = TRUE ) {
		
		if ( FALSE === strpos( $_SERVER['PHP_SELF'], '/view.php' ) && 1 == plugin_config_get( 'process_markdown_view_php' ) )
			return $p_string;
		
		if ( 1 == plugin_config_get( 'process_markdown_text' ) ) {
		
			if ( $this->has_markdown_tag( $p_string ) )
				$p_string = $this->string_process_markdown( $this->remove_markdown_tag($p_string), $p_multiline );
			else 
				$p_string = $this->apply_basic_formatting( $p_string, $p_multiline );
		}
		
		return $p_string;
	}
	
	private $markdown_tag = "usemarkdown";
	
	private function has_markdown_tag( $p_string ) {
		return strncmp( $p_string, $this->markdown_tag, strlen($this->markdown_tag) ) == 0;
	}
	
	private function remove_markdown_tag( $p_string ) {
		return substr( $p_string, strlen($this->markdown_tag) );
	}
	
	private function apply_basic_formatting( $p_string, $p_multiline = TRUE ) {
		$p_string = string_strip_hrefs( $p_string );
		$p_string = string_html_specialchars( $p_string );
		$p_string = string_restore_valid_html_tags( $p_string, /* multiline = */ true );

		if( $p_multiline ) {
			$p_string = string_preserve_spaces_at_bol( $p_string );
			$p_string = string_nl2br( $p_string );
		}

		$p_string = string_insert_hrefs( $p_string );

		$p_string = string_process_bug_link( $p_string );
		$p_string = string_process_bugnote_link( $p_string );
		
		return $p_string;
	}
	
	/**
	 * Plain text processing.
	 * @param string Event name
	 * @param string Unformatted text
	 * @param boolean Multiline text
	 * @return multi Array with formatted text and multiline paramater
	 */
	function text( $p_event, $p_string, $p_multiline = true ) {
		return $this->apply_basic_formatting( $p_string, $p_multiline );
	}
	
	/**
	 * RSS text processing.
	 * 
	 * @param  string  $p_event     Event name
	 * @param  string  $p_string    Unformatted text
	 * @param  boolean $p_multiline Multiline text
	 * @return array   $p_string    Array with formatted text and multiline parameter
	 */
	public function rss( $p_event, $p_string, $multiline = TRUE ) {
		
		if ( 1 == plugin_config_get( 'process_markdown_rss' ) )
			$p_string = $this->string_process_markdown( $p_string, $multiline );
		
		return $p_string;
	}

	/**
	 * Email text processing.
	 * 
	 * @param  string  $p_event     Event name
	 * @param  string  $p_string    Unformatted text
	 * @param  boolean $p_multiline Multiline text
	 * @return array   $p_string    Array with formatted text and multiline parameter
	 */
	public function email( $p_event, $p_string, $multiline = TRUE ) {
		
		if ( 1 == plugin_config_get( 'process_markdown_email' ) )
			$p_string = $this->string_process_markdown( $p_string, $multiline );
			
		return $p_string;
	}
	
} // end class
