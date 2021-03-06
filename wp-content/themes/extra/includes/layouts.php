<?php
// Prevent file from being loaded directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

define( 'EXTRA_HOME_LAYOUT_META_KEY', '_extra_layout_home' );
define( 'EXTRA_DEFAULT_LAYOUT_META_KEY', '_extra_layout_default' );

function extra_et_builder_post_types( $post_types ){
	return array_merge( $post_types, array(
		EXTRA_LAYOUT_POST_TYPE,
	) );
}

add_filter( 'et_builder_post_types', 'extra_et_builder_post_types' );

function extra_et_builder_always_enabled( $bool, $post_type, $post ) {
	if ( EXTRA_LAYOUT_POST_TYPE == $post_type ) {
		$bool = true;
	}
	return $bool;
}

add_filter( 'et_builder_always_enabled', 'extra_et_builder_always_enabled', 10, 3 );

function extra_et_builder_row_settings_controls( $output ) {
	global $typenow;

	if ( 'layout' == $typenow ) {
		$output = str_replace( '<a href="#" class="et-pb-settings et-pb-settings-row"><span>Settings</span></a>', '', $output );

	}
	return $output;
}

add_filter( 'et_builder_row_settings_controls', 'extra_et_builder_row_settings_controls' );

function extra_text_module_post_types( $post_types, $module_slug ) {
	if ( 'et_builder_text' == $module_slug ) {
		$post_types = array(
			EXTRA_LAYOUT_POST_TYPE,
			'post',
		);
	}

	return $post_types;
}

add_filter( 'et_builder_module_post_types', 'extra_text_module_post_types', 10, 2 );

function extra_text_module_layout_classes( $classes ) {
	if ( !is_singular( 'post' ) ) {
		$classes[] = 'boxy';
	}

	return $classes;
}

add_filter( 'et_builder_module_classes_et_builder_text', 'extra_text_module_layout_classes' );

function extra_text_module_layout_fields( $fields ) {
	global $post;

	if ( EXTRA_LAYOUT_POST_TYPE == $post->post_type ) {

		$fields_to_remove = array(
			'use_background_color',
			'background_color',
			'background_image',
			'dropcap',
			'breakout',
		);

		foreach ($fields as $field_key => $field) {
			if ( in_array( $field_key, $fields_to_remove ) ) {
				unset( $fields[$field_key] );
			}
		}
	}

	return $fields;
}

add_filter( 'et_builder_module_fields_et_builder_text', 'extra_text_module_layout_fields' );

function extra_text_module_layout_border_field( $field ) {
	global $post;

	if ( EXTRA_LAYOUT_POST_TYPE == $post->post_type ) {
		$field['label'] = esc_html__( 'Show Top Border?', 'extra' );

		$field['options'] = array(
			'none' => esc_html__( 'No', 'extra' ),
			'top'  => esc_html__( 'Yes', 'extra' ),
		);

		$field['description'] = esc_html__( 'This will add a border to the top side of the module.', 'extra' );
	}
	return $field;
}

add_filter( 'et_builder_module_fields_et_builder_text_field_border', 'extra_text_module_layout_border_field' );

function extra_get_layouts( $args = array() ) {
	$default_args = array(
		'post_type' => EXTRA_LAYOUT_POST_TYPE,
	);

	$args = wp_parse_args( $args, $default_args );

	return new WP_Query( $args );
}

function extra_home_layout() {
	global $et_builder_post_type;

	$et_builder_post_type = EXTRA_LAYOUT_POST_TYPE;
	echo do_shortcode( et_pb_fix_shortcodes( extra_get_home_layout() ) );
}

function _et_extra_get_home_layout() {
	global $wp_customize;

	$args = array(
		'meta_key'       => EXTRA_HOME_LAYOUT_META_KEY,
		'meta_value'     => 1,
		'posts_per_page' => 1,
	);

	if ( extra_is_customizer_request() ) {
		$show_on_front_layout = $wp_customize->get_setting( 'show_on_front_layout' )->post_value();
		if ( !empty( $show_on_front_layout ) ) {
			$args = array(
				'post__in' => array( $show_on_front_layout ),
			);
		}
	}

	$layouts = extra_get_layouts( $args );

	return !empty( $layouts->posts ) ? $layouts->posts[0] : false;
}

// filters the page id for Divi Builder settings to apply them correctly on homepage
function et_pb_set_home_page_id() {
	$layout_id = extra_get_home_layout_id();

	return $layout_id;
}

// filters the page id for Divi Builder settings to apply them correctly on tax pages
function et_pb_set_tax_page_id() {
	$layout_id = extra_get_tax_layout_id();

	return $layout_id;
}

function extra_get_home_layout() {
	// add filter to define the correct page id in Page Builder Settings
	add_filter( 'et_pb_page_id_custom_css', 'et_pb_set_home_page_id' );
	return ( $layout = _et_extra_get_home_layout() ) ? $layout->post_content : extra_default_layout();
}

function extra_get_home_layout_id() {
	return ( $layout = _et_extra_get_home_layout() ) ? $layout->ID : extra_get_default_layout_id();
}

