<?php

/**
 * Token bucket rate limiter.
 *
 * @package TFAuthLS
 */

// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore, Universal.Operators.StrictComparisons.LooseEqual, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy internal identifiers, WordPress return values, and atomic options-table locking are retained for compatibility.

namespace TFAuthLS;

/**
 * Limits repeated operations using Redis or WordPress options storage.
 */
class Model_TokenBucket
{


	/* Constants to map from tokens per unit to tokens per second */
	const MICROSECOND = 0.000001;
	const MILLISECOND = 0.001;
	const SECOND      = 1;
	const MINUTE      = 60;
	const HOUR        = 3600;
	const DAY         = 86400;
	const WEEK        = 604800;
	const MONTH       = 2629743.83;
	const YEAR        = 31556926;

	const BACKING_REDIS      = 'redis';
	const BACKING_WP_OPTIONS = 'wpoptions';

	/**
	 * Bucket identifier.
	 *
	 * @var string
	 */
	private $_identifier;
	/**
	 * Bucket capacity.
	 *
	 * @var int
	 */
	private $_bucket_size;
	/**
	 * Token refill rate.
	 *
	 * @var float
	 */
	private $_tokens_per_second;
	/**
	 * Storage backend.
	 *
	 * @var string
	 */
	private $_backing;
	/**
	 * Redis connection.
	 *
	 * @var \Redis|null
	 */
	private ?\Redis $_redis = null;

	/**
	 * Model_TokenBucket constructor.
	 *
	 * @param string $identifier The identifier for the bucket record in the database
	 * @param int    $bucket_size The maximum capacity of the bucket.
	 * @param float  $tokens_per_second The number of tokens per second added to the bucket.
	 * @param string $backing The backing storage to use.
	 */
	public function __construct($identifier, $bucket_size, $tokens_per_second, $backing = self::BACKING_WP_OPTIONS)
	{
		$this->_identifier      = $identifier;
		$this->_bucket_size      = $bucket_size;
		$this->_tokens_per_second = $tokens_per_second;
		$this->_backing         = $backing;

		if (self::BACKING_REDIS == $backing) {
			$this->_redis = new \Redis();
			$this->_redis->pconnect('127.0.0.1');
		}
	}

	/**
	 * Attempts to acquire a lock for the bucket.
	 *
	 * @param int $timeout Lock timeout in seconds.
	 * @return bool Whether or not the lock was acquired.
	 */
	private function lock($timeout = 30): bool
	{
		if (self::BACKING_WP_OPTIONS == $this->_backing) {
			$start = microtime(true);
			while (! $this->wp_options_create_lock($this->_identifier)) {
				if (microtime(true) - $start > $timeout) {
					return false;
				}
				usleep(5000); // 5 ms
			}
			return true;
		}
		if (self::BACKING_REDIS == $this->_backing) {
			if (null === $this->_redis) {
				return false;
			}
			$start = microtime(true);
			while (! $this->_redis->setnx('lock:' . $this->_identifier, '1')) {
				if (microtime(true) - $start > $timeout) {
					return false;
				}
				usleep(5000); // 5 ms
			}
			$this->_redis->expire('lock:' . $this->_identifier, 30);
			return true;
		}
		return false;
	}

	/**
	 * Releases the bucket lock.
	 */
	private function unlock(): void
	{
		if (self::BACKING_WP_OPTIONS == $this->_backing) {
			$this->wp_options_release_lock($this->_identifier);
		} elseif (self::BACKING_REDIS == $this->_backing) {
			if (null === $this->_redis) {
				return;
			}
			$this->_redis->del('lock:' . $this->_identifier);
		}
	}

	/**
	 * Creates a WordPress-options lock.
	 *
	 * @param string   $name Lock name.
	 * @param int|null $timeout Lock timeout in seconds.
	 * @return bool
	 */
	private function wp_options_create_lock(string $name, $timeout = null)
	{
		// Our own version of WP_Upgrader::create_lock
		global $wpdb;

		if (! $timeout) {
			$timeout = 3600;
		}

		$lock_option = 'wfls_' . $name . '.lock';
		$lock_result = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')", $lock_option, time()));

		if (! $lock_result) {
			$lock_result = get_option($lock_option);
			if (! $lock_result) {
				return false;
			}

			if ($lock_result > (time() - $timeout)) {
				return false;
			}

			$this->wp_options_release_lock($name);
			return $this->wp_options_create_lock($name, $timeout);
		}

		return true;
	}

