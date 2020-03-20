<?php

namespace Detain\RateLimit\Adapter;

class Memcached extends \Detain\RateLimit\Adapter
{

    /**
     * @var \Memcached
     */
    protected $memcached;

    public function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    public function set($key, $value, $ttl)
    {
        error_log('memcacheds set "'.$key.'" to "'.$value.'"');
        return $this->memcached->set($key, $value, $ttl);
    }

    /**
     * @return float
     * @param string $key
     */
    public function get($key)
    {
        $val = $this->_get($key);
        error_log('memcacheds get "'.$key.'" = "'.$val.'" or float = "'.((float)$val).'"');
        return (float) $val;
    }

    /**
     * @return bool|float
     * @param string $key
     */
    private function _get($key)
    {
        return $this->memcached->get($key);
    }

    public function exists($key)
    {
        $val = $this->_get($key);
        return $val !== false;
    }

    public function del($key)
    {
        return $this->memcached->delete($key);
    }
}
