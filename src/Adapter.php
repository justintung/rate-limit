<?php

namespace Detain\RateLimit;

/**
 * @author Peter Chung <touhonoob@gmail.com>
 * @date May 16, 2015
 */
abstract class Adapter
{
    /**
     * @return bool
     * @param string $key
     * @param float|mixed $value
     * @param int $ttl - seconds after which this entry will expire e.g 50
     */
    abstract public function set($key, $value, $ttl);

    /**
     * @param string $key
     * @return float|mixed
     */
    abstract public function get($key);

    /**
     * @param string $key
     * @return bool
     */
    abstract public function exists($key);

    /**
     * @return bool
     * @param string $key
     */
    abstract public function del($key);
}
