<?php
/**
 * No-op lock implementation.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * An implementation of the Utility_Lock that doesn't actually do any locking
 */
class Utility_NullLock implements Utility_Lock {



	/**
	 * Performs no lock acquisition.
	 *
	 * @param int $delay Delay between acquisition attempts, in microseconds.
	 */
	public function acquire($delay = self::DEFAULT_DELAY): void
	{
		// Do nothing
	}

	/**
	 * Performs no lock release.
	 */
	public function release(): void
	{
		// Do nothing
	}
}
