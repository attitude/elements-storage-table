<?php

/**
 * Table Storage Prototype
 */

namespace attitude\Elements;

/**
 * Table Storage Class
 *
 * Persistent database table storage engine.
 *
 * @author Martin Adamko <@martin_adamko>
 * @version v0.1.0
 * @licence MIT
 *
 */
abstract class StorageTable_Prototype implements Storage_Interface
{
    /**
     * PDO Connection object
     *
     * @var \PDO $database_engine
     *
     */
    protected static $database_engine = null;

    /**
     * Table name
     *
     * @var string  $table_name
     *
     */
    protected static $table_name      = null;

    /**
     * Table primary key
     *
     * @var string  $primary_key
     *
     */
    protected static $primary_key     = null;

    /**
     * Is primary key autoincremented
     *
     * @var bool    $primary_key_is_autoincrement
     */
    protected static $primary_key_is_autoincrement = false; // Expect unxepected

    /**
     * List of columns
     *
     * Array of columns with metadata
     *
     * @var array
     *
     */
    protected static $columns = null;

    /**
     * Table Storage constructor
     *
     * Protected visibility allows building singleton class.
     *
     * @param   void
     * @returns object  Returns $this
     *
     */
    protected function __construct()
    {
        static::$database_engine = DependencyContainer::get(get_called_class().'::$database_engine');
        static::$data_serializer = DependencyContainer::get(get_called_class().'::$data_serializer');

        static::$table_name      = DependencyContainer::get(get_called_class().'::$table_name');

        $this->setColumnDependency('primary_key', DependencyContainer::get(get_called_class().'::$primary_key'));

        if (!$this->setup()) {
            trigger_error('Cannot setup table for `'.get_called_class().'` class.', E_USER_ERROr);
        }

        return $this;
    }

    /**
     * Sets Column Dependency
     *
     * @param   string                      $column_name
     * @param   \attitude\Elements\Storage\Column    $column
     *
     */
    public function setColumnDependency($column_name, \attitude\Interfaces\Storage\Column $column)
    {
        static::$$column_name = $column;

        return $this;
    }

    /**
     * Unserializes data using serializer object
     *
     * @param   string  $data
     *
     */
    protected function unserialize($data)
    {
        return static::$data_serializer->unserialize($data);
    }

    /**
     * Serializes data using serializer object
     *
     * @param   mixed   $data
     */
    protected function serialize($data)
    {
        return static::$data_serializer->serialize($data);
    }

    /**
     * Setup table
     *
     * Check if table exists and if not attempt to create it
     *
     * @param   void
     * @returns bool    Returns TRUE if table exists or has been created
     *                  successfuly and returns FALSE on failure.
     *
     */
    abstract protected /*boolean*/ function setup();

    /**
     * Setup table fields just in time
     *
     * @param   void
     * @returns bool
     *
     */
    protected function lazySetup()
    {
        if (static::$columns===null) {
            static::$columns = static::$database_engine->fetch('DESCRIBE `'.static::$table_name.'`');
        }

        if (empty(static::$columns)) {
            throw new HTTPException('Failed to describe table');

            return false;
        }

        foreach (static::$columns as &$column) {
            if ($column['Key']==='PRI') {
                // Check if primary key is set up properly
                if (static::$primary_key->name()!==$column['Field']) {
                    trigger_error('Configuration missmatch: Primary key is set incorrectly.', E_USER_ERROR);
                }

                if ($column['Extra']==='auto_increment') {
                    static::$primary_key_is_autoincrement = true;
                }
            }

            static::$columns[$column['Field']] = $column;
        }

        return true;
    }

    /**
     * Build where conndition to identify record to be unique
     *
     * @param   int     $key    Unique ID number
     * @returns string          SQL where statement.
     *
     */
    protected function lookupIdentifier($key)
    {
        return sprintf("\n".'`'.static::$primary_key->name().'`=%d', $key);
    }

    /**
     * Checks if record exists
     *
     * More about EXISTS: <http://stackoverflow.com/questions/1676551/best-way-to-test-if-a-row-exists-in-a-mysql-table>
     *
     * @param   int     $key    Unique ID number
     * @returns bool            Returns TRUE if record exists, FALSE if not.
     *
     */
    public function exists($key)
    {
        $exists = static::$database_engine->fetch('SELECT EXISTS (SELECT 1 FROM `'.static::$table_name.'` WHERE '.$this->lookupIdentifier($key).' LIMIT 1) as `_exists`');

        return !!$exists[0]['_exists'];
    }

    /**
     * Returns a record
     *
     * @param   int     $key    Unique ID number
     * @returns mixed
     *
     * @todo: What if $body_column is not set?
     *
     */
    public function get($key)
    {
        if ($this->exists($key)) {
            $data = static::$database_engine->fetch('SELECT * FROM `'.static::$table_name.'` WHERE '.$this->lookupIdentifier($key).' LIMIT 1');

            if (count($data)!==1) {
                trigger_error('`get()` failed for '.$key.' key.', E_USER_WARNING);

                return false;
            }

            $data =& $data[0];
            return $this->unserialize($data[static::$body_column->name()]);

            return $data;
        }

        return null;
    }

    /**
     * Add a new record
     *
     * @param   null|int    $key    Unique ID number or NULL if AUTOINCREMENT
     * @param   mixed       $var    Data/Object to store
     * @returns boolean|int
     *
     */
    public function add($key, $var)
    {
        if ($this->exists($key)) {
            return false;
        }

        return $this->insert($key, $var);
    }

