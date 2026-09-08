<?php
/**
 * Loader for registering actions and filters.
 *
 * Collects actions/filters and registers them with WordPress when run().
 *
 * @package   Skydropx
 * @subpackage Skydropx/includes
 * @since     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's actions and filters with WordPress.
 */
class Skydropx_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
	 */
	protected $filters;


	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}


	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since 1.0.0
	 * @param string $hook          The WordPress action name.
	 * @param object $component     Instance on which the action is defined.
	 * @param string $callback      Method name on the component.
	 * @param int    $priority      Optional. Priority to fire. Default 10.
	 * @param int    $accepted_args Optional. Number of args passed. Default 1.
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}


	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since 1.0.0
	 * @param string $hook          The WordPress filter name.
	 * @param object $component     Instance on which the filter is defined.
	 * @param string $callback      Method name on the component.
	 * @param int    $priority      Optional. Priority to fire. Default 10.
	 * @param int    $accepted_args Optional. Number of args passed. Default 1.
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}


	/**
	 * Register an action or filter into a collection.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param array  $hooks         The collection (actions or filters).
	 * @param string $hook          The WordPress hook name.
	 * @param object $component     Instance containing the callback.
	 * @param string $callback      Callback method name.
	 * @param int    $priority      Hook priority.
	 * @param int    $accepted_args Number of accepted arguments for the callback.
	 * @return array The updated collection.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {

		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}
}
