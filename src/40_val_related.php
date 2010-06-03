<?php class DataMapper {
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Related Validation methods                                        *
	 *                                                                   *
	 * The following are methods used to validate the                    *
	 * relationships of this object.                                     *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */


	// --------------------------------------------------------------------

	/**
	 * Related Required (pre-process)
	 *
	 * Checks if the related object has the required related item
	 * or if the required relation already exists.
	 *
	 * @ignore
	 */	
	protected function _related_required($object, $model)
	{
		return ($this->_count_related($model, $object) == 0) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Related Min Size (pre-process)
	 *
	 * Checks if the value of a property is at most the minimum size.
	 * 
	 * @ignore
	 */
	protected function _related_min_size($object, $model, $size = 0)
	{
		return ($this->_count_related($model, $object) < $size) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Related Max Size (pre-process)
	 *
	 * Checks if the value of a property is at most the maximum size.
	 *
	 * @ignore
	 */
	protected function _related_max_size($object, $model, $size = 0)
	{
		return ($this->_count_related($model, $object) > $size) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

}
