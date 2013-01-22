<?php
/*
Plugin Name: CPT Archive Decorator
Plugin URI: https://github.com/emzo/cpt-archive-decorator
Description: Editable introductory content for custom post type archives.
Version: 0.1
Author: Emyr Thomas
Author Email: emyr.thomas@gmail.com
License: WTFPL

This program is free software. It comes without any warranty, to
the extent permitted by applicable law. You can redistribute it
and/or modify it under the terms of the Do What The Fuck You Want
To Public License, Version 2, as published by Sam Hocevar. See
http://sam.zoy.org/wtfpl/COPYING for more details.

*/

class CPT_Archive_Decorator {
	public static $instance;
	public static $settings;
	
	public function __construct() {
		self::$instance = $this;
		$this->settings = array();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'loop_start', array( $this, 'decorate' ) );
		add_action( 'loop_end', array( $this, 'decorate' ) );
	}
		
	public function admin_menu() {
		$pt_args = apply_filters( 'cptad_pt_args' , array( 'public' => true, 'capability_type' => 'post', '_builtin' => false, 'has_archive' => true, 'show_ui' => true ) );
		$pts = get_post_types( $pt_args , 'names', 'and' );
	
		foreach ( $pts as $pt ) {
			$this->settings[$pt] = new CPT_Archive_Setting( $pt );
		}
	}

	public function decorate( $query ) {
		if ( ! is_post_type_archive() )
			return;

		if ( ! $query->is_main_query() )
			return;

		$settings = get_option( 'cptad-' . $query->query['post_type'] );
		//if ( ! $settings )
		//	return;

		$hook = $query->query['post_type'] . '_archive_' . current_filter();

		add_filter( $hook, 'wptexturize'        );
		add_filter( $hook, 'convert_smilies'    );
		add_filter( $hook, 'convert_chars'      );
		add_filter( $hook, 'wpautop'            );
		add_filter( $hook, 'shortcode_unautop'  );

		echo apply_filters( $hook, $settings[ current_filter() ] );
	}
}

class CPT_Archive_Setting {
	public $post_type;
	public $settings;

	public function __construct( $pt ) {
		$this->post_type = get_post_type_object( $pt );
		$this->settings = (array) get_option( 'cptad-' . $this->post_type->name );

		$page = add_submenu_page( 'edit.php?post_type=' . $this->post_type->name, 'Archive Settings', 'Archive Settings', 'manage_options', 'cptad-' . $this->post_type->name, array( $this, 'archive_submenu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'register_style' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'enqueue_style' ) );
	}

	public function register_style() {
		wp_register_style( 'cptad-stylesheet', plugins_url('css/admin.css', __FILE__) );
	}

	function enqueue_style() {
		wp_enqueue_style( 'cptad-stylesheet' );
	}

	public function archive_submenu_page() {
		?>
		<div class="wrap">
			<div id="icon-edit" class="icon32 icon32-posts-<?php echo $this->post_type->name; ?>"><br></div>
			<h2><?php echo $this->post_type->labels->singular_name; ?> Archive Settings</h2>
			<form action="options.php" method="POST">
				<?php settings_fields( 'cptad-' . $this->post_type->name . '-setting-group' ); ?>
				<?php do_settings_sections( 'cptad-' . $this->post_type->name ); ?>
				<?php submit_button(); ?>
 			</form>
		</div>
		<?php
	}

	public function register_settings() {
		register_setting( 'cptad-' . $this->post_type->name . '-setting-group', 'cptad-' . $this->post_type->name, array( $this, 'sanitize' ) );

		// field for rich content to display before the loop
		add_settings_section( 'section-loop_start', 'Before Archive', array( $this, 'section_loop_start_callback' ), 'cptad-' . $this->post_type->name );
		add_settings_field( 'field-loop_start', 'Field One', array( $this, 'field_loop_start_callback' ), 'cptad-' . $this->post_type->name, 'section-loop_start' );

		// field for rich content to display after the loop
		add_settings_section( 'section-loop_end', 'After Archive', array( $this, 'section_loop_end_callback' ), 'cptad-' . $this->post_type->name );
		add_settings_field( 'field-loop_end', false, array( $this, 'field_loop_end_callback' ), 'cptad-' . $this->post_type->name, 'section-loop_end' );
	}

	public function sanitize( $input ) {
		foreach ( $input as $k => &$v ) {
			$v = wp_kses_post( $v );
		}
		unset( $v );
		return $input;
	}

	public function section_loop_start_callback() {
		?>
    	Content to be displayed immediately before the <?php echo $this->post_type->labels->singular_name; ?> loop.
    	<?php
	}

	public function field_loop_start_callback() {
    	$loop_start = esc_attr( $this->settings['loop_start'] );
    	wp_editor( $loop_start, 'cptad-' . $this->post_type->name . '[loop_start]');
	}

	public function section_loop_end_callback() {
		?>
    	Content to be displayed immediately after the <?php echo $this->post_type->labels->singular_name; ?> loop.
    	<?php
	}

	public function field_loop_end_callback() {
    	$loop_end = esc_attr( $this->settings['loop_end'] );
    	wp_editor( $loop_end, 'cptad-' . $this->post_type->name . '[loop_end]');
	}
}

new CPT_Archive_Decorator;

