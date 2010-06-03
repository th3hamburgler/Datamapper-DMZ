<? class DataMapper {

	/**
	 * Stores the shared configuration
	 * @var array
	 */
	static $config = array();
	/**
	 * Stores settings that are common across a specific Model
	 * @var array
	 */
	static $common = array(DMZ_CLASSNAMES_KEY => array());
	/**
	 * Stores global extensions
	 * @var array
	 */
	static $global_extensions = array();
	/**
	 * Used to override unset default properties.
	 * @var array
	 */
	static $_dmz_config_defaults = array(
		'timestamp_format' => 'Y-m-d H:i:s O',
		'created_field' => 'created',
		'updated_field' => 'updated',
		'extensions_path' => 'datamapper',
		'field_label_lang_format' => '${model}_${field}',
	);

	/**
	 * Contains any errors that occur during validation, saving, or other
	 * database access.
	 * @var DM_Error_Object
	 */
	public $error;
	/**
	 * Used to keep track of the original values from the database, to
	 * prevent unecessarily changing fields.
	 * @var object
	 */
	public $stored;
	/**
	 * DB Table Prefix
	 * @var string
	 */
	public $prefix = '';
	/**
	 * DB Join Table Prefix
	 * @var string
	 */
	public $join_prefix = '';
	/**
	 * The name of the table for this model (may be automatically generated
	 * from the classname).
	 * @var string
	 */
	public $table = '';
	/**
	 * The singular name for this model (may be automatically generated from
	 * the classname).
	 * @var string
	 */
	public $model = '';
	/**
	 * Can be used to override the default database behavior.
	 * @var mixed
	 */
	public $db_params = '';
	/**
	 * Prefix string used when reporting errors.
	 * @var string
	 */
	public $error_prefix = '';
	/**
	 * Suffic string used when reporting errors.
	 * @var string
	 */
	public $error_suffix = '';
	/**
	 * Custom name for the automatic timestamp saved with new objects.
	 * Defaults to 'created'.
	 * @var string
	 */
	public $created_field = '';
	/**
	 * Custom name for the automatic timestamp saved when an object changes.
	 * Defaults to 'updated'.
	 * @var string
	 */
	public $updated_field = '';
	/**
	 * If TRUE, automatically wrap every save and delete in a transaction.
	 * @var bool
	 */
	public $auto_transaction = FALSE;
	/**
	 * If TRUE, has_many relationships are automatically loaded when accessed.
	 * Not recommended in most situations.
	 * @var bool
	 */
	public $auto_populate_has_many = FALSE;
	/**
	 * If TRUE, has_one relationships are automatically loaded when accessed.
	 * Not recommended in some situations.
	 * @var bool
	 */
	public $auto_populate_has_one = FALSE;
	/**
	 * Enables the old method of storing the all array using an object's ID.
	 * @var bool
	 */
	public $all_array_uses_ids = FALSE;
	/**
	 * The result of validate is stored here.
	 * @var bool
	 */
	public $valid = FALSE;
	/**
	 * If TRUE, the created/updated fields are stored using local time.
	 * If FALSE (the default), they are stored using UTC
	 * @var bool
	 */
	public $local_time = FALSE;
	/**
	 * If TRUE, the created/updated fields are stored as a unix timestamp,
	 * as opposed to a formatted string.
	 * Defaults to FALSE.
	 * @var bool
	 */
	public $unix_timestamp = FALSE;
	/**
	 * Set to a date format to override the default format of
	 *	'Y-m-d H:i:s O'
	 * @var string
	 */
	public $timestamp_format = '';
	/**
	 * Contains the database fields for this object.
	 * ** Automatically configured **
	 * @var array
	 */
	public $fields = array();
	/**
	 * Set to a string to use when autoloading lang files.
	 * Can contain two magic values: ${model} and ${table}.
	 * These are automatically
	 * replaced when looking up the language file.
	 * Defaults to model_${model}
	 * @var string
	 */
	public $lang_file_format = '';
	/**
	 * Set to a string to use when looking up field labels.  Can contain three
	 * magic values: ${model}, ${table}, and ${field}.  These are automatically
	 * replaced when looking up the language file.
	 * Defaults to ${model}_${field}
	 * @var string
	 */
	public $field_label_lang_format = '';
	/**
	 * Contains the result of the last query.
	 * @var array
	 */
	public $all = array();
	/**
	 * Semi-private field used to track the parent model/id if there is one.
	 * @var array
	 */
	public $parent = array();
	/**
	 * Contains the validation rules, label, and get_rules for each field.
	 * @var array
	 */
	public $validation = array();
	/**
	 * Contains any related objects of which this model is related one or more times.
	 * @var array
	 */
	public $has_many = array();
	/**
	 * Contains any related objects of which this model is singularly related.
	 * @var array
	 */
	public $has_one = array();
	/**
	 * Used to enable or disable the production cache.
	 * This should really only be set in the global configuration.
	 * @var bool
	 */
	public $production_cache = FALSE;
	/**
	 * Used to determine where to look for extensions.
	 * This should really only be set in the global configuration.
	 * @var string
	 */
	public $extensions_path = '';
	/**
	 * If set to an array of names, this will automatically load the
	 * specified extensions for this model.
	 * @var mixed
	 */
	public $extensions = NULL;
	/**
	 * If a query returns more than the number of rows specified here,
	 * then it will be automatically freed after a get.
	 * @var int
	 */
	public $free_result_threshold = 100;
	/**
	 * This can be specified as an array of fields to sort by if no other
	 * sorting or selection has occurred.
	 * @var mixed
	 */
	public $default_order_by = NULL;

	// tracks whether or not the object has already been validated
	protected $_validated = FALSE;
	// Tracks the columns that need to be instantiated after a GET
	protected $_instantiations = NULL;
	// Tracks get_rules, matches, and intval rules, to spped up _to_object
	protected $_field_tracking = NULL;
	// used to track related queries in deep relationships.
	protected $_query_related = array();
	// If true before a related get(), any extra fields on the join table will be added.
	protected $_include_join_fields = FALSE;
	// If true before a save, this will force the next save to be new.
	protected $_force_save_as_new = FALSE;
	// If true, the next where statement will not be prefixed with an AND or OR.
	protected $_where_group_started = FALSE;

}