<?php
/**
 * Loader Class
 *
 * @package ReasonDigital\CSVPageGenerator\Core
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Core;

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 */
class Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @var array
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @var array
	 */
	protected $filters;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @param string $hook          The name of the WordPress action that is being registered.
	 * @param object $component     A reference to the instance of the object on which the action is defined.
	 * @param string $callback      The name of the function definition on the $component.
	 * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
	 * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @param string $hook          The name of the WordPress filter that is being registered.
	 * @param object $component     A reference to the instance of the object on which the filter is defined.
	 * @param string $callback      The name of the function definition on the $component.
	 * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
	 * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks into a single
	 * collection.
	 *
	 * @param array  $hooks         The collection of hooks that is being registered (that is, actions or filters).
	 * @param string $hook          The name of the WordPress filter that is being registered.
	 * @param object $component     A reference to the instance of the object on which the filter is defined.
	 * @param string $callback      The name of the function definition on the $component.
	 * @param int    $priority      The priority at which the function should be fired.
	 * @param int    $accepted_args The number of arguments that should be passed to the $callback.
	 * @return array The collection of actions and filters registered with WordPress.
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

	/**
	 * Register the filters and actions with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}

	/**
	 * Get all registered actions.
	 *
	 * @return array The registered actions.
	 */
	public function get_actions() {
		return $this->actions;
	}

	/**
	 * Get all registered filters.
	 *
	 * @return array The registered filters.
	 */
	public function get_filters() {
		return $this->filters;
	}

	/**
	 * Remove a specific action from the collection.
	 *
	 * @param string $hook      The name of the WordPress action.
	 * @param object $component The component object.
	 * @param string $callback  The callback method name.
	 * @return bool True if the action was removed, false otherwise.
	 */
	public function remove_action( $hook, $component, $callback ) {
		foreach ( $this->actions as $key => $action ) {
			if ( $action['hook'] === $hook &&
				$action['component'] === $component &&
				$action['callback'] === $callback ) {
				unset( $this->actions[ $key ] );
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove a specific filter from the collection.
	 *
	 * @param string $hook      The name of the WordPress filter.
	 * @param object $component The component object.
	 * @param string $callback  The callback method name.
	 * @return bool True if the filter was removed, false otherwise.
	 */
	public function remove_filter( $hook, $component, $callback ) {
		foreach ( $this->filters as $key => $filter ) {
			if ( $filter['hook'] === $hook &&
				$filter['component'] === $component &&
				$filter['callback'] === $callback ) {
				unset( $this->filters[ $key ] );
				return true;
			}
		}
		return false;
	}

	/**
	 * Clear all registered actions and filters.
	 */
	public function clear_all() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Get the total count of registered hooks.
	 *
	 * @return int The total number of registered hooks.
	 */
	public function get_hook_count() {
		return count( $this->actions ) + count( $this->filters );
	}
}