    /**
     * Sets new record or replaces old one
     *
     * @param   int         $key    Unique ID number or NULL if AUTOINCREMENT
     * @param   mixed       $var    Data/Object to store
     * @returns boolean|int
     *
     */
    public function set($key, $var)
    {
        if ($this->exists($key)) {
            return $this->update($key, $var);
        }

        return $this->insert($key, $var);
    }

    /**
     * Replaces an existing record
     *
     * @param   int         $key    Unique ID number or NULL if AUTOINCREMENT
     * @param   mixed       $var    Data/Object to store
     * @returns boolean|int
     *
     */
    public function replace($key, $var)
    {
        if (!$this->exists($key)) {
            return false;
        }

        return $this->update($key, $var);
    }

    /**
     * Insert a new record
     *
     * Called internally to make sure a record does not exist.
     *
     * @param   int         $key    Unique ID number or NULL if AUTOINCREMENT
     * @param   mixed       $var    Data/Object to store
     * @returns boolean|int         Last inserted ID or FALSE on failure
     *
     */
    protected function insert($key, $var)
    {
        $this->lazySetup();

        // Cast object as array
        if (is_object($var)) {
            $var = (array) $var;
        }

        if (!is_array($var)) {
            trigger_error('Only `object` and `array` types are permited.', E_USER_WARNING);

            return false;
        }

        if ($key===null) {
            // Remove primary key from $var if not specifically passed
            if (array_key_exists(static::$primary_key->name(), $var)) {
                unset($var[static::$primary_key->name()]);
            }
        } else {
            // Add primary key if missing in $var
            if (!array_key_exists(static::$primary_key->name(), $var)) {
                $var[static::$primary_key->name()] = $key;
            }
        }

        // Build query
        $query = array();
        $query[] ='INSERT INTO `'.static::$table_name.'`';

        $fields_list = array();
        $values_list = array();

        foreach ((array) $var as $field => $value) {
            if (array_key_exists($field, static::$columns)) {
                $fields_list[] = '`'.$field.'`';
                $values_list[] = $value;
            } else {
                trigger_error('Cannot store values for unexisting `'.$field.'` field. Ignoring.', E_USER_NOTICE);
            }
        }

        $query[] = "(" . join(", ", $fields_list) . ")";
        $query[] = "VALUES";
        $query[] = "(". trim(str_repeat('?,', count($fields_list)), ',') .")";

        if (static::$database_engine->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'pgsql') {
            $query[] = 'RETURNING ' . $this->_quote_identifier($this->_get_id_column_name());
        }

        $query = implode(' ', $query);

        if (static::$database_engine->execute($query, $values_list)) {
            if (isset($var[static::$primary_key->name()]) && !empty($var[static::$primary_key->name()])) {
                return $var[static::$primary_key->name()];
            }

            if(static::$database_engine->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'pgsql') {
                return static::$last_pdo_statement()->fetchColumn();
            } else {
                return static::$database_engine->lastInsertId();
            }
        }

        trigger_error('Failed to insert into `'.static::$table_name.'` table');

        return false;
    }

    /**
     * Updata a stored record in database
     *
     * Called internally to make sure a record exists.
     *
     * @param   int         $key    Unique ID number or NULL if AUTOINCREMENT
     * @param   mixed       $var    Data/Object to store
     * @returns boolean|int         Last inserted ID or FALSE on failure
     *
     */
    protected function update($key, $var)
    {
        $this->lazySetup();

        if ($key===null) {
            trigger_error('Update requires `$key` to be a non-empty value', E_USER_WARNING);

            return false;
        }

        // Cast object as array
        if (is_object($var)) {
            $var = (array) $var;
        }

        if (!is_array($var)) {
            trigger_error('Only `object` and `array` types are permited.', E_USER_WARNING);

            return false;
        }

        // Add primary key if missing in $var
        if (!array_key_exists(static::$primary_key->name(), $var)) {
            $var[static::$primary_key->name()] = $key;
        }

        // Build query
        $query = array();
        $query[] ='UPDATE `'.static::$table_name.'` SET';

        $field_as_values = array();
        $values_list = array();

        foreach ((array) $var as $field => $value) {
            if (array_key_exists($field, static::$columns)) {
                $field_as_values[] = '`'.$field.'`=?';
                $values_list[] = $value;
            } else {
                trigger_error('Cannot store values for unexisting `'.$field.'` field. Ignoring.', E_USER_NOTICE);
            }
        }

        $query[] = join(", ", $field_as_values);
        $query[] = "WHERE ".$this->lookupIdentifier($key);

        $query = implode(' ', $query);

        if (static::$database_engine->execute($query, $values_list)) {
            return $var[static::$primary_key->name()];
        }

        trigger_error('Failed to update `'.$key.'` key in `'.static::$table_name.'` table');

        return false;
    }

    /**
     * Destroys document
     *
     * @param   string  $key    The key associated with the item to delete.
     * @returns null|bool       Returns TRUE on success or FALSE on failure.
     *                          NULL is being returned when there is nothing to
     *                          destroy.
     *
     */
    public function delete($key)
    {
        if (!$this->exists($key)) {
            return null;
        }

        return static::$database_engine->execute('DELETE FROM `'.static::$table_name.'` WHERE '.$this->lookupIdentifier($key));
    }
}
