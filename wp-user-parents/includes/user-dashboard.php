<?php

/**
 * Parents User Dashboard
 *
 * @package Plugins/User/Parents/Dashboard
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Filter sections and add "Children" section
 *
 * @since 0.1.0
 *
 * @param array $sections
 */
function wp_user_parents_add_section( $sections = array() ) {

	// Bail if cannot have or view children
	if ( ! current_user_can( 'have_user_children' ) || ! current_user_can( 'view_user_children' ) ) {
		return $sections;
	}

	// Events
	$sections[] = array(
		'id'           => 'children',
		'slug'         => 'children',
		'url'          => '',
		'label'        => esc_html__( 'Children', 'wp-user-parents' ),
		'show_in_menu' => true,
		'order'        => 150
	);

	// Return sections
	return $sections;
}

/**
 * Maybe add a child from the "Children" section
 *
 * @since 0.1.0
 */
function wp_user_parents_add_child() {

	// Bail if no signup nonce
	if ( empty( $_REQUEST['signup_nonce'] ) ) {
		return;
	}

	// Bail if nonce fails
	if ( ! wp_verify_nonce( $_REQUEST['signup_nonce'], 'wp_user_dashboard_child_signup' ) ) {
		return;
	}

	// Bail if current user cannot have children
	if ( ! current_user_can( 'have_user_children' ) ) {
		return;
	}

	// Sanitize fields
	$email     = ! empty( $_REQUEST['email']     ) ? $_REQUEST['email']     : '';
	$firstname = ! empty( $_REQUEST['firstname'] ) ? $_REQUEST['firstname'] : '';
	$lastname  = ! empty( $_REQUEST['lastname']  ) ? $_REQUEST['lastname']  : '';
	$password  = ! empty( $_REQUEST['password']  ) ? $_REQUEST['password']  : '';
	$username  = ! empty( $_REQUEST['username']  ) ? $_REQUEST['username']  : "{$firstname}-{$lastname}";

	// Names are empty
	if ( empty( $firstname ) || empty( $lastname ) || strlen( $firstname ) < 2 || strlen( $lastname ) < 2 ) {
		wp_user_parents_dashboard_redirect( array( 'error' => 'name' ) );
	}

	// Password validation
	if ( empty( $password ) ) {
		wp_user_parents_dashboard_redirect( array( 'error' => 'password' ) );
	}

	// Username validation
	$username = preg_replace( '/\s+/', '', sanitize_user( $username, true ) );
	$username = esc_html( sanitize_key( $username ) );
	if ( empty( $username ) || username_exists( $username ) || strlen( $username ) < 4 ) {
		wp_user_parents_dashboard_redirect( array( 'error' => 'username' ) );
	}

	// Email validation
	$email = sanitize_email( $email );
	if ( empty( $email ) || ! is_email( $email ) || email_exists( $email ) ) {
		wp_user_parents_dashboard_redirect( array( 'error' => 'email' ) );
	}

	// Filter the default child role
	$role = apply_filters( 'wp_user_parents_default_role', get_option( 'default_role' ) );

	// Requires activation
	if ( is_multisite() && apply_filters( 'wp_join_page_requires_activation', true ) ) {
		wpmu_signup_user( $username, $email, array(
			'add_to_blog' => get_current_blog_id(),
			'new_role'    => $role,
			'first_name'  => $firstname,
			'last_name'   => $lastname,
		) );
	}

	// User validation
	$user_id = wp_create_user( $username, $password, $email );
	if ( empty( $user_id ) ) {
		wp_user_parents_dashboard_redirect( array( 'error' => 'unknown' ) );
	}

	// Get new userdata
	$user = new WP_User( $user_id );

	// Newly created users have no roles or caps until they are added to a blog
	delete_user_option( $user->ID, 'capabilities' );
	delete_user_option( $user->ID, 'user_level'   );

	// Add default role
	$user->add_role( $role );

	// Save fullname to usermeta
	update_user_meta( $user->ID, 'first_name', $firstname );
	update_user_meta( $user->ID, 'last_name',  $lastname  );

	// Get the current user ID
	$current_user_id = get_current_user_id();

	// Add the relationship to usermeta
	add_user_meta( $user->ID, 'user_parent', $current_user_id, false );

	// Do action
	do_action( 'wp_user_parents_added_child', $user, $current_user_id );

	// Redirect
	wp_user_parents_dashboard_redirect( array( 'success' => 'yay' ) );
}

/**
 * Redirect user back to children page, with feedback
 *
 * @since 0.1.0
 *
 * @param array $args
 */
function wp_user_parents_dashboard_redirect( $args = array() ) {
	$url      = wp_get_user_dashboard_url( 'children' );
	$redirect = add_query_arg( $args, $url );
	wp_safe_redirect( $redirect );
	die;
}
