<?php
/**
 * Ajax plugin configuration
 *
 * @author        Webcraftic <wordpress.webraftic@gmail.com>
 * @copyright (c) 2017 Webraftic Ltd
 * @version       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This action allows you to process Ajax requests to activate external components Clearfy
 */
function wtitan_update_component() {
	check_ajax_referer( 'updates' );

	$slug    = WBCR\Titan\Plugin::app()->request->post( 'plugin', null, true );
	$action  = WBCR\Titan\Plugin::app()->request->post( 'plugin_action', null, true );
	$storage = WBCR\Titan\Plugin::app()->request->post( 'storage', null, true );

	if ( ! WBCR\Titan\Plugin::app()->currentUserCan() ) {
		wp_die( __( 'You don\'t have enough capability to edit this information.', 'titan-security' ), __( 'Something went wrong.' ), 403 );
	}

	if ( empty( $slug ) || empty( $action ) ) {
		wp_send_json_error( [ 'error_message' => __( 'Required attributes are not passed or empty.', 'titan-security' ) ] );
	}
	$success   = false;
	$send_data = [];

	if ( $storage == 'internal' ) {
		if ( $action == 'activate' ) {
			if ( WBCR\Titan\Plugin::app()->activateComponent( $slug ) ) {
				$success = true;
			}
		} else if ( $action == 'deactivate' ) {

			if ( WBCR\Titan\Plugin::app()->deactivateComponent( $slug ) ) {
				$success = true;
			}
		} else {
			wp_send_json_error( [ 'error_message' => __( 'You are trying to perform an invalid action.', 'titan-security' ) ] );
		}
	} else if ( $storage == 'wordpress' ) {
		if ( ! empty( $slug ) ) {
			$network_wide = WBCR\Titan\Plugin::app()->isNetworkActive();

			if ( $action == 'activate' ) {
				$result = activate_plugin( $slug, '', $network_wide );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( [ 'error_message' => $result->get_error_message() ] );
				}
			} else if ( $action == 'deactivate' ) {
				deactivate_plugins( $slug, false, $network_wide );
			}

			$success = true;
		}
	}

	if ( $action == 'install' || $action == 'deactivate' ) {
		try {
			// Delete button
			$delete_button              = WBCR\Titan\Plugin::app()->getDeleteComponentsButton( $storage, $slug );
			$send_data['delete_button'] = $delete_button->getButton();
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'error_message' => $e->getMessage() ] );
		}
	}

	// Если требуется обновить постоянные ссылки, даем сигнал, что пользователю, нужно показать
	// всплывающее уведомление.
	// todo: сделать более красивое решение с передачей текстовых сообщений
	/*if ( $action == 'deactivate' ) {
		$is_need_rewrite_rules = WBCR\Titan\Plugin::app()->getPopulateOption( 'need_rewrite_rules' );
		if ( $is_need_rewrite_rules ) {
			$send_data['need_rewrite_rules'] = sprintf( '<span class="wbcr-clr-need-rewrite-rules-message">' . __( 'When you deactivate some components, permanent links may work incorrectly. If this happens, please, <a href="%s">update the permalinks</a>, so you could complete the deactivation.', 'titan-security' ), admin_url( 'options-permalink.php' ) . '</span>' );
		}
	}*/

	if ( $success ) {
		do_action( 'wtitan_update_component', $slug, $action, $storage );

		wp_send_json_success( $send_data );
	}

	wp_send_json_error( [ 'error_message' => __( 'An unknown error occurred during the activation of the component.', 'titan-security' ) ] );
}

add_action( 'wp_ajax_wtitan-update-component', 'wtitan_update_component' );

/**
 * Ajax event that calls the wbcr/titan/activated_component action,
 * to get the component to work. Usually this is a call to the installation functions,
 * but in some cases, overwriting permanent references or compatibility checks.
 */
function wtitan_prepare_component() {
	check_ajax_referer( 'updates' );

	$component_name = WBCR\Titan\Plugin::app()->request->post( 'plugin', null, true );

	if ( ! WBCR\Titan\Plugin::app()->currentUserCan() ) {
		wp_send_json_error( [ 'error_message' => __( 'You don\'t have enough capability to edit this information.', 'titan-security' ) ], 403 );
	}

	if ( empty( $component_name ) ) {
		wp_send_json_error( [ 'error_message' => __( 'Required attribute [component_name] is empty.', 'titan-security' ) ] );
	}

	do_action( 'wbcr/titan/activated_component', $component_name );

	wp_send_json_success();
}

add_action( 'wp_ajax_wtitan-prepare-component', 'wtitan_prepare_component' );