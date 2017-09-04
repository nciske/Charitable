<?php
/**
 * Class that sets up the emails.
 *
 * @version   1.5.0
 * @package   Charitable/Classes/Charitable_Emails
 * @author    Eric Daams
 * @copyright Copyright (c) 2017, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Charitable_Emails' ) ) :

	/**
	 * Charitable_Emails
	 *
	 * @since 1.0.0
	 */
	class Charitable_Emails {

		/**
		 * The single instance of this class.
		 *
		 * @var Charitable_Emails|null
		 */
		private static $instance = null;

		/**
		 * All available emails.
		 *
		 * @var string[]
		 */
		private $emails;

		/**
		 * The email currently being rendered.
		 *
		 * @var Charitable_Email
		 */
		private $current_email;

		/**
		 * Set up the class.
		 *
		 * Note that the only way to instantiate an object is with the charitable_start method,
		 * which can only be called during the start phase. In other words, don't try
		 * to instantiate this object.
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			/* Register email shortcode */
			add_shortcode( 'charitable_email', array( $this, 'email_shortcode' ) );

			/* 3rd party hook for overriding anything we've done above. */
			do_action( 'charitable_emails_start', $this );
		}

		/**
		 * Returns and/or create the single instance of this class.
		 *
		 * @since  1.2.0
		 *
		 * @return Charitable_Emails
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new Charitable_Emails();
			}

			return self::$instance;
		}

		/**
		 * Register Charitable emails.
		 *
		 * @since  1.0.0
		 *
		 * @return string[]
		 */
		public function register_emails() {
			$this->emails = apply_filters( 'charitable_emails', array(
				'donation_receipt'              => 'Charitable_Email_Donation_Receipt',
				'new_donation'                  => 'Charitable_Email_New_Donation',
				'campaign_end'                  => 'Charitable_Email_Campaign_End',
				'password_reset'                => 'Charitable_Email_Password_Reset',
				'offline_donation_receipt'      => 'Charitable_Email_Offline_Donation_Receipt',
				'offline_donation_notification' => 'Charitable_Email_Offline_Donation_Notification',
			) );

			return $this->emails;
		}

		/**
		 * Register admin actions for default emails.
		 *
		 * @since  1.5.0
		 *
		 * @return void
		 */
		public function register_admin_actions() {
			$donation_actions = charitable_get_donation_actions();

			/**
			 * Filter the list of resendable donation emails.
			 *
			 * @since 1.5.0
			 *
			 * @param string[] $emails The list of emails and their label.
			 */
			$emails = array(
				'donation_receipt',
				'new_donation',
				'offline_donation_receipt',
				'offline_donation_notification',
			);

			foreach ( $emails as $email )  {
				$class  = $this->get_email( $email );
				$object = new $class;

				$donation_actions->register( 'resend_' . $email, array(
					'label'           => $object->get_name(),
					'callback'        => array( $class, 'resend' ),
					'button_text'     => __( 'Resend Email', 'charitable' ),
					'active_callback' => array( $class, 'can_be_resent' ),
					'success_message' => 11,
					'failed_message'  => 12,
				), __( 'Resend Donation Emails', 'charitable' ) );
			}
		}

		/**
		 * Receives a request to enable or disable an email and validates it before passing it off.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function handle_email_settings_request() {
			if ( ! wp_verify_nonce( $_REQUEST['_nonce'], 'email' ) ) {
				wp_die( __( 'Cheatin\' eh?!', 'charitable' ) );
			}

			$email = isset( $_REQUEST['email_id'] ) ? $_REQUEST['email_id'] : false;

			/* Gateway must be set */
			if ( false === $email ) {
				wp_die( __( 'Missing email.', 'charitable' ) );
			}

			/* Validate email. */
			if ( ! isset( $this->emails[ $email ] ) ) {
				wp_die( __( 'Invalid email.', 'charitable' ) );
			}

			/* All good, so disable or enable the email */
			if ( 'charitable_disable_email' == current_filter() ) {
				$this->disable_email( $email );
			} else {
				$this->enable_email( $email );
			}
		}

		/**
		 * Returns all available emails.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_available_emails() {
			return $this->emails;
		}

		/**
		 * Returns the currently enabled emails.
		 *
		 * @since  1.0.0
		 *
		 * @return string[]
		 */
		public function get_enabled_emails() {
			$enabled = charitable_get_option( 'enabled_emails', array() );

			/* The Password Reset email is always enabled. */
			$enabled['password_reset'] = 'Charitable_Email_Password_Reset';

			return $enabled;
		}

		/**
		 * Returns a list of the names of currently enabled emails.
		 *
		 * @since  1.3.0
		 *
		 * @return string[]
		 */
		public function get_enabled_emails_names() {
			$emails = array();

			foreach ( $this->get_enabled_emails() as $class ) {				
				if ( ! class_exists( $class ) ) {
					continue;
				}

				$email              = new $class;
				$emails[$email::ID] = $email->get_name();
			}

			return $emails;
		}

		/**
		 * Return the email class name for a given email.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $email The email to return.
		 * @return string|false A string representing the email class name if the
		 *                      email is registered; false if it is not registered.
		 */
		public function get_email( $email ) {
			return isset( $this->emails[ $email ] ) ? $this->emails[ $email ] : false;
		}

		/**
		 * Returns whether the passed email is enabled.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $email_id The email to check for.
		 * @return boolean
		 */
		public function is_enabled_email( $email_id ) {
			return array_key_exists( $email_id, $this->get_enabled_emails() );
		}

		/**
		 * Register email settings fields.
		 *
		 * @since  1.0.0
		 *
		 * @param  array            $settings Settings for this email.
		 * @param  Charitable_Email $email    The email's helper object.
		 * @return array
		 */
		public function register_email_settings( $settings, Charitable_Email $email ) {
			add_filter( 'charitable_settings_fields_emails_email_' . $email->get_email_id(), array( $email, 'add_recipients_field' ) );

			return $email->email_settings( $settings );
		}

		/**
		 * Set the email currently being rendered/sent.
		 *
		 * This is executed before an email is prepared for send/preview. Setting
		 * the email is required in order to allow the shortcode function (below)
		 * to gather the correct information to display.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Email $email A `Charitable_Email` object.
		 * @return void
		 */
		public function set_current_email( Charitable_Email $email ) {
			$this->current_email = $email;
		}

		/**
		 * Handles the parsing of the [charitable_email] shortcode.
		 *
		 * @since  1.0.0
		 *
		 * @param  mixed[] $atts Mixed shortcode attributes.
		 * @return string
		 */
		public function email_shortcode( $atts = array() ) {
			$defaults = array(
				'show' => '',
			);

			$args = apply_filters( 'charitable_email_shortcode_args', wp_parse_args( $atts, $defaults ), $atts, $defaults );

			if ( ! isset( $args['show'] ) ) {
				return '';
			}

			return $this->current_email->get_value( $args['show'], $args );
		}

		/**
		 * Enable an email.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $email The email to be enabled.
		 * @return void
		 */
		protected function enable_email( $email ) {
			$settings                   = get_option( 'charitable_settings' );
			$enabled_emails             = isset( $settings['enabled_emails'] ) ? $settings['enabled_emails'] : array();
			$enabled_emails[ $email ]   = $this->emails[ $email ];
			$settings['enabled_emails'] = $enabled_emails;

			update_option( 'charitable_settings', $settings );

			Charitable_Settings::get_instance()->add_update_message( __( 'Email enabled', 'charitable' ), 'success' );

			do_action( 'charitable_email_enable', $email );
		}

		/**
		 * Disable an email.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $email The email to be disabled.
		 * @return void
		 */
		protected function disable_email( $email ) {
			$settings = get_option( 'charitable_settings' );

			if ( ! isset( $settings['enabled_emails'][ $email ] ) ) {
				return;
			}

			unset( $settings['enabled_emails'][ $email ] );

			update_option( 'charitable_settings', $settings );

			Charitable_Settings::get_instance()->add_update_message( __( 'Email disabled', 'charitable' ), 'success' );

			do_action( 'charitable_email_disable', $email );
		}
	}

endif;
