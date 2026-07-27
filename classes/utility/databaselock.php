<?php

namespace TFAuthLS;

use RuntimeException;

class Utility_DatabaseLock implements Utility_Lock
{


	const DEFAULT_TIMEOUT = 30;
	const MAX_TIMEOUT     = 120;

	private $wpdb;
	private $table;
	private string $key;
	private $timeout;
	private int|float|null $expirationTimestamp = null;

	public function __construct($dbController, $key, $timeout = null)
	{
		$this->wpdb    = $dbController->get_wpdb();
		$this->table   = $dbController->settings;
		$this->key     = "lock:{$key}";
		$this->timeout = $this->resolveTimeout($timeout);
	}

	private function resolveTimeout($timeout): int
	{
		if ($timeout === null) {
			$timeout = ini_get('max_execution_time');
		}
		$timeout = (int) $timeout;
		if ($timeout <= 0 || $timeout > self::MAX_TIMEOUT) {
			return self::DEFAULT_TIMEOUT;
		}
		return $timeout;
	}

	private function clearExpired(int $timestamp): void
	{
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
	}

	private function insert(int|float $expirationTimestamp): bool
	{
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				<<<SQL
			INSERT IGNORE
				INTO {$this->table}
				(name, value, autoload)
			VALUES(%s, %d, 'no')
			SQL,
				$this->key,
				$expirationTimestamp
			)
		);
		return $result === 1;
	}

	public function acquire($delay = self::DEFAULT_DELAY): void
	{
		$attempts = (int) ($this->timeout * 1000000 / $delay);
		for (; $attempts > 0; $attempts--) {
			$timestamp = time();
			$this->clearExpired($timestamp);
			$expirationTimestamp = $timestamp + $this->timeout;
			$locked              = $this->insert($expirationTimestamp);
			if ($locked) {
				$this->expirationTimestamp = $expirationTimestamp;
				return;
			}
			usleep($delay);
		}
		throw new RuntimeException("Failed to acquire lock {$this->key}");
	}

	private function delete($expirationTimestamp): void
	{
		$this->wpdb->delete(
			$this->table,
			array(
				'name'  => $this->key,
				'value' => $expirationTimestamp,
			),
			array(
				'%s',
				'%d',
			)
		);
	}

	public function release(): void
	{
		if ($this->expirationTimestamp === null) {
			return;
		}
		$this->delete($this->expirationTimestamp);
		$this->expirationTimestamp = null;
	}
}
