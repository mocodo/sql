<?php


class MySQLConnectionTest extends PHPUnit_Framework_TestCase
{
    /** @var \Mocodo\Db\Driver\MySQLConnection */
    private $instance;

    private $query = 'SELECT foo, bar FROM my_table t WHERE 1';

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->instance = new \Mocodo\Db\Driver\MySQLConnection(
            'sqlite::memory:',
            null,
            null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    public function tearDown()
    {
        parent::tearDown(); // TODO: Change the autogenerated stub

        $this->instance = null;
    }

    public function testSimpleCondition()
    {
        $params = [
            'conditions' => [
                't.foo =' => 'bar',
            ],
        ];

        $this->assertEquals(
            'SELECT foo, bar FROM my_table t WHERE 1 AND `t`.`foo` = \'bar\' LIMIT 1',
            $this->instance->dumpQuery($this->query, $params, true)
        );
    }

    public function testSimpleConditionLight()
    {
        $params = [
            'conditions' => [
                'foo' => 'bar',
            ],
        ];

        $this->assertEquals(
            'SELECT foo, bar FROM my_table t WHERE 1 AND `foo` = \'bar\' LIMIT 1',
            $this->instance->dumpQuery($this->query, $params, true)
        );
    }

    public function testSimpleConditionNull()
    {
        $params = [
            'conditions' => [
                'foo IS' => 'NOT NULL',
            ],
        ];

        $this->assertEquals(
            'SELECT foo, bar FROM my_table t WHERE 1 AND `foo` IS NOT NULL LIMIT 1',
            $this->instance->dumpQuery($this->query, $params, true)
        );
    }

    public function testSimpleConditionOr()
    {
        $params = [
            'conditions' => [
                'foo IS'   => 'NOT NULL',
                'OR bar =' => 42,
            ],
        ];

        $this->assertEquals(
            'SELECT foo, bar FROM my_table t WHERE 1 AND `foo` IS NOT NULL OR `bar` = \'42\' LIMIT 1',
            $this->instance->dumpQuery($this->query, $params, true)
        );
    }

    public function testConditionBetween()
    {
        $params = [
            'conditions' => [
                'foo ='       => 'bar',
                'foz BETWEEN' => [1, 10],
            ],
        ];

        $this->assertEquals(
            'SELECT foo, bar FROM my_table t WHERE 1 AND `foo` = \'bar\' AND `foz` BETWEEN \'1\' AND \'10\' LIMIT 1',
            $this->instance->dumpQuery($this->query, $params, true)
        );
    }

    public function testConditionIn()
    {
        $params = [
            'conditions' => [
                'foo ='  => 'bar',
                'foz IN' => [1, 2, 3],
            ],
        ];

        $this->assertEquals(
            'SELECT foo, bar FROM my_table t WHERE 1 AND `foo` = \'bar\' AND `foz` IN (\'1\', \'2\', \'3\') LIMIT 1',
            $this->instance->dumpQuery($this->query, $params, true)
        );
    }

    public function testNestedConditionOr()
    {
        $params = [
            'conditions' => [
                't.foo IS' => 'NOT NULL',
                'OR'       => [
                    'foo IS' => 'NULL',
                    'bar >'  => 42,
                ],
            ],
        ];

        $this->assertEquals(
            'SELECT foo, bar FROM my_table t WHERE 1 AND `t`.`foo` IS NOT NULL OR (1 AND `foo` IS NULL AND `bar` > \'42\') LIMIT 1',
            $this->instance->dumpQuery($this->query, $params, true)
        );
    }
}