function extra_modify_archive_query( $post_id ) {
	if ( is_home() ) {
		$home_layout_id = extra_get_home_layout_id();

		if ( $home_layout_id ) {
			return $home_layout_id;
		}
	}

	if ( ( is_category() || is_tag() ) ) {
		$layout_id = extra_get_tax_layout_id();

		if ( $layout_id ) {
			return $layout_id;
		}
	}

	return $post_id;
}
add_filter( 'et_is_ab_testing_active_post_id', 'extra_modify_archive_query' );

function _extra_get_default_layout() {
	$args = array(
		'meta_key'       => EXTRA_DEFAULT_LAYOUT_META_KEY,
		'meta_value'     => 1,
		'posts_per_page' => 1,
	);

	$layouts = extra_get_layouts( $args );

	if ( !empty( $layouts->posts ) ) {
		return $layouts->posts[0];
	} else {
		return false;
	}
}

function extra_get_default_layout_id() {
	return ( $layout = _extra_get_default_layout() ) ? $layout->ID : false;
}

function extra_default_layout() {
	return ( $layout = _extra_get_default_layout() ) ? $layout->post_content : '';
}

function extra_tax_layout() {
	$layout = extra_get_tax_layout();
	if ( !empty( $layout ) ) {
		// add filter to define the correct page id in Page Builder Settings
		add_filter( 'et_pb_page_id_custom_css', 'et_pb_set_tax_page_id' );
		echo do_shortcode( et_pb_fix_shortcodes( $layout ) );
	} else {
		require locate_template( 'index-content.php' );
	}
}

function _et_extra_get_tax_layout() {
	global $wp_query;

	$args = array(
		'posts_per_page' => 1,
	);

	if ( is_category() ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'id',
				'terms'    => array( get_query_var( 'cat' ) ),
				'operator' => 'IN',
			),
		);
	} elseif ( is_tax() ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'tag',
				'field'    => 'id',
				'terms'    => array( get_query_var( 'tag' ) ),
				'operator' => 'IN',
			),
		);
	} else {
		return false;
	}

	$layouts = extra_get_layouts( $args );

	return !empty( $layouts->posts ) ? $layouts->posts[0] : false;
}

function extra_get_tax_layout() {
	return ( $layout = _et_extra_get_tax_layout() ) ? $layout->post_content : extra_default_layout();
}

function extra_get_tax_layout_id() {
	return ( $layout = _et_extra_get_tax_layout() ) ? $layout->ID : extra_get_default_layout_id();
}

function extra_admin_bar_layout_edit( $wp_admin_bar ) {
	if ( !current_user_can( 'edit_pages' ) ) {
		return;
	}

	if ( is_home() && et_extra_show_home_layout() ) {
		$layout_id = extra_get_home_layout_id();
	} else if ( is_category() ) {
		$layout_id = extra_get_tax_layout_id();
	}

	if ( empty( $layout_id ) ) {
		return;
	}

	$wp_admin_bar->add_node( array(
		'id'     => 'edit-layout',
		'parent' => false,
		'title'  => esc_html__( 'Edit Layout', 'extra' ),
		'href'   => admin_url( 'post.php?post=' . $layout_id . '&action=edit' ),
	));
}

add_action( 'admin_bar_menu', 'extra_admin_bar_layout_edit', 100 );

function extra_layout_menu_home_layout_link() {
	if ( is_admin() ) {
		return;
	}

	$home_layout_id = extra_get_home_layout_id();
	$default_layout_id = extra_get_default_layout_id();

	if ( !empty( $home_layout_id ) ) {
		$pagehook = add_submenu_page(
			'edit.php?post_type=' . EXTRA_LAYOUT_POST_TYPE,
			__( 'Edit Home Layout', 'extra' ),
			__( 'Edit Home Layout', 'extra' ),
			'edit_pages',
			'post.php?post=' . $home_layout_id . '&action=edit'
		);
	}

	if ( !empty( $default_layout_id ) ) {
		$pagehook = add_submenu_page(
			'edit.php?post_type=' . EXTRA_LAYOUT_POST_TYPE,
			__( 'Edit Default Layout', 'extra' ),
			__( 'Edit Default Layout', 'extra' ),
			'edit_pages',
			'post.php?post=' . $default_layout_id . '&action=edit'
		);
	}
}

add_action( 'admin_menu', 'extra_layout_menu_home_layout_link' );

function extra_layout_used() {
	if ( is_home() && et_extra_show_home_layout() ) {
		return true;
	} else if ( is_category() || is_tag() ) {
		if ( extra_get_tax_layout_id() ) {
			return true;
		}
	}
	return false;
}

function et_pb_is_pagebuilder_used( $page_id ) {
	return ( 'on' === get_post_meta( $page_id, '_et_pb_use_builder', true ) );
}

function extra_hide_use_default_editor_button() {
	global $post, $pagenow;

	$post_types = apply_filters( 'extra_hide_use_default_editor_button', array( EXTRA_LAYOUT_POST_TYPE ) );

	if ( ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) && isset( $post->post_type ) && in_array( $post->post_type, $post_types ) ) {
		wp_add_inline_style( 'et_pb_admin_css', '#et_pb_toggle_builder { display: none !important; }' );
	}
}

add_action( 'admin_enqueue_scripts', 'extra_hide_use_default_editor_button', 11 );
