<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/OllieJones
 * @since      1.0.0
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Index_Wp_Users_For_Speed {

  /**
   * The loader that's responsible for maintaining and registering all hooks that power
   * the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      Index_Wp_Users_For_Speed_Loader $loader Maintains and registers all hooks for the plugin.
   */
  protected $loader;

  /**
   * The unique identifier of this plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string $plugin_name The string used to uniquely identify this plugin.
   */
  protected $plugin_name;

  /**
   * The current version of the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string $version The current version of the plugin.
   */
  protected $version;

  /**
   * Define the core functionality of the plugin.
   *
   * Set the plugin name and the plugin version that can be used throughout the plugin.
   * Load the dependencies, define the locale, and set the hooks for the admin area and
   * the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function __construct() {
    if ( defined( 'INDEX_WP_USERS_FOR_SPEED_VERSION' ) ) {
      $this->version = INDEX_WP_USERS_FOR_SPEED_VERSION;
    } else {
      $this->version = '1.0.0';
    }
    $this->plugin_name = 'index-wp-users-for-speed';

    $this->load_dependencies();
    $this->set_locale();
    $this->define_admin_hooks();
    $this->define_public_hooks();

  }

  /**
   * Load the required dependencies for this plugin.
   *
   * Include the following files that make up the plugin:
   *
   * - Index_Wp_Users_For_Speed_Loader. Orchestrates the hooks of the plugin.
   * - Index_Wp_Users_For_Speed_i18n. Defines internationalization functionality.
   * - Index_Wp_Users_For_Speed_Admin. Defines all hooks for the admin area.
   * - Index_Wp_Users_For_Speed_Public. Defines all hooks for the public side of the site.
   *
   * Create an instance of the loader which will be used to register the hooks
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function load_dependencies() {

    /**
     * The class responsible for orchestrating the actions and filters of the
     * core plugin.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-index-wp-users-for-speed-loader.php';

    /**
     * The class responsible for defining internationalization functionality
     * of the plugin.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-index-wp-users-for-speed-i18n.php';

    /**
     * The class responsible for defining all actions that occur in the admin area.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-index-wp-users-for-speed-admin.php';

    /**
     * The class responsible for defining all actions that occur in the public-facing
     * side of the site.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-index-wp-users-for-speed-public.php';

    $this->loader = new Index_Wp_Users_For_Speed_Loader();

  }

  /**
   * Define the locale for this plugin for internationalization.
   *
   * Uses the Index_Wp_Users_For_Speed_i18n class in order to set the domain and to register the hook
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function set_locale() {

    $plugin_i18n = new Index_Wp_Users_For_Speed_i18n();

    $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

  }

  /**
   * Register all of the hooks related to the admin area functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_admin_hooks() {

    $plugin_admin = new Index_Wp_Users_For_Speed_Admin( $this->get_plugin_name(), $this->get_version() );

    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

    $this->loader->add_action( 'admin_init', $plugin_admin, 'admin_init', 10, 0 );
    $this->loader->add_action( 'set_user_role', $plugin_admin, 'set_user_role', 10, 3 );
    $this->loader->add_filter( 'views_users', $plugin_admin, 'filter_view_list', 10, 1 );
  }

  /**
   * The name of the plugin used to uniquely identify it within the context of
   * WordPress and to define internationalization functionality.
   *
   * @return    string    The name of the plugin.
   * @since     1.0.0
   */
  public function get_plugin_name() {
    return $this->plugin_name;
  }

  /**
   * Retrieve the version number of the plugin.
   *
   * @return    string    The version number of the plugin.
   * @since     1.0.0
   */
  public function get_version() {
    return $this->version;
  }

  /**
   * Register all of the hooks related to the public-facing functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_public_hooks() {

    $plugin_public = new Index_Wp_Users_For_Speed_Public( $this->get_plugin_name(), $this->get_version() );

    $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
    $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

  }

  /**
   * Run the loader to execute all of the hooks with WordPress.
   *
   * @since    1.0.0
   */
  public function run() {
    $this->loader->run();
  }

  /**
   * The reference to the class that orchestrates the hooks with the plugin.
   *
   * @return    Index_Wp_Users_For_Speed_Loader    Orchestrates the hooks of the plugin.
   * @since     1.0.0
   */
  public function get_loader() {
    return $this->loader;
  }

}
