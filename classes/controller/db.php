<?php

/**
 * Database controller.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

use RuntimeException;

/**
 * Manages the plugin database schema and queries.
 *
 * @property-read string $secrets
 * @property-read string $settings
 * @property-read string $role_counts
 * @property-read string $role_counts_temporary
 */
class Controller_DB
{


	const TABLE_2FA_SECRETS           = 'wfls_2fa_secrets';
	const TABLE_SETTINGS              = 'wfls_settings';
	const TABLE_ROLE_COUNTS           = 'wfls_role_counts';
	const TABLE_ROLE_COUNTS_TEMPORARY = 'wfls_role_counts_temporary';

	const SCHEMA_VERSION = 2;

	/**
	 * Returns the singleton Controller_DB.
	 *
	 * @return Controller_DB
	 */
	public static function shared()
	{
		static $_shared = null;
		if (null === $_shared) {
			$_shared = new Controller_DB();
		}
		return $_shared;
	}

	/**
	 * Returns the table prefix for the main site on multisites and the site itself on single site installations.
	 *
	 * @return string
	 */
	public static function network_prefix()
	{
		global $wpdb;
		return $wpdb->base_prefix;
	}

	/**
	 * Returns the table with the site (single site installations) or network (multisite) prefix added.

	 * @param string $table Table name without a prefix.
	 * @return string
	 */
	public static function network_table(string $table): string
	{
		return self::network_prefix() . $table;
	}

	/**
	 * Returns a configured table name.
	 *
	 * @param string $key Table identifier.
	 * @return string
	 * @throws \OutOfBoundsException When the table identifier is unknown.
	 */
	public function __get(string $key)
	{
		switch ($key) {
			case 'secrets':
				return self::network_table(self::TABLE_2FA_SECRETS);
			case 'settings':
				return self::network_table(self::TABLE_SETTINGS);
			case 'role_counts':
				return self::network_table(self::TABLE_ROLE_COUNTS);
			case 'role_counts_temporary':
				return self::network_table(self::TABLE_ROLE_COUNTS_TEMPORARY);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
		throw new \OutOfBoundsException('Unknown key: ' . $key);
	}

	/**
	 * Installs the database schema.
	 */
	public function install(): void
	{
		$this->create_schema();

		global $wpdb;
		$table = $this->secrets;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct update is required to invalidate stored verification times; table name is generated from a fixed plugin constant.
		$wpdb->query($wpdb->prepare("UPDATE `{$table}` SET `vtime` = LEAST(`vtime`, %d)", Controller_Time::time()));
	}

	/**
	 * Removes the database schema.
	 */
	public function uninstall(): void
	{
		$tables = array(self::TABLE_2FA_SECRETS, self::TABLE_SETTINGS, self::TABLE_ROLE_COUNTS);
		foreach ($tables as $table) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema removal is required during uninstall; table name is generated from a fixed plugin constant.
			$wpdb->query('DROP TABLE IF EXISTS `' . self::network_table($table) . '`');
		}
	}

	/**
	 * Creates a database table.
	 *
	 * @param string       $name       Table name without a prefix.
	 * @param array|string $definition Table definition or alternatives.
	 * @param bool         $temporary  Whether to create a temporary table.
	 * @return bool
	 */
	private function create_table($name, array|string $definition, $temporary = false): bool
	{
		global $wpdb;
		if (is_array($definition)) {
			foreach ($definition as $attempt) {
				if ($this->create_table($name, $attempt, $temporary)) {
					return true;
				}
			}
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema creation requires direct DDL; identifiers and definitions are plugin-controlled.
		return $wpdb->query('CREATE ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE IF NOT EXISTS `' . self::network_table($name) . '` ' . $definition) !== false;
	}

	/**
	 * Creates a temporary database table.
	 *
	 * @param string       $name       Table name without a prefix.
	 * @param array|string $definition Table definition or alternatives.
	 * @return bool
	 */
	private function create_temporary_table(string $name, array|string $definition): bool
	{
		if (Controller_Settings::shared()->get_bool(Controller_Settings::OPTION_DISABLE_TEMPORARY_TABLES)) {
			return false;
		}
		if ($this->create_table($name, $definition, true)) {
			return true;
		}
		Controller_Settings::shared()->set(Controller_Settings::OPTION_DISABLE_TEMPORARY_TABLES, true);
		return false;
	}

	/**
	 * Returns the role-counts table definition.
	 *
	 * @param string|null $engine Storage engine name.
	 * @return string
	 */
	private function get_role_counts_table_definition($engine = null): string
	{
		$engine_clause = null === $engine ? '' : "ENGINE={$engine}";
		return <<<SQL
				(
				serialized_roles VARBINARY(255) NOT NULL,
				two_factor_inactive TINYINT(1) NOT NULL,
				user_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (serialized_roles, two_factor_inactive)
				) {$engine_clause};
SQL;
	}

	/**
	 * Returns role-count table definitions for supported storage engines.
	 *
	 * @return string[]
	 */
	private function get_role_counts_table_definition_options(): array
	{
		return array(
			$this->get_role_counts_table_definition('MEMORY'),
			$this->get_role_counts_table_definition('MyISAM'),
			$this->get_role_counts_table_definition(),
		);
	}

	/**
	 * Creates the plugin database schema.
	 */
	protected function create_schema()
	{
		$tables = array(
			self::TABLE_2FA_SECRETS => '(
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `secret` tinyblob NOT NULL,
  `recovery` blob NOT NULL,
  `ctime` int(10) unsigned NOT NULL,
  `vtime` int(10) unsigned NOT NULL,
  `mode` enum(\'authenticator\') NOT NULL DEFAULT \'authenticator\',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
			self::TABLE_SETTINGS    => '(
  `name` varchar(191) NOT NULL DEFAULT \'\',
  `value` longblob,
  `autoload` enum(\'no\',\'yes\') NOT NULL DEFAULT \'yes\',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
			self::TABLE_ROLE_COUNTS => $this->get_role_counts_table_definition_options(),
		);

		foreach ($tables as $table => $def) {
			$this->create_table($table, $def);
		}

		Controller_Settings::shared()->set(Controller_Settings::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION);
	}

	/**
	 * Ensures the database schema is at least the requested version.
	 *
	 * @param int $version Required schema version.
	 */
	public function require_schema_version($version): void
	{
		$current = Controller_Settings::shared()->get_int(Controller_Settings::OPTION_SCHEMA_VERSION);
		if ($current < $version) {
			$this->install();
		}
	}

	/**
	 * Executes a database query.
	 *
	 * @param string $query SQL query.
	 * @throws RuntimeException When the query fails.
	 */
	public function query($query): void
	{
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- This internal helper is called with plugin-generated maintenance SQL only.
		if ($wpdb->query($query) === false) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
			throw new RuntimeException("Failed to execute query: {$query}");
		}
	}

	/**
	 * Returns the WordPress database connection.
	 *
	 * @return \wpdb
	 */
	public function get_wpdb()
	{
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Creates the temporary role-counts table.
	 *
	 * @return bool
	 */
	public function create_temporary_role_counts_table()
	{
		return $this->create_temporary_table(self::TABLE_ROLE_COUNTS_TEMPORARY, $this->get_role_counts_table_definition_options());
	}
}
