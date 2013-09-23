<?php

/**
 * Database Connection
 */

namespace attitude\Elements;

/**
 * Database Connection abstract class
 *
 * Persistent database storage engine.
 *
 * @author Martin Adamko <@martin_adamko>
 * @version v0.1.0
 * @licence MIT
 *
 */
abstract class StorageTable_Connection implements StorageTable_ConnectionInterface
{
    /**
     * Connection this class wraps
     *
     * @var PDO $conection
     *
     */
    private $connection = null;

    /**
     * Last PDO Statement instance
     *
     * @var PDOStatemnt
     *
     */
    protected static $last_pdo_statement = null;

    /**
     * Database Connection class constructor
     *
     * @param   void
     * @returns object  $this
     *
     */
    protected function __construct()
    {
        try {
            $this->connection = new \PDO(
                DependencyContainer::get(get_called_class().'::$dsn'),                    // string $dsn
                DependencyContainer::get(get_called_class().'::$username'),               // string $username
                DependencyContainer::get(get_called_class().'::$password'),               // string $password
                DependencyContainer::get(get_called_class().'::$driver_options', array()) // array $driver_options
            );

            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (HTTPException $e) {
            throw $e;
        }
    }

    /**
     * Dependency setter method
     *
     * @param   PDO     $connection
     * @returns object              Returns $this
     *
     */
    public function setConnectionDependency(PDO $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Dependency getter method
     *
     * @param   void
     * @returns object  PDO connection instance
     *
     */
    public function getConnectionDependency()
    {
        return $this->connection;
    }

    /**
     * Forwarded method
     *
     * @see http://php.net/manual/en/pdo.getattribute.php
     *
     */
    public function getAttribute(/*int*/ $attribute)
    {
        return $this->connection->getAttribute($attribute);
    }

    /**
     * Forwarded method
     *
     * @see http://php.net/manual/en/pdo.setattribute.php   PDO Documentation
     *
     */
    public function setAttribute(/*int*/ $attribute, /*mixed*/ $value)
    {
        return $this->connection->setAttribute($attribute, $value);
    }

    /**
     * Returns last query
     *
     * Returns last query as `PDOStatement` class instance
     *
     * @param   void
     * @returns PDOStatment
     *
     */
    public function lastQuery()
    {
        return static::$last_pdo_statement;
    }

    /**
     * Executes a query
     *
     * @param   string  $query  This must be a valid SQL statement for the target database server.
     * @param   array   $args   An array of values with as many elements as there are bound parameters in the SQL statement being executed.
     * @returns bool            Returns TRUE on success or FALSE on failure.
     *
     */
    public function execute($query, $args=array())
    {
        /* $this->logger->log($query, $args); */

        try {
            $pdo_statement = $this->connection->prepare($query, $args);
            static::$last_pdo_statement =& $pdo_statement;

            $return = $pdo_statement->execute($args);
        } catch (HTTPException $e) {
            throw $e;
        }

        return $return;
    }

    /**
     * Executes a query and returs results
     *
     * @param   string  $query  This must be a valid SQL statement for the target database server.
     * @param   array   $args   An array of values with as many elements as there are bound parameters in the SQL statement being executed.
     * @returns mixed           Returns array of rows on success or FALSE on failure.
     *
     */
    public function fetch($query, $args=array())
    {
        /* $this->logger->log($query, $args); */

        try {
            $this->execute($query, $args);
        } catch (HTTPException $e) {
            trigger_error($e->getMessage());

            throw $e;
        }

        $rows = array();
        while ($row = static::$last_pdo_statement->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }
}
