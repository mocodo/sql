<?php

namespace Mocodo\Db\Driver;

class MySQLConnection extends \PDO implements ConnectionInterface
{
    private $query;

    private $preparedParams = [];

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
        $this->preparedParams = [];
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
        $stmt->execute($this->preparedParams);

        return $stmt;
    }

    public function findOne($query, array $params = [])
    {
        $this->preparedParams = [];
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
        $stmt->execute($this->preparedParams);

        return $stmt;
    }

    public function dumpQuery($query, array $params = [], $single = true)
    {
        $this->preparedParams = [];
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
            $keyAttributes = $this->parseKey($key);
            $placeholder = uniqid(':');

            switch ($keyAttributes['operator']) {
                case '=':
                case '>':
                case '>=':
                case '<':
                case '<=':
                case '!=':
                case 'LIKE':
                case 'NOT LIKE':
                    $this->query .= sprintf(
                        ' %s %s %s %s',
                        $keyAttributes['type'],
                        $keyAttributes['field'],
                        $keyAttributes['operator'],
                        $placeholder
                    );

                    $this->preparedParams[$placeholder] = $value;

                    break;
                case 'IN':
                case 'NOT IN':
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException('Expected array for IN condition');
                    }

                    $index = 1;
                    $placeholders = [];
                    foreach ($value as $k => $v) {
                        $this->preparedParams[$placeholder . $index] = $v;
                        $placeholders[] = $placeholder . $index;
                        $index++;
                    }

                    $this->query .= sprintf(
                        ' %s %s %s (%s)',
                        $keyAttributes['type'],
                        $keyAttributes['field'],
                        $keyAttributes['operator'],
                        implode(', ', $placeholders)
                    );

                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    if (!is_array($value) || 2 != count($value)) {
                        throw new \InvalidArgumentException('Expected array of size 2 for BETWEEN condition');
                    }

                    $this->query .= sprintf(
                        ' %s %s %s %s AND %s',
                        $keyAttributes['type'],
                        $keyAttributes['field'],
                        $keyAttributes['operator'],
                        $placeholder . '0',
                        $placeholder . '1'
                    );

                    $this->preparedParams[$placeholder . '0'] = $value[0];
                    $this->preparedParams[$placeholder . '1'] = $value[1];

                    break;
                case 'IS':
                case 'IS NOT':
                    if (!in_array(mb_strtoupper(trim($value)), ['NULL', 'NOT NULL'])) {
                        throw new \InvalidArgumentException('Expected value of NULL of NOT NULL for IS condition');
                    }

                    $this->query .= sprintf(
                        ' %s %s %s %s',
                        $keyAttributes['type'],
                        $keyAttributes['field'],
                        $keyAttributes['operator'],
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
                        $keyAttributes['type'],
                        $keyAttributes['field'],
                        $placeholder
                    );

                    $this->preparedParams[$placeholder] = $value;

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
        $type = $field = $operator = null;

        $validTypes = ['AND', 'OR'];
        $validOperators = [
            '>=',
            '<=',
            '!=',
            '=',
            '>',
            '<',
            'NOT LIKE',
            'NOT IN',
            'NOT BETWEEN',
            'IS NOT',
            'LIKE',
            'IN',
            'BETWEEN',
            'IS',
        ];

        // grab the operator
        $type = $this->checkStart($key, $validTypes);
        $field = trim(str_replace($type, '', $key));
        $operator = $this->checkEnd($field, $validOperators);
        $field = trim(str_replace($operator, '', $field));
        $type = ($type === false) ? 'AND' : $type;
        $operator = ($operator === false) ? '=' : $operator;

        if ($type && $field && !in_array($operator, $validOperators)) {
            throw new \InvalidArgumentException();
        }

        if ($type && !$field) {
            $operator = $type;
        }

        return [
            'type'     => $type,
            'field'    => $this->escapeField($field),
            'operator' => $operator,
        ];
    }

    private function escapeField($field)
    {
        return $field;
        // Currently commented because of issues when a field is caled in a function (eg.
        $parsedField = explode('.', $field);

        foreach ($parsedField as &$item) {
            $item = sprintf('`%s`', $item);
        }

        return implode('.', $parsedField);
    }

    private function checkEnd($str, $end)
    {
        $str = ' ' . $str;
        if (is_array($end)) {
            foreach ($end as $el) {
                $el = ' ' . $el;
                if (mb_substr($str, -1 * mb_strlen($el), mb_strlen($el)) === $el) {
                    return trim($el);
                }
            }

            return false;
        } else {
            $end = ' ' . $end;
            if (mb_substr($str, -1 * mb_strlen($end), mb_strlen($end)) === $end) {
                return trim($end);
            }

            return false;
        }
    }

    private function checkStart($str, $start)
    {
        $str .= ' ';
        if (is_array($start)) {
            foreach ($start as $el) {
                $el .= ' ';
                if (mb_substr($str, 0, mb_strlen($el)) === $el) {
                    return trim($el);
                }
            }

            return false;
        } else {
            $start .= ' ';
            if (mb_substr($str, 0, mb_strlen($start)) === $start) {
                return trim($start);
            }

            return false;
        }
    }
}
