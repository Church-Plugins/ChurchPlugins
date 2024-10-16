<?php
/**
 * Base migrator class
 *
 * @package ChurchPlugins
 * @since 1.2.0
 */

namespace ChurchPlugins\Setup;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Migrator
 */
abstract class Migrator {
	/**
	 * Singleton instance
	 *
	 * @var Migrator
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Migrator
	 *
	 * @return Migrator
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof static ) {
			self::$_instance = new static();
		}

		return self::$_instance;
	}

	/**
	 * Get an array of migrations
	 */
	abstract public function get_migrations(): array;

	/**
	 * Run all migrations
	 *
	 * @param string $old_version The old version.
	 * @param string $new_version The new version.
	 */
	public function run_migrations( $old_version, $new_version ) {
		$migrations = $this->get_migrations();

		foreach ( $migrations as $version => $migration ) {
			if ( version_compare( $old_version, $version, '<' ) && version_compare( $new_version, $version, '>=' ) ) {
				if ( isset( $migration['up'] ) && is_callable( $migration['up'] ) ) {
					$migration['up']();
				}
			} elseif ( version_compare( $old_version, $version, '>=' ) && version_compare( $new_version, $version, '<' ) ) {
				if ( isset( $migration['down'] ) && is_callable( $migration['down'] ) ) {
					$migration['down']();
				}
			}
		}
	}
}
