<?php
/**
 * Sets up the donation metaboxes.
 *
 * @package     Charitable/Classes/Charitable_Donation_Metaboxes
 * @version     1.5.0
 * @author      Eric Daams
 * @copyright   Copyright (c) 2017, Studio 164a
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Charitable_Donation_Metaboxes' ) ) :

	/**
	 * Charitable_Donation_Metaboxes class.
	 *
	 * @final
	 * @since 1.5.0
	 */
	final class Charitable_Donation_Metaboxes {

		/**
		 * The single instance of this class.
		 *
		 * @var     Charitable_Donation_Metaboxes|null
		 * @access  private
		 * @static
		 */
		private static $instance = null;

		/**
		 * @var     Charitable_Meta_Box_Helper $meta_box_helper
		 */
		private $meta_box_helper;

		/**
		 * Create object instance.
		 *
		 * @access  public
		 * @since   1.5.0
		 */
		public function __construct( $helper ) {
			$this->meta_box_helper = $helper;
		}

		/**
		 * Returns and/or create the single instance of this class.
		 *
		 * @return  Charitable_Donation_Metaboxes
		 * @access  public
		 * @since   1.5.0
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				$helper 		= new Charitable_Meta_Box_Helper( 'charitable-donation' );
				self::$instance = new Charitable_Donation_Metaboxes( $helper );
			}

			return self::$instance;
		}

		/**
		 * Sets up the meta boxes to display on the donation admin page.
		 *
		 * @return  void
		 * @access  public
		 * @since   1.5.0
		 */
		public function add_meta_boxes() {
			foreach ( $this->get_meta_boxes() as $meta_box_id => $meta_box ) {
				add_meta_box(
					$meta_box_id,
					$meta_box['title'],
					array( $this->meta_box_helper, 'metabox_display' ),
					Charitable::DONATION_POST_TYPE,
					$meta_box['context'],
					$meta_box['priority'],
					$meta_box
				);
			}
		}

		/**
		 * Remove default meta boxes.
		 *
		 * @return  void
		 * @access  public
		 * @since   1.5.0
		 */
		public function remove_meta_boxes() {
			global $wp_meta_boxes;

			$charitable_meta_boxes = $this->get_meta_boxes();

			foreach ( $wp_meta_boxes[ Charitable::DONATION_POST_TYPE ] as $context => $priorities ) {
				foreach ( $priorities as $priority => $meta_boxes ) {
					foreach ( $meta_boxes as $meta_box_id => $meta_box ) {
						if ( ! isset( $charitable_meta_boxes[ $meta_box_id ] ) ) {
							remove_meta_box( $meta_box_id, Charitable::DONATION_POST_TYPE, $context );
						}
					}
				}
			}
		}

		/**
		 * Returns an array of all meta boxes added to the donation post type screen.
		 *
		 * @return  array
		 * @access  private
		 * @since   1.5.0
		 */
		private function get_meta_boxes() {
			$meta_boxes = array(
				'donation-overview'  => array(
					'title'         => __( 'Donation Overview', 'charitable' ),
					'context'       => 'normal',
					'priority'      => 'high',
					'view'          => 'metaboxes/donation/donation-overview',
				),
				'donation-actions'     => array(
					'title'         => __( 'Donation Actions', 'charitable' ),
					'context'       => 'side',
					'priority'      => 'high',
					'view'          => 'metaboxes/donation/donation-actions',
				),
				'donation-details'     => array(
					'title'         => __( 'Donation Details', 'charitable' ),
					'context'       => 'side',
					'priority'      => 'high',
					'view'          => 'metaboxes/donation/donation-details',
				),
				'donation-log'      => array(
					'title'         => __( 'Donation Log', 'charitable' ),
					'context'       => 'normal',
					'priority'      => 'low',
					'view'          => 'metaboxes/donation/donation-log',
				),
			);

			return apply_filters( 'charitable_donation_meta_boxes', $meta_boxes );
		}

		/**
		 * Get an array of donation actions.
		 *
		 * @param 	int $donation_id The donation ID.
		 * @return 	array
		 * @access 	public
		 * @since 	1.5.0
		 */
		public function get_donation_actions( $donation_id ) {
			$actions = array();

			return apply_filters( 'charitable_donation_actions', $actions, $donation_id );
		}

		/**
		 * Save meta for the donation.
		 *
		 * @param   int $donation_id
		 * @param   WP_Post $post
		 * @return  void
		 * @access  public
		 * @since   1.5.0
		 */
		public function save_donation( $donation_id, WP_Post $post ) {
			if ( ! $this->meta_box_helper->user_can_save( $donation_id ) ) {
				return;
			}

			/* Handle any fired actions */
			if ( ! empty( $_POST['charitable_donation_action'] ) ) {

			}

			/* Hook for plugins to do something else with the posted data */
			do_action( 'charitable_donation_save', $donation_id, $post );
		}

		/**
		 * Fire any donation actions.
		 *
		 * @param   int $donation_id
		 * @param   WP_Post $post
		 * @return  void
		 * @access  public
		 * @since   1.5.0
		 */
		public function process_donation_actions( $donation_id, WP_Post $post ) {
			global $wpdb;

			// Handle button actions
			if ( ! empty( $_POST['charitable_donation_action'] ) ) {

				$action = sanitize_text_field( $_POST['charitable_donation_action'] );

				if ( strstr( $action, 'send_email_' ) ) {

					$email_to_send = str_replace( 'send_email_', '', $action );

					// Switch back to the site locale.
					if ( function_exists( 'switch_to_locale' ) ) {
						switch_to_locale( get_locale() );
					}

					// data saved, now get it so we can manipulate status
					$donation = charitable_get_donation( $donation_id );

					do_action( 'charitable_before_resend_donation_emails', $donation, $email_to_send );

					// Ensure gateways are loaded in case they need to insert data into the emails.
					Charitable_Gateways::get_instance();

					// Load mailer.
					$mailer = Charitable_Emails::get_instance();

					$emails = $mailer->get_available_emails();

					if( isset( $emails[$email_to_send] ) && class_exists( $emails[$email_to_send] ) ) {
						
						$email_class = $emails[$email_to_send];

						if( method_exists( $email_class, 'send_with_donation_id' ) ) {

							$email = new $email_class;

							$sent = $email_class::send_with_donation_id( $donation_id, $donation, true );

							if( $sent ) {
								/* translators: %s: email title */
								$donation->update_donation_log( sprintf( __( '%s email notification manually sent.', 'charitable' ), $email->get_name() ), false, true );

								// Change the post saved message.
								add_filter( 'redirect_post_location', array( __CLASS__, 'set_email_sent_message' ) );
							} else {
								add_filter( 'redirect_post_location', array( __CLASS__, 'set_email_fail_message' ) );
							}

						}
				
					}

					do_action( 'charitable_after_resend_donation_email', $donation, $email_to_send );

					// Restore user locale.
					if ( function_exists( 'restore_current_locale' ) ) {
						restore_current_locale();
					}

				} else {

					if ( ! did_action( 'charitable_donation_action_' . sanitize_title( $action ) ) ) {
						do_action( 'charitable_donation_action_' . sanitize_title( $action ), $donation );
					}
				}
			}
		}

		/**
		 * Customize donations columns.
		 *
		 * @see     get_column_headers
		 *
		 * @return  array
		 * @access  public
		 * @since   1.5.0
		 */
		public function dashboard_columns( $column_names ) {
			$column_names = apply_filters( 'charitable_donation_dashboard_column_names', array(
				'cb'            => '<input type="checkbox"/>',
				'id'            => __( 'Donation', 'charitable' ),
				'amount'        => __( 'Amount Donated', 'charitable' ),
				'campaigns'     => __( 'Campaign(s)', 'charitable' ),
				'donation_date' => __( 'Date', 'charitable' ),
				'post_status'   => __( 'Status', 'charitable' ),
			) );

			return $column_names;
		}

		/**
		 * Add information to the dashboard donations table listing.
		 *
		 * @see     WP_Posts_List_Table::single_row()
		 *
		 * @since   1.5.0
		 *
		 * @param   string  $column_name    The name of the column to display.
		 * @param   int     $post_id        The current post ID.
		 * @return  void
		 */
		public function dashboard_column_item( $column_name, $post_id ) {

			$donation = charitable_get_donation( $post_id );

			switch ( $column_name ) {

				case 'id' :
					$title = esc_attr__( 'View Donation Details', 'charitable' );
					$name  = $donation->get_donor()->get_name();

					if ( $name ) {
						$text = sprintf( _x( '#%d by %s', 'number symbol', 'charitable' ),
							$post_id,
							$name
						);
					} else {
						$text = sprintf( _x( '#%d', 'number symbol', 'charitable' ),
							$post_id
						);
					}

					$url = esc_url( add_query_arg( array(
						'post'   => $post_id,
						'action' => 'edit',
					), admin_url( 'post.php' ) ) );

					$display = sprintf( '<a href="%s" aria-label="%s">%s</a>', $url, $title, $text );

					break;

				case 'post_status' :

					$display = sprintf( '<mark class="status %s">%s</mark>',
						esc_attr( $donation->get_status() ),
						strtolower( $donation->get_status( true ) )
					);

					break;

				case 'amount' :

					$display = charitable_format_money( $donation->get_total_donation_amount() );
					$display .= '<span class="meta">' . sprintf( _x( 'via %s', 'charitable' ), $donation->get_gateway_label() ). '</span>';

					break;

				case 'campaigns' :

					$campaigns = array();

					foreach ( $donation->get_campaign_donations() as $cd ) {

						$campaigns[] = sprintf( '<a href="edit.php?post_type=%s&campaign_id=%s">%s</a>',
							Charitable::DONATION_POST_TYPE,
							$cd->campaign_id,
							$cd->campaign_name
						);
					}

					$display = implode( ', ', $campaigns );

					break;

				case 'donation_date' :

					$display = $donation->get_date();

					break;

				default :

					$display = '';

					break;

			}

			echo apply_filters( 'charitable_donation_column_display', $display, $column_name, $post_id, $donation );
		}

		/**
		 * Make columns sortable.
		 *
		 * @since   1.4.0
		 *
		 * @param   array $columns  .
		 * @return  array
		 */
		public function sortable_columns( $columns ) {
			$sortable_columns = array(
				'id'            => 'ID',
				'amount'        => 'amount',
				'donation_date' => 'date',
			);

			return wp_parse_args( $sortable_columns, $columns );
		}

		/**
		 * Set list table primary column for donations.
		 *
		 * Support for WordPress 4.3.
		 *
		 * @since   1.4.0
		 *
		 * @param  string $default
		 * @param  string $screen_id
		 * @return string
		 */
		public function primary_column( $default, $screen_id ) {
			if ( 'edit-donation' === $screen_id ) {
				return 'id';
			}

			return $default;
		}

		/**
		 * Set row actions for donations.
		 *
		 * @since   1.4.0
		 *
		 * @param  array   $actions
		 * @param  WP_Post $post
		 * @return array
		 */
		public function row_actions( $actions, $post ) {
			if ( Charitable::DONATION_POST_TYPE !== $post->post_type ) {
				return $actions;
			}

			if ( isset( $actions['inline hide-if-no-js'] ) ) {
				unset( $actions['inline hide-if-no-js'] );
			}

			if ( isset( $actions['edit'] ) ) {

				$title  = esc_attr__( 'View Details', 'charitable' );
				$text   = __( 'View', 'charitable' );
				$url    = esc_url( add_query_arg( array(
					'post' => $post->ID,
					'action' => 'edit',
				), admin_url( 'post.php' ) ) );

				$actions['edit'] = sprintf( '<a href="%s" aria-label="%s">%s</a>', $url, $title, $text );

			}

			return $actions;
		}   

		/**
		 * Customize the output of the status views.
		 *
		 * @since   1.4.0
		 *
		 * @param   string[] $views
		 * @return  string[]
		 */
		public function set_status_views( $views ) {

			$counts  = $this->get_status_counts();
			$current = array_key_exists( 'post_status', $_GET ) ? $_GET['post_status'] : '';

			foreach ( charitable_get_valid_donation_statuses() as $key => $label ) {

				$views[ $key ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
					add_query_arg( array(
						'post_status' => $key,
						'paged'       => false,
					) ),
					$current === $key ? ' class="current"' : '',
					$label,
					array_key_exists( $key, $counts ) ? $counts[ $key ] : '0'
				);

			}

			$views['all'] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				remove_query_arg( array( 'post_status', 'paged' ) ),
				'all' === $current || '' === $current ? ' class="current"' : '',
				__( 'All', 'charitable' ),
				array_sum( $counts )
			);

			unset( $views['mine'] );

			return $views;
		}

		/**
		 * Add Custom bulk actions
		 *
		 * @since   1.4.7
		 *
		 * @param   array $actions
		 * @return  array
		 */
		public function custom_bulk_actions( $actions ) {
			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}

			return array_merge( $actions, $this->get_bulk_actions() );
		}

		/**
		 * Process bulk actions
		 *
		 * @since   1.4.7
		 *
		 * @param   int    $redirect_to
		 * @param   string $action
		 * @param   int[]  $post_ids
		 * @return  string
		 */
		public function bulk_action_handler( $redirect_to, $action, $post_ids ) {

			// Bail out if this is not a status-changing action
			if ( strpos( $action, 'set-' ) === false ) {
				$sendback = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'locked', 'ids' ), wp_get_referer() );
				wp_redirect( esc_url_raw( $sendback ) );

				exit();
			}

			$donation_statuses = charitable_get_valid_donation_statuses();

			$new_status    = str_replace( 'set-', '', $action ); // get the status name from action

			$report_action = 'bulk_' . Charitable::DONATION_POST_TYPE . '_status_update';

			// Sanity check: bail out if this is actually not a status, or is
			// not a registered status
			if ( ! isset( $donation_statuses[ $new_status ] ) ) {
				return $redirect_to;
			}

			foreach ( $post_ids as $post_id ) {
				$donation = charitable_get_donation( $post_id );
				$donation->update_status( $new_status );
				do_action( 'charitable_donations_table_do_bulk_action', $post_id, $new_status );
			}

			$redirect_to = add_query_arg( $report_action, count( $post_ids ), $redirect_to );

			return $redirect_to;
		}


		/**
		 * Remove edit from the bulk actions.
		 *
		 * @since   1.4.0
		 *
		 * @param   array $actions
		 * @return  array
		 */
		public function remove_bulk_actions( $actions ) {
			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}

			return $actions;
		}

		/**
		 * Retrieve the bulk actions
		 *
		 * @return  array $actions Array of the bulk actions
		 * @access  public
		 * @since   1.5.0
		 */
		public function get_bulk_actions() {
			$actions = array();

			foreach ( charitable_get_valid_donation_statuses() as $status_key => $label ) {
				$actions[ 'set-' . $status_key ] = sprintf( _x( 'Set to %s', 'set donation status to x', 'charitable' ), $label );
			}

			return apply_filters( 'charitable_donations_table_bulk_actions', $actions );
		}

		/**
		 * Add extra bulk action options to mark orders as complete or processing.
		 *
		 * Using Javascript until WordPress core fixes: https://core.trac.wordpress.org/ticket/16031
		 *
		 * @global  string $post_type
		 * @since   1.4.0
		 *
		 * @return  void
		 */
		public function bulk_admin_footer() {
			global $post_type;

			if ( Charitable::DONATION_POST_TYPE == $post_type ) {
				?>
				<script type="text/javascript">
				(function($) { 

					<?php
					foreach ( $this->get_bulk_actions() as $status_key => $label ) {
						printf( "jQuery('<option>').val('%s').text('%s').appendTo( [ '#bulk-action-selector-top', '#bulk-action-selector-bottom' ] );", $status_key, $label );
					}
					?>

					
				})(jQuery);
				</script>
				<?php
			}
		}

		/**
		 * Process the new bulk actions for changing order status.
		 *
		 * @since   1.4.0
		 *
		 * @return  void
		 */
		public function process_bulk_action() {

			// We only want to deal with donations. In case any other CPTs have an 'active' action
			if ( ! isset( $_REQUEST['post_type'] ) || Charitable::DONATION_POST_TYPE !== $_REQUEST['post_type'] || ! isset( $_REQUEST['post'] ) ) {
				return;
			}

			check_admin_referer( 'bulk-posts' );

			// get the action
			$action = '';

			if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
				$action = $_REQUEST['action'];
			} else if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
				$action = $_REQUEST['action2'];
			}

			$post_ids = array_map( 'absint', (array) $_REQUEST['post'] );

			$redirect_to = add_query_arg( array( 'post_type' => Charitable::DONATION_POST_TYPE ), admin_url( 'edit.php' ) );

			$redirect_to = $this->bulk_action_handler( $redirect_to, $action, $post_ids );

			wp_redirect( esc_url_raw( $redirect_to ) );

			exit();
		}


		/**
		 * Show confirmation message that order status changed for number of orders.
		 */
		public function bulk_admin_notices() {
			global $post_type, $pagenow;

			// Bail out if not on shop order list page
			if ( 'edit.php' !== $pagenow || Charitable::DONATION_POST_TYPE !== $post_type ) {
				return;
			}

			// Check if any status changes happened
			$report_action = 'bulk_' . Charitable::DONATION_POST_TYPE . '_status_update';

			if ( ! empty( $_REQUEST[ $report_action ] ) ) {
				$number = absint( $_REQUEST[ $report_action ] );
				$message = sprintf( _n( 'Donation status changed.', '%s donation statuses changed.', $number, 'charitable' ), number_format_i18n( $number ) );
				echo '<div class="updated"><p>' . $message . '</p></div>';
			}

		}

		/**
		 * Change messages when a post type is updated.
		 *
		 * @param  array $messages
		 * @return array
		 */
		public function post_messages( $messages ) {
			global $post, $post_ID;

			$messages[ Charitable::DONATION_POST_TYPE ] = array(
				0 => '', // Unused. Messages start at index 1.
				1 => sprintf( __( 'Donation updated. <a href="%s">View Donation</a>', 'charitable' ), esc_url( get_permalink( $post_ID ) ) ),
				2 => __( 'Custom field updated.', 'charitable' ),
				3 => __( 'Custom field deleted.', 'charitable' ),
				4 => __( 'Donation updated.', 'charitable' ),
				5 => isset( $_GET['revision'] ) ? sprintf( __( 'Donation restored to revision from %s', 'charitable' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __( 'Donation published. <a href="%s">View Donation</a>', 'charitable' ), esc_url( get_permalink( $post_ID ) ) ),
				7 => __( 'Donation saved.', 'charitable' ),
				8 => sprintf(
					__( 'Donation submitted. <a target="_blank" href="%s">Preview Donation</a>', 'charitable' ),
					esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) )
				),
				9 => sprintf(
					__( 'Donation scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Donation</a>', 'charitable' ),
					date_i18n( __( 'M j, Y @ G:i', 'charitable' ), strtotime( $post->post_date ) ),
					esc_url( get_permalink( $post_ID ) )
				),
				10 => sprintf(
					__( 'Donation draft updated. <a target="_blank" href="%s">Preview Donation</a>', 'charitable' ),
					esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) )
				),
				11 => __( 'Donation updated and email sent.', 'charitable' ),
				12 => __( 'Email could not be sent.', 'charitable' ),
			);

			return $messages;
		}

		/**
		 * Modify bulk messages
		 *
		 * @since   1.4.7
		 *
		 * @param 	array $bulk_messages
		 * @param 	array $bulk_counts
		 * @return 	array
		 */
		public function bulk_messages( $bulk_messages, $bulk_counts ) {

			$bulk_messages[ Charitable::DONATION_POST_TYPE ] = array(
				'updated'   => _n( '%d donation updated.', '%d donations updated.', $bulk_counts['updated'], 'charitable' ),
				'locked'    => ( 1 == $bulk_counts['locked'] ) ? __( '1 donation not updated, somebody is editing it.' ) :
								   _n( '%s donation not updated, somebody is editing it.', '%s donations not updated, somebody is editing them.', $bulk_counts['locked'], 'charitable' ),
				'deleted'   => _n( '%s donation permanently deleted.', '%s donations permanently deleted.', $bulk_counts['deleted'], 'charitable' ),
				'trashed'   => _n( '%s donation moved to the Trash.', '%s donations moved to the Trash.', $bulk_counts['trashed'], 'charitable' ),
				'untrashed' => _n( '%s donation restored from the Trash.', '%s donations restored from the Trash.', $bulk_counts['untrashed'], 'charitable' ),
			);

			return $bulk_messages;

		}

		/**
		 * Disable the month's dropdown (will replace with custom range search).
		 *
		 * @since   1.4.0
		 *
		 * @param   boolean $disable   Whether the months dropdown should be disabled.
		 * @param   string  $post_type The current post type.
		 * @return  array
		 */
		public function disable_months_dropdown( $disable, $post_type ) {
			if ( Charitable::DONATION_POST_TYPE == $post_type ) {
				$disable = true;
			}

			return $disable;
		}

		/**
		 * Add date-based filters above the donations table.
		 *
		 * @since   1.4.0
		 *
		 * @param   string $post_type The post type.
		 * @return  void
		 */
		public function restrict_manage_posts( $post_type = '' ) {
			global $typenow;

			/* Show custom filters to filter orders by donor. */
			if ( in_array( $typenow, array( Charitable::DONATION_POST_TYPE ) ) ) {
				charitable_admin_view( 'donations-page/filters' );
			}
		}

		/**
		 * Add extra buttons after filters
		 *
		 * @since   1.4.0
		 *
		 * @param   string $which Context.
		 * @return  void
		 */
		public function extra_tablenav( $which ) {
			global $typenow;

			/* Add the export button. */
			if ( 'top' == $which && in_array( $typenow, array( Charitable::DONATION_POST_TYPE ) ) ) {
				charitable_admin_view( 'donations-page/export' );
			}
		}

		/**
		 * Add modal template to footer
		 *
		 * @since   1.4.0
		 *
		 * @return  void
		 */
		public function modal_forms() {
			global $typenow;

			/* Add the modal form. */
			if ( in_array( $typenow, array( Charitable::DONATION_POST_TYPE ) ) ) {
				charitable_admin_view( 'donations-page/export-form' );
				charitable_admin_view( 'donations-page/filter-form' );
			}

		}

		/**
		 * Admin scripts and styles.
		 *
		 * Set up the scripts & styles used for the modal.
		 *
		 * @since   1.4.0
		 *
		 * @param 	string $hook The current admin page hook.
		 * @return  void
		 */
		public function load_scripts( $hook ) {
			if ( 'edit.php' != $hook ) {
				return;
			}

			if ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
				$suffix  = '';
				$version = time();
			} else {
				$suffix  = '.min';
				$version = charitable()->get_version();
			}

			$assets_path = charitable()->get_path( 'assets', false );

			/* Register the appropriate scripts. */
			wp_register_script(
				'lean-modal',
				$assets_path . 'js/libraries/leanModal' . $suffix . '.js',
				array( 'jquery-core' ),
				$version,
				true
			);

			wp_register_style(
				'lean-modal-css',
				$assets_path . 'css/modal' . $suffix . '.css',
				array(),
				$version
			);

			wp_register_script(
				'charitable-admin-donations',
				$assets_path . 'js/charitable-admin-donations' . $suffix . '.js',
				array( 'jquery-core', 'lean-modal' ),
				$version,
				true
			);

			global $typenow;

			/* Enqueue the scripts for donation page */
			if ( in_array( $typenow, array( Charitable::DONATION_POST_TYPE ) ) ) {
				wp_enqueue_style( 'lean-modal-css' );
				wp_enqueue_script( 'jquery-core' );
				wp_enqueue_script( 'lean-modal' );
				wp_enqueue_script( 'charitable-admin-donations' );
			}

		}

		/**
		 * Add custom filters to the query that returns the donations to be displayed.
		 *
		 * @since   1.4.0
		 *
		 * @param   array $vars Request args.
		 * @return  array
		 */
		public function request_query( $vars ) {
			global $typenow;

			if ( Charitable::DONATION_POST_TYPE != $typenow ) {
				return $vars;
			}

			/* No Status: fix WP's crappy handling of "all" post status. */
			if ( ! isset( $vars['post_status'] ) ) {
				$vars['post_status'] = array_keys( charitable_get_valid_donation_statuses() );
			}

			/* Set up date query */
			if ( isset( $_GET['start_date'] ) && ! empty( $_GET['start_date'] ) ) {

				$start_date = $this->get_parsed_date( $_GET['start_date'] );

				$vars['date_query']['after'] = array(
					'year'  => $start_date['year'],
					'month' => $start_date['month'],
					'day'   => $start_date['day'],
				);
			}

			if ( isset( $_GET['end_date'] ) && ! empty( $_GET['end_date'] ) ) {

				$end_date = $this->get_parsed_date( $_GET['end_date'] );

				$vars['date_query']['before'] = array(
					'year'  => $end_date['year'],
					'month' => $end_date['month'],
					'day'   => $end_date['day'],
				);
			}

			/* Filter by campaign. */
			if ( isset( $_GET['campaign_id'] ) && 'all' != $_GET['campaign_id'] ) {

				$donations = charitable_get_table( 'campaign_donations' )->get_donation_ids_for_campaign( $_GET['campaign_id'] );

				$vars['post__in'] = $donations;

			}

			return $vars;
		}

		/**
		 * Column sorting handler.
		 *
		 * @since   1.4.0
		 *
		 * @param   array $clauses Clauses used to filter the donations.
		 * @return  array
		 */
		public function posts_clauses( $clauses ) {
			global $typenow, $wpdb;

			if ( Charitable::DONATION_POST_TYPE != $typenow ) {
				return $clauses;
			}

			if ( ! isset( $_GET['orderby'] ) ) {
				return $clauses;
			}

			/* Sorting */
			$order = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) == 'ASC' ? 'ASC' : 'DESC';

			switch ( $_GET['orderby'] ) {

				case 'amount' :
					$clauses['join'] = "JOIN {$wpdb->prefix}charitable_campaign_donations cd ON cd.donation_id = $wpdb->posts.ID ";
					$clauses['orderby'] = 'cd.amount ' . $order;
					break;

			}

			return $clauses;
		}

		/**
		 * Return the status counts, taking into account any current filters.
		 *
		 * @since   1.4.0
		 *
		 * @return  array
		 */
		protected function get_status_counts() {
			if ( ! isset( $this->status_counts ) ) {

				$args = array();

				if ( isset( $_GET['s'] ) && strlen( $_GET['s'] ) ) {
					$args['s'] = $_GET['s'];
				}

				if ( isset( $_GET['start_date'] ) && strlen( $_GET['start_date'] ) ) {
					$args['start_date'] = $this->get_parsed_date( $_GET['start_date'] );
				}

				if ( isset( $_GET['end_date'] ) && strlen( $_GET['end_date'] ) ) {
					$args['end_date'] = $this->get_parsed_date( $_GET['end_date'] );
				}

				$status_counts = Charitable_Donations::count_by_status( $args );

				foreach ( charitable_get_valid_donation_statuses() as $key => $label ) {

					$count = array_key_exists( $key, $status_counts ) ? $status_counts[ $key ]->num_donations : 0;

					$this->status_counts[ $key ] = $count;

				}
			}//end if

			return $this->status_counts;
		}

		/**
		 * Given a date, returns an array containing the date, month and year.
		 *
		 * @since   1.4.0
		 *
		 * @param 	string $date The date as a string.
		 * @return  string[]
		 */
		protected function get_parsed_date( $date ) {
			$time = strtotime( $date );

			return array(
				'year'  => date( 'Y', $time ),
				'month' => date( 'm', $time ),
				'day'   => date( 'd', $time ),
			);
		}

		/**
		 * Set the correct message ID.
		 *
		 * @param string $location
		 *
		 * @since  1.5.0
		 *
		 * @static
		 *
		 * @return string
		 */
		public static function set_email_sent_message( $location ) {
			return add_query_arg( 'message', 11, $location );
		}

		/**
		 * Set the correct message ID.
		 *
		 * @param string $location
		 *
		 * @since  1.5.0
		 *
		 * @static
		 *
		 * @return string
		 */
		public static function set_email_fail_message( $location ) {
			return add_query_arg( 'message', 12, $location );
		}


		/**
		 * Respond to changes in donation status.
		 *
		 * @since   1.2.0
		 *
		 * @param   string  $new_status New donation status.
		 * @param   string  $old_status Old donation status.
		 * @param   WP_Post $post       The post object.
		 * @return  void
		 *
		 * @deprecated 1.4.0
		 */
		public function handle_donation_status_change( $new_status, $old_status, $post ) {

			charitable_get_deprecated()->deprecated_function(
				__METHOD__,
				'1.4.0',
				__( 'Handled automatically when $donation->update_status() is called.', 'charitable' )
			);

			if ( Charitable::DONATION_POST_TYPE != $post->post_type ) {
				return;
			}

			$valid_statuses = charitable_get_valid_donation_statuses();

			if ( 'new' == $old_status ) {
				$message = sprintf( __( 'Donation status set to %s.', 'charitable' ),
					$valid_statuses[ $new_status ]
				);
			} else {
				$message = sprintf( __( 'Donation status updated from %s to %s.', 'charitable' ),
					$valid_statuses[ $old_status ],
					$valid_statuses[ $new_status ]
				);
			}

			charitable_get_donation( $post->ID )->update_donation_log( $message );
		}
	}

endif;