	/**
	 * Releases a WordPress-options lock.
	 *
	 * @param string $name Lock name.
	 * @return bool
	 */
	private function wp_options_release_lock(string $name)
	{
		return delete_option('wfls_' . $name . '.lock');
	}

	/**
	 * Atomically checks the available token count, creating the initial record if needed, and updates the available token count if the requested number of tokens is available.
	 *
	 * @param int $token_count Number of tokens to consume.
	 * @return bool Whether or not there were enough tokens to satisfy the request.
	 */
	public function consume($token_count = 1): bool
	{
		if (! $this->lock()) {
			return false;
		}

		if (self::BACKING_WP_OPTIONS == $this->_backing) {
			$record = get_transient('wflsbucket:' . $this->_identifier);
		} elseif (self::BACKING_REDIS == $this->_backing) {
			$record = $this->_redis->get('bucket:' . $this->_identifier);
		} else {
			$this->unlock();
			return false;
		}

		if (false === $record) {
			if ($token_count > $this->_bucket_size) {
				$this->unlock();
				return false;
			}

			$this->bootstrap($this->_bucket_size - $token_count);
			$this->unlock();
			return true;
		}

		$tokens = min($this->seconds_to_tokens(microtime(true) - (float) $record), $this->_bucket_size);
		if ($token_count > $tokens) {
			$this->unlock();
			return false;
		}

		if (self::BACKING_WP_OPTIONS === $this->_backing) {
			set_transient('wflsbucket:' . $this->_identifier, (string) (microtime(true) - $this->tokens_to_seconds($tokens - $token_count)), (int) ceil($this->tokens_to_seconds($this->_bucket_size)));
		} elseif (self::BACKING_REDIS === $this->_backing) {
			$this->_redis->set('bucket:' . $this->_identifier, (string) (microtime(true) - $this->tokens_to_seconds($tokens - $token_count)));
		}

		$this->unlock();
		return true;
	}

	/**
	 * Resets the bucket state.
	 *
	 * @return bool|null
	 */
	public function reset(): ?bool
	{
		if (! $this->lock()) {
			return false;
		}

		if (self::BACKING_WP_OPTIONS == $this->_backing) {
			delete_transient('wflsbucket:' . $this->_identifier);
		} elseif (self::BACKING_REDIS == $this->_backing) {
			$this->_redis->del('bucket:' . $this->_identifier);
		}

		$this->unlock();
		return null;
	}

	/**
	 * Creates an initial record with the given number of tokens.
	 *
	 * @param int $initial_tokens Initial available token count.
	 */
	protected function bootstrap($initial_tokens)
	{
		$microtime = microtime(true) - $this->tokens_to_seconds($initial_tokens);
		if (self::BACKING_WP_OPTIONS == $this->_backing) {
			set_transient('wflsbucket:' . $this->_identifier, (string) $microtime, (int) ceil($this->tokens_to_seconds($this->_bucket_size)));
		} elseif (self::BACKING_REDIS == $this->_backing) {
			$this->_redis->set('bucket:' . $this->_identifier, (string) $microtime);
		}
	}

	/**
	 * Converts token count to refill time in seconds.
	 *
	 * @param int|float $tokens Token count.
	 * @return int|float
	 */
	protected function tokens_to_seconds($tokens): int|float
	{
		return $tokens / $this->_tokens_per_second;
	}

	/**
	 * Converts elapsed seconds to available tokens.
	 *
	 * @param int|float $seconds Elapsed time in seconds.
	 * @return int|float
	 */
	protected function seconds_to_tokens($seconds): int|float
	{
		return (int) $seconds * $this->_tokens_per_second;
	}
}
