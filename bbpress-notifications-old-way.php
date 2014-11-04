<?php
/*
Plugin Name: bbPress notifications the old way
Plugin URI: http://sfndesign.ca
Description: Stops bbPress from adding forum notifications via BCC email's
Version: 1.0
Author: SFNdesign, Curtis McHale
Author URI: http://sfndesign.ca
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class bbPress_Notifications_Old_Way{

	function __construct(){

		add_action( 'admin_notices', array( $this, 'check_required_plugins' ) );

		add_action( 'init', array( $this, 'kill_bbp_notifications' ) );

		add_action( 'bbp_new_reply', array( $this, 'old_way_reply' ), 11, 5 );
		add_action( 'bbp_new_topic', array( $this, 'old_way_topic_notification' ), 11, 4 );

		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

	} // construct

	public function old_way_topic_notification( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0 ){

		// Bail if subscriptions are turned off
		if ( !bbp_is_subscriptions_active() ) {
			return false;
		}

		/** Validation ************************************************************/

		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );

		/** Topic *****************************************************************/

		// Bail if topic is not published
		if ( ! bbp_is_topic_published( $topic_id ) ) {
			return false;
		}

		// Poster name
		$topic_author_name = bbp_get_topic_author_display_name( $topic_id );

		/** Mail ******************************************************************/

		// Remove filters from reply content and topic title to prevent content
		// from being encoded with HTML entities, wrapped in paragraph tags, etc...
		remove_all_filters( 'bbp_get_topic_content' );
		remove_all_filters( 'bbp_get_topic_title'   );

		// Strip tags from text and setup mail data
		$topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
		$topic_content = strip_tags( bbp_get_topic_content( $topic_id ) );
		$topic_url     = get_permalink( $topic_id );
		$blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$do_not_reply  = '<noreply@' . ltrim( get_home_url(), '^(http|https)://' ) . '>';

		$message = $topic_author_name . ' wrote:<br />'.

		           $topic_content . '<br />'.

		           'Post link: '. $topic_url .'<br/>' .

		           ' ';

		// For plugins to filter titles per reply/topic/user
		$subject = apply_filters( 'bbp_forum_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $topic_id, $forum_id, $user_id );
		if ( empty( $subject ) ) {
			return;
		}

		// Get topic subscribers and bail if empty
		$user_ids = bbp_get_forum_subscribers( $forum_id, true );
		if ( empty( $user_ids ) ) {
			return false;
		}

		foreach( $user_ids as $user_id ){

			// Don't send notifications to the person who made the post
			if ( !empty( $topic_author ) && (int) $user_id === (int) $topic_author ) {
				continue;
			}

			wp_mail( get_userdata( $user_id )->user_email, $subject, $message );

		}

		return true;

	} // old_way_topic_notification

	/**
	 * Loops through and sends individual emails for notifications
	 *
	 * Basically a copy of bbp_notify_subscribers but it stops the BCC part because that can be broken. https://bbpress.org/forums/topic/cc-instead-of-bcc-in-notification-emails/
	 *
	 * @param int       $reply_id       required        The ID of the current reply trigering the notification
	 * @param int       $topic_id       required        The topic we're notifying for
	 * @param int       $forum_id       required        The forum the topic is in
	 * @param bool      $anonymous_data ??              ???? I have no idea and I didn't look it up and the original doesn't have it
	 * @param int       $reply_author   required        User_id for the user whose reply triggered the notification
	 *
	 * @return bool
	 */
	public function old_way_reply( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0 ){

		// Bail if subscriptions are turned off
		if ( !bbp_is_subscriptions_active() ) {
			return false;
		}

		/** Validation ************************************************************/

		$reply_id = bbp_get_reply_id( $reply_id );
		$topic_id = bbp_get_topic_id( $topic_id );
		$forum_id = bbp_get_forum_id( $forum_id );

		/** Topic *****************************************************************/

		// Bail if topic is not published
		if ( !bbp_is_topic_published( $topic_id ) ) {
			return false;
		}

		/** Reply *****************************************************************/

		// Bail if reply is not published
		if ( !bbp_is_reply_published( $reply_id ) ) {
			return false;
		}

		// Poster name
		$reply_author_name = bbp_get_reply_author_display_name( $reply_id );

		/** Mail ******************************************************************/

		// Remove filters from reply content and topic title to prevent content
		// from being encoded with HTML entities, wrapped in paragraph tags, etc...
		remove_all_filters( 'bbp_get_reply_content' );
		remove_all_filters( 'bbp_get_topic_title'   );

		// Strip tags from text and setup mail data
		$topic_title   = strip_tags( bbp_get_topic_title( $topic_id ) );
		$reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
		$reply_url     = bbp_get_reply_url( $reply_id );
		$blog_name     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$do_not_reply  = '<noreply@' . ltrim( get_home_url(), '^(http|https)://' ) . '>';

		$message = $reply_author_name . ' wrote:<br />'.

		           $reply_content . '<br />'.

		           'Post link: '. $reply_url .'<br/>' .

		' ';

		// For plugins to filter titles per reply/topic/user
		$subject = apply_filters( 'bbp_subscription_mail_title', '[' . $blog_name . '] ' . $topic_title, $reply_id, $topic_id );
		if ( empty( $subject ) ) {
			return;
		}

		$user_ids = bbp_get_topic_subscribers( $topic_id, true );
		if ( empty( $user_ids ) ){
			return false;
		}

		foreach( (array) $user_ids as $user_id ){

			// Don't send notifications to the person who made the post
			if ( !empty( $reply_author ) && (int) $user_id === (int) $reply_author ) {
				continue;
			}

			wp_mail( get_userdata( $user_id )->user_email, $subject, $message );
		}

		return true;
	}

	/**
	 * Unhooks the default bbPress notifications
	 *
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 * @access public
	 *
	 * @uses remove_action()            Removes WP action given hook and function and matching params
	 */
	public function kill_bbp_notifications(){
		remove_action( 'bbp_new_reply', 'bbp_notify_subscribers', 11, 5 );
		remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 );
	}

	/**
	 * Checks for plugin requirements and deactivates plugin if requirements not found
	 *
	 * @since   1.0
	 * @author  SFNdesign, Curtis McHale
	 *
	 * @uses is_plugin_active()             Returns true if the given plugin is active
	 * @uses deactivate_plugins()           Deactivates plugins given string or array of plugins
	 */
	public function check_required_plugins(){

		if( ! is_plugin_active( 'bbpress/bbpress.php' ) ){ ?>

			<div id="message" class="error">
				<p>bbPress Notifications the Old Way expects bbpress to be active. This plugin has been deactivated.</p>
			</div>

			<?php
			deactivate_plugins( '/bbpress-notifications-old-way/bbpress-notifications-old-way.php' );
		}

	} // check_required_plugins

	/**
	 * Fired when plugin is activated
	 *
	 * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
	 */
	public function activate( $network_wide ){

	} // activate

	/**
	 * Fired when plugin is deactivated
	 *
	 * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
	 */
	public function deactivate( $network_wide ){

	} // deactivate

	/**
	 * Fired when plugin is uninstalled
	 *
	 * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
	 */
	public function uninstall( $network_wide ){

	} // uninstall

} // bbPress_Notifications_Old_Way

new bbPress_Notifications_Old_Way();
