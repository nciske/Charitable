<?php
/**
 * Registers and performs admin actions.
 *
 * @package   Charitable/Classes/Charitable_Admin_Actions
 * @version   1.5.0
 * @author    Eric Daams
 * @copyright Copyright (c) 2017, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Charitable_Admin_Actions' ) ) :

    /**
     * Charitable_Admin_Actions
     *
     * @since 1.5.0
     */
    abstract class Charitable_Admin_Actions implements Charitable_Admin_Actions_Interface {

        /**
         * Registered actions.
         *
         * @since 1.5.0
         *
         * @var   array
         */
        private $actions;

        /**
         * Registered groups.
         *
         * @since 1.5.0
         *
         * @var   array
         */
        private $groups;

        /**
         * The result of the most recently executed action.
         *
         * @since 1.5.0
         *
         * @var   int
         */
        private $result_message;

        /**
         * Create class object.
         *
         * @since 1.5.0
         */
        public function __construct() {
            $this->actions = array();
            $this->groups  = array(
                '' => array(),
            );
        }

        /**
         * Return the array of actions.
         *
         * @since  1.5.0
         *
         * @return array
         */
        public function get_actions() {
            return $this->actions;
        }

        /**
         * Get available actions, given an object id and set of arguments.
         *
         * @since  1.5.0
         *
         * @param  int    $object_id The object ID. This could be the ID of the donation, campaign, donor, etc.
         * @param  array  $args      Optional. Mixed set of arguments.
         * @return array
         */
        public function get_available_actions( $object_id, $args = array() ) {
            $actions = array();

            foreach ( $this->actions as $action => $action_args ) {
                if ( $this->is_action_available( $action_args, $object_id, $args ) ) {
                    $actions[ $action ] = $action_args;
                }
            }

            return $actions;
        }

        /**
         * Checks whether an action is available.
         *
         * @since  1.5.0
         *
         * @param  array $action_args Action arguments.
         * @param  int   $object_id   The object ID. This could be the ID of the donation, campaign, donor, etc.
         * @param  array $args        Optional. Mixed set of arguments.
         * @return boolean
         */
        protected function is_action_available( $action_args, $object_id, $args = array() ) {
            return ! array_key_exists( 'active_callback', $action_args ) || call_user_func( $action_args['active_callback'], $object_id, $args );
        }

        /**
         * Return the array of groups.
         *
         * @since  1.5.0
         *
         * @return array
         */
        public function get_groups() {
            return $this->groups;
        }

        /**
         * Returns all groups with at least one action available.
         *
         * @since  1.5.0
         *
         * @param  int    $object_id The object ID. This could be the ID of the donation, campaign, donor, etc.
         * @param  array  $args      Optional. Mixed set of arguments.
         * @return array
         */
        public function get_available_groups( $object_id, $args = array() ) {
            $groups = array();

            foreach ( $this->groups as $group => $actions ) {
                foreach ( $actions as $action ) {
                    if ( $this->is_action_available( $this->actions[ $action ], $object_id, $args ) ) {
                        $groups[ $group ] = $actions;
                        break;
                    }
                }
            }

            return $groups;
        }

        /**
         * Get the result message.
         *
         * @since  1.5.0
         *
         * @param  string $location
         * @return string
         */
        public function show_result_message( $location ) {
            return add_query_arg( 'message', $this->result_message, $location );
        }

        /**
         * Register a new action.
         *
         * @since  1.5.0
         *
         * @param  string $action The action key. 
         * @param  array  $args   {
         *     Array of arguments for the action.
         *
         *     @type string   $label           The label to display in the admin.
         *     @type callable $callback        A callback function to run when the action is processed.
         *     @type string   $button_text     Optional. The text to show in the button when this action is selected.
         *     @type callable $active_callback Optional. Any passed callback will receive a donation ID as its only parameter
         *                                     and should return a boolean result: TRUE if the action should be shown for
         *                                     the donation; FALSE if it should not.
         *     @type int      $success_message Optional. Message to display when an action is successfully run.
         *     @type int      $failure_message Optional. Message to display when an action fails to run.
         * }
         * @param  string $group  Optional. If set, action will be added to a group of other related actions, which will be
         *                        shown as an optgroup.
         * @return boolean True if the action was registerd. False if not.
         */
        public function register( $action, $args, $group = 'default' ) {
            if ( array_key_exists( $action, $this->actions ) ) {
                return false;
            }

            if ( ! array_key_exists( 'label', $args ) || ! array_key_exists( 'callback', $args ) ) {
                return false;
            }

            /* Add the action to the group. */
            if ( ! array_key_exists( $group, $this->groups ) ) {
                $this->groups[ $group ] = array(); 
            }

            $this->groups[ $group ][] = $action;

            /* Register the action itself. */
            $this->actions[ $action ] = $args;

            return true;
        }

        /**
         * Do a particular action.
         *
         * @since  1.5.0
         *
         * @param  string $action    The action to do.
         * @param  int    $object_id The object ID. This could be the ID of the donation, campaign, donor, etc.
         * @param  array  $args      Optional. Mixed set of arguments.
         * @return boolean|WP_Error WP_Error in case of error. Mixed results if the action was performed.
         */
        public function do_action( $action, $object_id, $args = array() ) {
            if ( ! array_key_exists( $action, $this->actions ) ) {
                return new WP_Error( sprintf( __( 'Action "%s" is not registered.', 'charitable' ), $action ) );
            }

            $action_args = $this->actions[ $action ];

            if ( ! $this->is_action_available( $action_args, $object_id, $args ) ) {
                return false;
            }

            $action_hook = sprintf( 'charitable_%s_admin_action_%s', $this->get_type(), $action );

            /**
             * Register the action's callback for the hook.
             */
            add_filter( $action_hook, $action_args['callback'], 10, 2 );

            /**
             * Do something for this action and return a boolean result.
             *
             * To find the hook for a particular action, you need to know the type of action (i.e. donation, campaign)
             * and the action key. The hook is constructed like this:
             *
             * charitable_{type}_admin_action_{action key}
             *
             * @since 1.5.0
             *
             * @param int   $object_id The object ID. This could be the ID of the donation, campaign, donor, etc.
             * @param array $args      Optional. Mixed set of arguments.
             */
            $success = apply_filters( false, $action_hook, $object_id, $args );

            if ( $success && array_key_exists( 'success_message', $action_args ) ) {
                $this->result_message = $action_args['success_message'];
            }

            if ( ! $success && array_key_exists( 'failure_message', $action_args ) ) {
                $this->result_message = $action_args['failure_message'];
            }

            if ( isset( $this->result_message ) ) {
                add_action( 'redirect_post_location', array( $this, 'show_result_message' ) );
            }

            return $success;
        }
    }

endif;
