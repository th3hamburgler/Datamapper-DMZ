<?php


/**
 * Iterator for get_iterated
 *
 * @package DMZ
 */
class DM_DatasetIterator implements Iterator, Countable
{
	/**
	 * The parent DataMapper object that contains important info.
	 * @var DataMapper
	 */
    protected $parent;
	/**
	 * The temporary DM object used in the loops.
	 * @var DataMapper
	 */
    protected $object;
	/**
	 * Results array
	 * @var array
	 */
    protected $result;
	/**
	 * Number of results
	 * @var int
	 */
    protected $count;
	/**
	 * Current position
	 * @var int
	 */
    protected $pos;

	/**
	 * @param DataMapper $object Should be cloned ahead of time
	 * @param DB_result $query result from a CI DB query
	 */
    function __construct($object, $query)
    {
		// store the object as a main object
		$this->parent = $object;
		// clone the parent object, so it can be manipulated safely.
		$this->object = $object->get_clone();

		// Now get the information on the current query object
        $this->result = $query->result();
        $this->count = count($this->result);
        $this->pos = 0;
    }

	/**
	 * Gets the item at the current index $pos
	 * @return DataMapper
	 */
    function current()
    {
		return $this->get($this->pos);
    }

    function key()
    {
        return $this->pos;
    }

	/**
	 * Gets the item at index $index
	 * @param int $index
	 * @return DataMapper
	 */
	function get($index) {
		// clear to ensure that the item is not duplicating data
		$this->object->clear();
		// set the current values on the object
        $this->parent->_to_object($this->object, $this->result[$index]);
        return $this->object;
	}

    function next()
    {
        $this->pos++;
    }

    function rewind()
    {
        $this->pos = 0;
    }

    function valid()
    {
        return ($this->pos < $this->count);
    }

	/**
	 * Returns the number of results
	 * @return int
	 */
    function count()
    {
        return $this->count;
    }

	// Alias for count();
	function result_count() {
		return $this->count;
	}
}

// leave this line