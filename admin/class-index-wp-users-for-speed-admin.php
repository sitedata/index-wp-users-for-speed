<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-index-wp-users-for-speed-indexing.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @link       https://github.com/OllieJones
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Index_Wp_Users_For_Speed_Admin {

  private static $messages;
  /** List of author IDs.
   * @var array
   */
  public $authorIdKludge = [];
  /**
   * The ID of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string $plugin_name The ID of this plugin.
   */
  private $plugin_name;
  /**
   * The version of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string $version The current version of this plugin.
   */
  private $version;
  private $indexer;
  private $pluginPath;
  private $recursionLevelBySite = [];
  private $message;

  /**
   * Initialize the class and set its properties.
   *
   * @param string $plugin_name The name of this plugin.
   * @param string $version The version of this plugin.
   *
   * @since    1.0.0
   */
  public function __construct( $plugin_name, $version ) {

    $this->plugin_name    = $plugin_name;
    $this->version        = $version;
    $this->indexer        = Index_Wp_Users_For_Speed_Indexing::getInstance();
    $this->authorIdKludge = range( 0, 20 );  //TODO get this right.
    $this->pluginPath     = plugin_dir_path( dirname( __FILE__ ) );
    /* after a POST, we get a redirect with ?st=message */
    $this->message = isset( $_REQUEST['st'] ) ? sanitize_key( $_REQUEST['st'] ) : null;

    self::$messages = [
      'started'   => __( 'User Indexing Started', 'index-wp-users-for-speed' ),
      'removed'   => __( 'User Indexing Removed', 'index-wp-users-for-speed' ),
      /* translators: 1: fraction complete on index */
      'progress'  => __( 'User Indexing %1$s Complete', 'index-wp-users-for-speed' ),
      'completed' => __( 'User Indexing Complete', 'index-wp-users-for-speed' ),
      /* translators: 1: message id, like 'started' or 'removed' This is a warning */
      'default'   => __( 'Unknown message id %1$s', 'index-wp-users-for-speed' ),
    ];
  }

  public function admin_menu() {

    add_users_page(
      esc_html__( 'Index WP Users For Speed', 'index-wp-users-for-speed' ),
      esc_html__( 'Index For Speed', 'index-wp-users-for-speed' ),
      'manage_options',
      $this->plugin_name,
      [ $this, 'render_admin_page' ],
      12 );

  }

  public function render_admin_page() {
    include_once $this->pluginPath . 'admin/views/page.php';
  }

  //region Post Actions

  /** untrusted post action
   * @return void
   */
  public function post_action_unverified() {
    $valid = check_admin_referer( $this->plugin_name, 'reindex' );
    if ( $valid === 1 ) {
      if ( current_user_can( 'update_options' ) ) {
        $message = apply_filters( $this->plugin_name . '-post-filter', $_REQUEST, 'default' );
        wp_safe_redirect( add_query_arg( 'st', $message, wp_get_referer() ) );

        return;
      }
    }
    status_header( 403 );
  }

  /** Form post filter, after verification.
   *
   * @param array $params
   *
   * @return void
   */
  public function post_filter( $params, $message ) {
    $q = $params;

    return $message;
  }

  /**
   * Register the stylesheets for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_styles() {
    wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/index-wp-users-for-speed-admin.css', [], $this->version, 'all' );
  }

  //endregion


  //region Enqueueing

  /**
   * Register the JavaScript for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_scripts() {
    wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/index-wp-users-for-speed-admin.js', [ 'jquery' ], $this->version, false );
  }

  public function delete_user( $user_id, $reassign, $user ) {
    $a = $user;
  }

  public function add_user_to_blog( $user_id, $role, $blog_id ) {
    $restoreBlogId = get_current_blog_id();
    switch_to_blog( $blog_id );
    $this->indexer->getUserCounts();
    $this->indexer->updateUserCounts( $role, + 1 );
    $this->indexer->setUserCounts();
    switch_to_blog( $restoreBlogId );
  }

  //endregion

  /**
   * @param array $args
   *
   * @return array filtered args
   */
  public function users_list_table_query_args( $args ) {
    return $args;
  }

  public function wpmu_activate_user( $user_id, $password, $meta ) {
    $a = $user_id;
  }

  public function wpmu_delete_user( $user_id, $user ) {
    $a = $user;
  }

  public function network_site_new_created_user( $user_id ) {
    $a = $user_id;
  }

  public function network_site_users_created_user( $user_id ) {
    $a = $user_id;
  }

  /** set a user role
   *
   * @param int $user_id
   * @param string $newRole
   * @param array $oldRoles
   *
   * @return void
   */
  public function set_user_role( $user_id, $newRole, $oldRoles ) {
    $this->indexer->getUserCounts();
    $this->indexer->updateUserCounts( $newRole, + 1 );
    foreach ( $oldRoles as $oldRole ) {
      $this->indexer->updateUserCounts( $oldRole, - 1 );
    }
    $this->indexer->setUserCounts();
  }

  /** count users.
   *
   * @param $result
   * @param $strategy
   * @param $site_id
   *
   * @return array
   */
  public function pre_count_users( $result, $strategy, $site_id ) {
    if ( ! array_key_exists( $site_id, $this->recursionLevelBySite ) ) {
      $this->recursionLevelBySite[ $site_id ] = 0;
    }
    if ( $this->recursionLevelBySite[ $site_id ] > 0 ) {
      return $result;
    }
    $previousId = get_current_blog_id();
    switch_to_blog( $site_id );

    $this->recursionLevelBySite[ $site_id ] ++;
    $output = $this->indexer->getUserCounts();
    $this->recursionLevelBySite[ $site_id ] --;

    switch_to_blog( $previousId );

    return $output;
  }

  /**
   * Filters the query arguments for the list of users in the dropdown.
   *
   * @param array $query_args The query arguments for get_users().
   * @param array $parsed_args The arguments passed to wp_dropdown_users() combined with the defaults.
   *
   * @returns array Updated $query_args
   * @since 4.4.0
   *
   */
  public function wp_dropdown_users_args( $query_args, $parsed_args ) {
    $query_args['include'] = $this->authorIdKludge;

    return $query_args;
  }

  /**
   * Filters the arguments used to generate the Quick Edit authors drop-down.
   *
   * @param array $users_opt An array of arguments passed to wp_dropdown_users().
   * @param bool $bulk A flag to denote if it's a bulk action.
   *
   * @since 5.6.0
   *
   * @see wp_dropdown_users()
   *
   */
  public function quick_edit_dropdown_authors_args( $users_opt, $bulk ) {
    $o = $users_opt;

    return $users_opt;
  }

  /**
   * Fires after the WP_User_Query has been parsed, and before
   * the query is executed.
   *
   * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
   *
   * @since 3.1.0
   *
   * @see WP_User_Query
   *
   * The passed WP_User_Query object contains SQL parts formed
   * from parsing the given query.
   *
   */
  public function pre_user_query( $query ) {
    /* Here we have $query->query_fields, query_from, query_where, query_orderby, query_limit
     *  and serveral other members of the WP_User_Query object.
     * We can alter them as needed to change the query before it runs */
    $q = $query;
  }

  /**
   * Filters WP_User_Query arguments when querying users via the REST API.
   *
   * @link https://developer.wordpress.org/reference/classes/wp_user_query/
   *
   * @since 4.7.0
   *
   * @param array $prepared_args Array of arguments for WP_User_Query.
   * @param WP_REST_Request $request The REST API request.
   */
  public function rest_user_query( $prepared_args, $request ) {
    if ( $request->get_param( 'context' ) === 'view' && $request->get_param( 'who' ) === 'authors' ) {
      /* this rest query does SQL_CALC_FOUND_ROWS and pagination. */
      $prepared_args['include'] = $this->authorIdKludge;
    }

    return $prepared_args;
  }

  /**
   * Filters the users array before the query takes place.
   *
   * Return a non-null value to bypass WordPress' default user queries.
   *
   * Filtering functions that require pagination information are encouraged to set
   * the `total_users` property of the WP_User_Query object, passed to the filter
   * by reference. If WP_User_Query does not perform a database query, it will not
   * have enough information to generate these values itself.
   *
   * @param array|null $results Return an array of user data to short-circuit WP's user query
   *                               or null to allow WP to run its normal queries.
   * @param WP_User_Query $query The WP_User_Query instance (passed by reference).
   *
   * @since 5.1.0
   *
   */
  public function users_pre_query( $results, $query ) {
    return $results;  /* unmodified, this is null */
  }

  protected function getMessage() {
    if ( $this->message ) {
      $message = array_key_exists( $this->message, self::$messages ) ? $this->message : 'default';

      return sprintf( self::$messages[ $message ], $this->message );
    }

    return false;
  }


}
