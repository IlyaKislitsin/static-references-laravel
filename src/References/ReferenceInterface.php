<?php

namespace AvtoDev\StaticReferencesLaravel\References;

use Illuminate\Support\Collection;

/**
 * Interface ReferenceInterface.
 *
 * @mixin Collection
 */
interface ReferenceInterface
{
    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all();

    /**
     * Dump the collection and end the script.
     *
     * @return void
     */
    public function dd();

    /**
     * Dump the collection.
     *
     * @return $this
     */
    public function dump();

    /**
     * Execute a callback over each item.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function each(callable $callback);

    /**
     * Run a filter over each of the items.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function filter(callable $callback = null);

    /**
     * Get the first item from the collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null);

    /**
     * Get an item from the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @param  int|null  $number
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random($number = null);


}
