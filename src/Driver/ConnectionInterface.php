<?php


namespace Mocodo\Db\Driver;


interface ConnectionInterface
{
    /**
     * @param string $query
     * @param array $params
     * @return \PDOStatement
     */
    public function find($query, array $params = []);

    /**
     * @param string $query
     * @param array $params
     * @return \PDOStatement
     */
    public function findOne($query, array $params = []);

    /**
     * @param string $query
     * @param array $params
     * @param bool $single
     * @return string
     */
    public function dumpQuery($query, array $params = [], $single = true);
}