<?php
/**
 * BP Follow Functions
 *
 * @package BP-Follow
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Start following a user's activity.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $leader_id The user ID of the person we want to follow.
 *     @type int $follower_id The user ID initiating the follow request.
 * }
 * @return bool
 */
function bp_follow_start_following( $args = '' ) {
	global $bp;

	$r = wp_parse_args( $args, array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	// existing follow already exists
	if ( ! empty( $follow->id ) ) {
		return false;
	}

	if ( ! $follow->save() ) {
		return false;
	}

	do_action_ref_array( 'bp_follow_start_following', array( &$follow ) );

	return true;
}

/**
 * Stop following a user's activity.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $leader_id The user ID of the person we want to stop following.
 *     @type int $follower_id The user ID initiating the unfollow request.
 * }
 * @return bool
 */
function bp_follow_stop_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	if ( ! $follow->delete() ) {
		return false;
	}

	do_action_ref_array( 'bp_follow_stop_following', array( &$follow ) );

	return true;
}

/**
 * Check if a user is already following another user.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $leader_id The user ID of the person we want to check.
 *     @type int $follower_id The user ID initiating the follow request.
 * }
 * @return bool
 */
function bp_follow_is_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	return apply_filters( 'bp_follow_is_following', (int)$follow->id, $follow );
}

/**
 * Fetch the user IDs of all the followers of a particular user.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $user_id The user ID to get followers for.
 * }
 * @return array
 */
function bp_follow_get_followers( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id' => bp_displayed_user_id()
	) );

	return apply_filters( 'bp_follow_get_followers', BP_Follow::get_followers( $r['user_id'] ) );
}

/**
 * Fetch all IDs that a particular user is following.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $user_id The user ID to fetch following user IDs for.
 *     @type string $follow_type The follow type
 * }
 * @return array
 */
function bp_follow_get_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id'     => bp_displayed_user_id(),
		'follow_type' => '',
	) );

	return apply_filters( 'bp_follow_get_following', BP_Follow::get_following( $r['user_id'], $r['follow_type'] ) );
}

/**
 * Get the total followers and total following counts for a user.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $user_id The user ID to grab follow counts for.
 *     @type string $follow_type The follow type
 * }
 * @return array [ followers => int, following => int ]
 */
function bp_follow_total_follow_counts( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id'     => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$count = false;

	/* try to get locally-cached values first */

	// logged-in user
	if ( empty( $r['follow_type'] ) ) {
		if ( $r['user_id'] == bp_loggedin_user_id() && is_user_logged_in() ) {
			global $bp;
	
			if ( ! empty( $bp->loggedin_user->total_follow_counts ) ) {
				$count = $bp->loggedin_user->total_follow_counts;
			}

		// displayed user
		} elseif ( $r['user_id'] == bp_displayed_user_id() && bp_is_user() ) {
			global $bp;
			
			if ( ! empty( $bp->displayed_user->total_follow_counts ) ) {
				$count = $bp->displayed_user->total_follow_counts;
			}
		}
	}

	// no cached value, so query for it
	if ( $count === false ) {
		$count = BP_Follow::get_counts( $r['user_id'], $r['follow_type'] );
	}

	return apply_filters( 'bp_follow_total_follow_counts', $count, $r['user_id'], $r );
}

/**
 * Removes follow relationships for all users from a user who is deleted or spammed
 *
 * @since 1.0.0
 *
 * @uses BP_Follow::delete_all_for_user() Deletes user ID from all following / follower records
 */
function bp_follow_remove_data( $user_id ) {
	do_action( 'bp_follow_before_remove_data', $user_id );

	BP_Follow::delete_all_for_user( $user_id );

	do_action( 'bp_follow_remove_data', $user_id );
}
add_action( 'wpmu_delete_user',	'bp_follow_remove_data' );
add_action( 'delete_user',	'bp_follow_remove_data' );
add_action( 'make_spam_user',	'bp_follow_remove_data' );

/**
 * Is an AJAX request currently taking place?
 *
 * Since BP Follow still supports BP 1.5, we can't simply use the DOING_AJAX
 * constant because BP 1.5 doesn't use admin-ajax.php for AJAX requests.  A
 * workaround is checking the "HTTP_X_REQUESTED_WITH" server variable.
 *
 * Once BP Follow drops support for BP 1.5, we can use the DOING_AJAX constant
 * as intended.
 *
 * @since 1.3.0
 *
 * @return bool
 */
function bp_follow_is_doing_ajax() {
	return ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' );
}