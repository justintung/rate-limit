<?php

/*
 * Copyright (c) Jeroen Visser <jeroenvisser101@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Detain\RateLimit;

/**
 * Implements the Leak Bucket algorithm.
 *
 * @author Jeroen Visser <jeroenvisser101@gmail.com>
 */
class LeakyBucket
{
    /**
     * Bucket key's prefix.
     */
    public const LEAKY_BUCKET_KEY_PREFIX = 'leakybucket:v1:';

    /**
     * Bucket key's postfix.
     */
    public const LEAKY_BUCKET_KEY_POSTFIX = ':bucket';

    /**
     * The key to the bucket.
     *
     * @var string
     */
    private $key;

    /**
     * The current bucket.
     *
     * @var array<string, mixed>
     */
    private $bucket;

    /**
     * A Adapter where the bucket data will be stored.
     *
     * @var Adapter
     */
    private $storage;

    /**
     * Array containing default settings.
     *
     * @var array<string, mixed>
     */
    private static $defaults = [
        'capacity' => 10,
        'leak'     => 0.33
    ];

    /**
     * The settings for this bucket.
     *
     * @var array<string, mixed>
     */
    private $settings = [];

    /**
     * Class constructor.
     *
     * @param string           $key      The bucket key
     * @param Adapter $storage  The storage provider that has to be used
     * @param array<string, mixed> $settings The settings to be set
     */
    public function __construct($key, Adapter $storage, array $settings = [])
    {
        $this->key     = $key;
        $this->storage = $storage;

        // Make sure only existing settings can be set
        $settings       = array_intersect_key($settings, self::$defaults);
        $this->settings = array_merge(self::$defaults, $settings);

        $this->bucket = $this->get();

        // Initialize the bucket
        if (!isset($this->bucket['drops']) || !isset($this->bucket['time'])) {
            $this->bucket = [
                'drops' => 0,
                'time'  => microtime(true)
            ];
        }
    }

    /**
     * Fills the bucket with a given amount of drops.
     *
     * @param int $drops Amount of drops that have to be added to the bucket
     * @return LeakyBucket
     */
    public function fill($drops = 1)
    {
        if (!$drops > 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The parameter "%s" has to be an integer greater than 0.',
                    '$drops'
                )
            );
        }

        // Make sure the key is at least zero
        $this->bucket['drops'] = $this->bucket['drops'] ?: 0;

        // Update the bucket
        $this->bucket['drops'] += $drops;

        $this->overflow();
        return $this;
    }

    /**
     * Spills a few drops from the bucket.
     *
     * @param int $drops Amount of drops to spill from the bucket
     * @return LeakyBucket
     */
    public function spill($drops = 1)
    {
        // Make sure the key is at least zero
        $this->bucket['drops'] = $this->bucket['drops'] ?: 0;

        $this->bucket['drops'] -= $drops;

        // Make sure we don't set it less than zero
        if ($this->bucket['drops'] < 0) {
            $this->bucket['drops'] = 0;
        }
        return $this;
    }

    /**
     * Attach aditional data to the bucket.
     *
     * @param mixed $data The data to be attached to this bucket
     * @return LeakyBucket
     */
    public function setData($data)
    {
        $this->bucket['data'] = $data;
        return $this;
    }

    /**
     * Get additional data from the bucket.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->bucket['data'];
    }

    /**
     * Gets the total capacity.
     *
     * @return float
     */
    public function getCapacity()
    {
        return (float) $this->settings['capacity'];
    }

    /**
     * Gets the amount of drops inside the bucket.
     *
     * @return float
     */
    public function getCapacityUsed()
    {
        return (float) $this->bucket['drops'];
    }

    /**
     * Gets the capacity that is still left.
     *
     * @return float
     */
    public function getCapacityLeft()
    {
        return (float) $this->settings['capacity'] - $this->bucket['drops'];
    }

    /**
     * Get the leak setting's value.
     *
     * @return float
     */
    public function getLeak()
    {
        return (float) $this->settings['leak'];
    }

    /**
     * Gets the last timestamp set on the bucket.
     *
     * @return mixed
     */
    public function getLastTimestamp()
    {
        return $this->bucket['time'];
    }

    /**
     * Updates the bucket's timestamp
     *
     * @return LeakyBucket
     */
    public function touch()
    {
        $this->bucket['time'] = microtime(true);
        return $this;
    }

    /**
     * Returns true if the bucket is full.
     *
     * @return bool
     */
    public function isFull()
    {
        return (ceil((float) $this->bucket['drops']) >= $this->settings['capacity']);
    }

    /**
     * Calculates how much the bucket has leaked.
     * @return LeakyBucket
     */
    public function leak()
    {
        // Calculate the leakage
        $elapsed = microtime(true) - $this->bucket['time'];
        $leakage = $elapsed * $this->settings['leak'];

        // Make sure the key is at least zero
        $this->bucket['drops'] = $this->bucket['drops'] ?: 0;
        $this->bucket['drops'] -= $leakage;

        // Make sure we don't set it less than zero
        if ($this->bucket['drops'] < 0) {
            $this->bucket['drops'] = 0;
        }
        return $this;
    }

    /**
     * Removes the overflow if present.
     * @return LeakyBucket
     */
    public function overflow()
    {
        if ($this->bucket['drops'] > $this->settings['capacity']) {
            $this->bucket['drops'] = $this->settings['capacity'];
        }
        return $this;
    }

    /**
     * Saves the bucket to the Adapter used.
     * @return LeakyBucket
     */
    public function save()
    {
        // Set the timestamp
        $this->touch();
        $this->set($this->bucket, intval($this->settings['capacity'] / $this->settings['leak'] * 1.5));
        return $this;
    }

    /**
     * Resets the bucket.
     *
     * @throws \Exception
     * @return LeakyBucket
     */
    public function reset()
    {
        try {
            $this->storage->del(static::LEAKY_BUCKET_KEY_PREFIX . $this->key . static::LEAKY_BUCKET_KEY_POSTFIX);
        } catch (\Exception $ex) {
            throw new \Exception(sprintf('Could not save "%s" to storage provider.', $this->key));
        }
        return $this;
    }

    /**
     * Sets the active bucket's value
     *
     * @param array<string, mixed> $bucket The bucket's contents
     * @param int   $ttl    The time to live for the bucket	 *
     * @throws \Exception
     * @return LeakyBucket
     */
    private function set(array $bucket, $ttl = 0)
    {
        try {
            $this->storage->set(static::LEAKY_BUCKET_KEY_PREFIX . $this->key . static::LEAKY_BUCKET_KEY_POSTFIX, serialize($bucket), $ttl);
        } catch (\Exception $ex) {
            throw new \Exception(sprintf('Could not save "%s" to storage provider.', $this->key));
        }
        return $this;
    }

    /**
     * Gets the active bucket's value
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    private function get()
    {
        try {
            return unserialize($this->storage->get(static::LEAKY_BUCKET_KEY_PREFIX . $this->key . static::LEAKY_BUCKET_KEY_POSTFIX));
        } catch (\Exception $ex) {
            throw new \Exception(sprintf('Could not save "%s" to storage provider.', $this->key));
        }
    }
}
