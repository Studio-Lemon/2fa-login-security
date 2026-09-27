<?php
/**
 * Lock abstraction.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

interface Utility_Lock {




	const DEFAULT_DELAY = 100000;

	/**
	 * Acquires the lock.
	 *
	 * @param int $delay Delay between acquisition attempts, in microseconds.
	 */
	public function acquire($delay = self::DEFAULT_DELAY);

	/**
	 * Releases the lock.
	 */
	public function release();
}
