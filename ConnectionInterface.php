<?php

/**
 * PDO Connection Interface
 */

namespace attitude\Elements;

/**
 * PDO Connection Interface
 *
 * Low level PDO object wrapper
 *
 * @author Martin Adamko <@martin_adamko>
 * @version v0.1.0
 * @licence MIT
 *
 */
interface StorageTable_ConnectionInterface
{
    /**
     * Retrieve a database connection attribute
     *
     * Returns the value of a database connection attribute.
     *
     * (A forwarded method on private `$connection`)
     *
     * @param   int     $attribute  One of the PDO::ATTR_* constants.
     * @returns mixed               Retrieve a database connection attribute.
     *
     */
    public /*mixed*/ function getAttribute(/*int*/ $attribute);

    /**
     * Set a database connection attribute
     *
     * Sets an attribute on the database handle. Some of the available generic
     * attributes are listed below; some drivers may make use of additional
     * driver specific attributes.
     *
     * (A forwarded method on private `$connection`)
     *
     * @param   int     $attribute  One of the PDO::ATTR_* constants.
     * @param   mixed   $value      One of the PDO::ATTR_* constants.
     * @returns mixed               Retrieve a database connection attribute.
     *
     */
    public /*bool*/ function setAttribute (/*int*/ $attribute, /*mixed*/ $value);
}
