<?php

/**
 * Document Table Storage
 */

namespace attitude\Elements\StorageTable;

use \attitude\Elements\DependencyContainer;
use \attitude\Elements\Storage\Blob_Interface;

/**
 * Document Table Storage Class
 *
 * Persistent database table storage engine.
 *
 * @author Martin Adamko <@martin_adamko>
 * @version v0.1.0
 * @licence MIT
 *
 */
abstract class Blob_Prototype extends StorageTable_Prototype implements Object_Interface
{
    /**
     * Created timestamp column name
     *
     * @var string
     */
    protected static $created_column = null;

    /**
     * Updated timestamp column name
     *
     * @var string
     */
    protected static $updated_column = null;

    /**
     * Document body column name
     *
     * @var string
     */
    protected static $body_column    = null;

    /**
     * Seliarizer object
     *
     * @var \attitude\Implementations\Data\Serializer
     */
    protected static $data_serializer = null;

    /**
     * Class constructor
     *
     * Protected visibility allows building singleton class.
     *
     * @param   void
     * @returns object  Returns `$this`
     *
     */
    protected function __construct()
    {
        static::$database_engine = DependencyContainer::get(get_called_class().'::$database_engine');
        static::$data_serializer = DependencyContainer::get(get_called_class().'::$data_serializer');

        static::$table_name = DependencyContainer::get(get_called_class().'::$table_name');

        $this->setColumnDependency('primary_key',    DependencyContainer::get(get_called_class().'::$primary_key'));
        $this->setColumnDependency('created_column', DependencyContainer::get(get_called_class().'::$created_column'));
        $this->setColumnDependency('updated_column', DependencyContainer::get(get_called_class().'::$updated_column'));
        $this->setColumnDependency('body_column',    DependencyContainer::get(get_called_class().'::$body_column'));

        if (!$this->setup()) {
            trigger_error('Cannot setup table for `'.get_called_class().'` class.', E_USER_ERROr);
        }

        return $this;
    }

    /**
     * Returns Universally Unique IDentifier
     *
     * See https://gist.github.com/dahnielson/508447
     *
     * @param   void
     * @returns string  32 bit hexadecimal hash
     *
     */
    public function uuid()
    {
        return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',

        // 32 bits for "time_low"
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),

        // 16 bits for "time_mid"
        mt_rand(0, 0xffff),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand(0, 0x0fff) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand(0, 0x3fff) | 0x8000,

        // 48 bits for "node"
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Returns part of where SQL clause by provided key
     *
     * @internal
     *
     * @param   string  $key
     * @returns string
     *
     */
    protected function lookupIdentifier($key)
    {
        return sprintf(" `".static::$primary_key->name()."`='%s' ", $key);
    }

    /**
     * Setup document table for storage
     *
     * Checks if table exists and attempts to create one if not.
     *
     * @param   void
     * @returns bool    Returns TRUE if table exists or has been created
     *                  successfuly and returns FALSE on failure.
     *
     */
    protected function setup()
    {
        if (count(static::$database_engine->fetch('SHOW TABLES LIKE ?', array(static::$table_name)))===0) {
            $build_sql =
'CREATE TABLE `'.static::$table_name.'`
(
    `'.static::$primary_key->name().'`    '.static::$primary_key->describe().',
    `'.static::$created_column->name().'` '.static::$created_column->describe().',
    `'.static::$updated_column->name().'` '.static::$updated_column->describe().',
    `'.static::$body_column->name().'`    '.static::$body_column->describe().',

    PRIMARY KEY (`'.static::$primary_key->name().'`),
    KEY `'.static::$created_column->name().'` (`'.static::$created_column->name().'`),
    KEY `'.static::$updated_column->name().'` (`'.static::$updated_column->name().'`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

            return static::$database_engine->execute($build_sql);
        }

        return true;
    }

    /**
     * Drops table
     *
     * @param   void
     * @returns bool    Returns TRUE if table exists or has been created
     *                  successfuly and returns FALSE on failure.
     *
     */
    protected function unsetup()
    {
        return static::$database_engine->execute('DROP TABLE '.static::$table_name);
    }

    /**
     * Flushes table
     *
     * Truncates table to 0 rows to start fresh
     *
     * @param   void
     * @returns bool    Returns TRUE if table exists or has been created
     *                  successfuly and returns FALSE on failure.
     *
     */
    protected function truncate()
    {
        return static::$database_engine->execute('TRUNCATE '.static::$table_name);
    }

    /**
     * Stores new variable in the storage without knowing a key
     *
     * Storage::store() stores var in the storage returning key on success.
     *
     * @uses    DocumentStorage::uuid()
     * @uses    DocumentStorage::add()
     * @param   mixed   $var    The variable to store. Strings and integers are
     *                          stored as is, other types are stored serialized.
     * @returns mixed           Returns associated key on success or FALSE on
     *                          failure.
     *
     */
    public function store($var)
    {
        $key = $this->uuid();

        $var[static::$primary_key->name()] = $key;

        ksort($var);

        if ($this->add($key, $var)) {
            return $key;
        }

        return false;
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
    public function insert($key, $var)
    {
        $now = time();

        $var[static::$primary_key->name()] = $key;
        $var[static::$created_column->name()] = isset($var[static::$created_column->name()]) ? $var[static::$created_column->name()] : $now;
        $var[static::$updated_column->name()] = isset($var[static::$updated_column->name()]) ? $var[static::$updated_column->name()] : $now;

        return parent::insert($key, array(
            static::$primary_key->name() => $key,
            static::$created_column->name() => $now,
            static::$updated_column->name() => $now,
            static::$body_column->name() => $this->serialize($var)
        ));
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
    public function update($key, $var)
    {
        $now = time();

        $var[static::$primary_key->name()] = $key;
        $var[static::$updated_column->name()] = isset($var[static::$updated_column->name()]) ? $var[static::$updated_column->name()] : $now;

        return parent::update($key, array(
            static::$primary_key->name() => $key,
            static::$updated_column->name() => $now,
            static::$body_column->name() => $this->serialize($var)
        ));
    }
}
