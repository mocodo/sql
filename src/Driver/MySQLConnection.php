<?php

namespace Mocodo\Db\Driver;

class MySQLConnection extends \PDO
{
    private $query;

    private $operators = ['=', '>', '>=', '<', '<=', '!=', 'LIKE', 'IN', 'BETWEEN', 'IS'];

    public function quote($value, $parameterType = \PDO::PARAM_STR)
    {
        if (!is_array($value)) {
            return parent::quote($value, $parameterType);
        }

        foreach ($value as $k => $v) {
            $value[$k] = parent::quote($v, $parameterType);
        }

        return implode(', ', $value);
    }

    public function find($query, array $params = [])
    {
        $this->query = $query;

        if (!empty($params['conditions'])) {
            $this->conditions($params['conditions']);
        }

        if (!empty($params['group'])) {
            $this->group($params['group']);
        }

        if (!empty($params['order'])) {
            $this->order($params['order']);
        }

        if (!empty($params['limit'])) {
            if (!empty($params['offset'])) {
                $this->limit($params['limit'], $params['offset']);
            } else {
                $this->limit($params['limit']);
            }
        }

        $stmt = $this->prepare($this->query);
        $stmt->execute();

        return $stmt;
    }

    public function findOne($query, array $params = [])
    {
        $this->query = $query;

        if (!empty($params['conditions'])) {
            $this->conditions($params['conditions']);
        }

        if (!empty($params['group'])) {
            $this->group($params['group']);
        }

        if (!empty($params['order'])) {
            $this->order($params['order']);
        }

        $this->query .= ' LIMIT 1';

        $stmt = $this->prepare($this->query);
        $stmt->execute();

        return $stmt;
    }

    public function dumpQuery($query, array $params = [], $single = true)
    {
        $this->query = $query;

        if (!empty($params['conditions'])) {
            $this->conditions($params['conditions']);
        }

        if (!empty($params['group'])) {
            $this->group($params['group']);
        }

        if (!empty($params['order'])) {
            $this->order($params['order']);
        }

        if ($single) {
            $this->query .= ' LIMIT 1';
        } else {
            if (!empty($params['limit'])) {
                if (!empty($params['offset'])) {
                    $this->limit($params['limit'], $params['offset']);
                } else {
                    $this->limit($params['limit']);
                }
            }
        }

        return preg_replace('/\s+/', ' ', $this->query);
    }

    protected function conditions(array $conditions)
    {
        // default behavior assume that operator is = and concat to AND
        foreach ($conditions as $key => $value) {
            // extract the operator
            $operator = explode(' ', trim($key));
            $isOr = (!empty($operator[0]) && 'OR' === mb_strtoupper(trim($operator[0])));
            $operator = mb_strtoupper(end($operator));

            switch ($operator) {
                case '=':
                case '>':
                case '>=':
                case '<':
                case '<=':
                case '!=':
                case 'LIKE':
                    $this->query .= sprintf(
                        ' %s %s %s',
                        $isOr ? '' : 'AND',
                        $key,
                        $this->quote($value)
                    );

                    break;
                case 'IN':
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException('Expected array for IN condition');
                    }

                    $this->query .= sprintf(
                        ' %s %s (%s)',
                        $isOr ? '' : 'AND',
                        $key,
                        $this->quote($value)
                    );

                    break;
                case 'BETWEEN':
                    if (!is_array($value) || 2 != count($value)) {
                        throw new \InvalidArgumentException('Expected array of size 2 for BETWEEN condition');
                    }

                    $this->query .= sprintf(
                        ' %s %s %s AND %s',
                        $isOr ? '' : 'AND',
                        $key,
                        $this->quote($value[0]),
                        $this->quote($value[1])
                    );

                    break;
                case 'IS':
                    if (!in_array(mb_strtoupper(trim($value)), ['NULL', 'NOT NULL'])) {
                        throw new \InvalidArgumentException('Expected value of NULL of NOT NULL for IS condition');
                    }

                    $this->query .= sprintf(
                        ' %s %s %s',
                        $isOr ? '' : 'AND',
                        $key,
                        mb_strtoupper(trim($value))
                    );

                    break;
                case 'OR':
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException('Expected array for OR condition');
                    }

                    $this->query .= ' OR (1';
                    $this->conditions($value);
                    $this->query .= ')';

                    break;
                case 'AND':
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException('Expected array for AND condition');
                    }

                    $this->query .= ' AND (1';
                    $this->conditions($value);
                    $this->query .= ')';

                    break;
                default:
                    // same as =
                    $this->query .= sprintf(
                        ' %s %s = %s',
                        $isOr ? '' : 'AND',
                        $key,
                        $this->quote($value)
                    );

                    break;
            }
        }
    }

    protected function order($order)
    {
        $this->query .= ' ORDER BY ' . $order;
    }

    protected function limit($limit, $offset = null)
    {
        $this->query .= ' LIMIT ' . intval($limit);

        if ($offset) {
            $this->query .= ' OFFSET ' . intval($offset);
        }
    }

    protected function group($by)
    {
        $this->query .= ' GROUP BY' . $by;
    }

    protected function having()
    {
        throw new \RuntimeException('Method is not implemented yet');
    }

    private function parseKey($key)
    {

    }
}
