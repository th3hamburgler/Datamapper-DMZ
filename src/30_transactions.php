<?php class DataMapper {
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                                   *
	 * Transaction methods                                               *
	 *                                                                   *
	 * The following are methods used for transaction handling.          *
	 *                                                                   *
	 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */


	// --------------------------------------------------------------------

	/**
	 * Trans Off
	 *
	 * This permits transactions to be disabled at run-time.
	 *
	 */	
	public function trans_off()
	{
		$this->db->trans_enabled = FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Trans Strict
	 *
	 * When strict mode is enabled, if you are running multiple groups of
	 * transactions, if one group fails all groups will be rolled back.
	 * If strict mode is disabled, each group is treated autonomously, meaning
	 * a failure of one group will not affect any others.
	 *
	 * @param	bool $mode Set to false to disable strict mode.
	 */	
	public function trans_strict($mode = TRUE)
	{
		$this->db->trans_strict($mode);
	}

	// --------------------------------------------------------------------

	/**
	 * Trans Start
	 *
	 * Start a transaction.
	 *
	 * @param	bool $test_mode Set to TRUE to only run a test (and not commit)
	 */	
	public function trans_start($test_mode = FALSE)
	{	
		$this->db->trans_start($test_mode);
	}

	// --------------------------------------------------------------------

	/**
	 * Trans Complete
	 *
	 * Complete a transaction.
	 *
	 * @return	bool Success or Failure
	 */	
	public function trans_complete()
	{
		return $this->db->trans_complete();
	}

	// --------------------------------------------------------------------

	/**
	 * Trans Begin
	 *
	 * Begin a transaction.
	 *
	 * @param	bool $test_mode Set to TRUE to only run a test (and not commit)
	 * @return	bool Success or Failure
	 */	
	public function trans_begin($test_mode = FALSE)
	{	
		return $this->db->trans_begin($test_mode);
	}

	// --------------------------------------------------------------------

	/**
	 * Trans Status
	 *
	 * Lets you retrieve the transaction flag to determine if it has failed.
	 *
	 * @return	bool Returns FALSE if the transaction has failed.
	 */	
	public function trans_status()
	{
		return $this->_trans_status;
	}

	// --------------------------------------------------------------------

	/**
	 * Trans Commit
	 *
	 * Commit a transaction.
	 *
	 * @return	bool Success or Failure
	 */	
	public function trans_commit()
	{
		return $this->db->trans_commit();
	}

	// --------------------------------------------------------------------

	/**
	 * Trans Rollback
	 *
	 * Rollback a transaction.
	 *
	 * @return	bool Success or Failure
	 */	
	public function trans_rollback()
	{
		return $this->db->trans_rollback();
	}

	// --------------------------------------------------------------------

	/**
	 * Auto Trans Begin
	 *
	 * Begin an auto transaction if enabled.
	 *
	 */	
	protected function _auto_trans_begin()
	{
		// Begin auto transaction
		if ($this->auto_transaction)
		{
			$this->trans_begin();
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Auto Trans Complete
	 *
	 * Complete an auto transaction if enabled.
	 *
	 * @param	string $label Name for this transaction.
	 */	
	protected function _auto_trans_complete($label = 'complete')
	{
		// Complete auto transaction
		if ($this->auto_transaction)
		{
			// Check if successful
			if (!$this->trans_complete())
			{
				$rule = 'transaction';

				// Get corresponding error from language file
				if (FALSE === ($line = $this->lang->line($rule)))
				{
					$line = 'Unable to access the ' . $rule .' error message.';
				}

				// Add transaction error message
				$this->error_message($rule, sprintf($line, $label));

				// Set validation as failed
				$this->valid = FALSE;
			}
		}
	}

	// --------------------------------------------------------------------

}
