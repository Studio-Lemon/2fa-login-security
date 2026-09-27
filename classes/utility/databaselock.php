<?php

/**
 * Database-backed lock utility.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

use RuntimeException;

/**
 * Acquires and releases a lock stored in the WordPress options table.
 */
class Utility_DatabaseLock implements Utility_Lock
{




	const DEFAULT_TIMEOUT = 30;
	const MAX_TIMEOUT     = 120;

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private $wpdb;
	/**
	 * Lock table name.
	 *
	 * @var string
	 */
	private $table;
	/**
	 * Lock key.
	 *
	 * @var string
	 */
	private string $key;
	/**
	 * Lock timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout;
	/**
	 * Lock expiration timestamp.
	 *
	 * @var int|float|null
	 */
	private int|float|null $expiration_timestamp = null;

	/**
	 * Initializes a database lock.
	 *
	 * @param Controller_DB $db_controller Database controller.
	 * @param string        $key Lock key.
	 * @param int|null      $timeout Lock timeout in seconds.
	 */
	public function __construct($db_controller, $key, $timeout = null)
	{
		$this->wpdb    = $db_controller->get_wpdb();
		$this->table   = $db_controller->settings;
		$this->key     = "lock:{$key}";
		$this->timeout = $this->resolveTimeout($timeout);
	}

	/**
	 * Resolves a valid lock timeout.
	 *
	 * @param int|string|null $timeout Requested timeout.
	 * @return int Resolved timeout.
	 */
	private function resolveTimeout($timeout): int
	{
		if (null === $timeout) {
			$timeout = ini_get('max_execution_time');
		}
		$timeout = (int) $timeout;
		if ($timeout <= 0 || $timeout > self::MAX_TIMEOUT) {
			return self::DEFAULT_TIMEOUT;
		}
		return $timeout;
	}

	/**
	 * Removes an expired lock.
	 *
	 * @param int $timestamp Current timestamp.
	 * @return void
	 */
	private function clearExpired(int $timestamp): void
	{
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal and values are prepared.
		$this->wpdb->query(
			$this->wpdb->prepare(
				<<<SQL
			DELETE
				FROM {$this->table}
			WHERE
				name = %s
				AND value < %d
			SQL,
				$this->key,
				$timestamp
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Inserts the lock if it does not already exist.
	 *
	 * @param int|float $expiration_timestamp Lock expiration timestamp.
	 * @return bool Whether the lock was inserted.
	 */
	private function insert(int|float $expiration_timestamp): bool
	{
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal and values are prepared.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				<<<SQL
			INSERT IGNORE
				INTO {$this->table}
				(name, value, autoload)
			VALUES(%s, %d, 'no')
			SQL,
				$this->key,
				$expiration_timestamp
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return 1 === $result;
	}

	/**
	 * Acquires the lock.
	 *
	 * @param int $delay Delay between attempts in microseconds.
	 * @return void
	 * @throws RuntimeException If the lock cannot be acquired.
	 */
	public function acquire($delay = self::DEFAULT_DELAY): void
	{
		$attempts = (int) ($this->timeout * 1000000 / $delay);
		for (; $attempts > 0; $attempts--) {
			$timestamp = time();
			$this->clearExpired($timestamp);
			$expiration_timestamp = $timestamp + $this->timeout;
			$locked               = $this->insert($expiration_timestamp);
			if ($locked) {
				$this->expiration_timestamp = $expiration_timestamp;
				return;
			}
			usleep($delay);
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The lock key is not browser output.
		throw new RuntimeException("Failed to acquire lock {$this->key}");
	}

	/**
	 * Deletes the lock if it has the expected expiration timestamp.
	 *
	 * @param int|float $expiration_timestamp Lock expiration timestamp.
	 * @return void
	 */
	private function delete($expiration_timestamp): void
	{
		$this->wpdb->delete(
			$this->table,
			array(
				'name'  => $this->key,
				'value' => $expiration_timestamp,
			),
			array(
				'%s',
				'%d',
			)
		);
	}

	/**
	 * Releases the held lock.
	 *
	 * @return void
	 */
	public function release(): void
	{
		if (null === $this->expiration_timestamp) {
			return;
		}
		$this->delete($this->expiration_timestamp);
		$this->expiration_timestamp = null;
	}
}
