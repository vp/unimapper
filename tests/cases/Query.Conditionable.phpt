<?php

use Tester\Assert;
use UniMapper\Entity\Reflection;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class QueryConditionableTest extends \Tester\TestCase
{

    /** @var \Mockery\Mock $adapterMock */
    private $adapterMock;

    public function setUp()
    {
        $this->adapterMock = Mockery::mock("UniMapper\Tests\Fixtures\Adapter\Simple");
    }

    /**
     * Create conditionable query
     *
     * @return \UniMapper\Tests\Fixtures\Query\Conditionable
     */
    private function createConditionableQuery()
    {
        return new UniMapper\Query\Select(
            new Reflection("UniMapper\Tests\Fixtures\Entity\Simple")
        );
    }

    public function testNestedConditions()
    {
        $query = $this->createConditionableQuery();
        $expectedConditions = [];

        // where()
        $query->where("id", ">", 1);
        $expectedConditions[] = ["id", ">", 1, "AND"];
        Assert::same($expectedConditions, $query->conditions);

        // orWhere()
        $query->orWhere("text", "=", "foo");
        $expectedConditions[] = ["text", "=", "foo", "OR"];
        Assert::same($expectedConditions, $query->conditions);

        // whereAre()
        $query->whereAre(function($query) {
            $query->where("id", "<", 2)
                  ->orWhere("text", "LIKE", "anotherFoo");
        });
        $expectedConditions[] = [
            [
                ['id', '<', 2, 'AND'],
                ['text', 'LIKE', 'anotherFoo', 'OR']
            ],
            'AND'
        ];
        Assert::same($expectedConditions, $query->conditions);

        // orWhereAre()
        $query->orWhereAre(function($query) {
            $query->where("id", "<", 5);
            $query->orWhere("text", "LIKE", "yetAnotherFoo");
        });
        $expectedConditions[] = [
            [
                ['id', '<', 5, 'AND'],
                ['text', 'LIKE', 'yetAnotherFoo', 'OR'],
            ],
            'OR'
        ];
        Assert::same($expectedConditions, $query->conditions);

        // Deep nesting
        $query->whereAre(function($query) {
            $query->where("id", "=", 4);
            $query->orWhereAre(function($query) {
                $query->where("text", "LIKE", "yetAnotherFoo2");
                $query->whereAre(function($query) {
                    $query->orWhere("text", "LIKE", "yetAnotherFoo3");
                    $query->orWhere("text", "LIKE", "yetAnotherFoo4");
                });
                $query->orWhere("text", "LIKE", "yetAnotherFoo5");
            });
        });
        $expectedConditions[] = [
            [
                ['id', '=', 4, 'AND'],
                [
                    [
                        ['text', 'LIKE', 'yetAnotherFoo2', 'AND'],
                        [
                            [
                                ['text', 'LIKE', 'yetAnotherFoo3', 'OR'],
                                ['text', 'LIKE', 'yetAnotherFoo4', 'OR'],
                            ],
                            'AND'
                        ],
                        ['text', 'LIKE', 'yetAnotherFoo5', 'OR']
                    ],
                    'OR'
                ]
            ],
            'AND'
        ];
        Assert::same($expectedConditions, $query->conditions);
    }

    /**
     * @throws UniMapper\Exception\QueryException Value must be type array when using operator IN or NOT IN!
     */
    public function testInvalidIn()
    {
        $this->createConditionableQuery()->where("id", "IN", true);
    }

    /**
     * @throws UniMapper\Exception\QueryException Expected integer but boolean given on property id!
     */
    public function testInvalidValueType()
    {
        $this->createConditionableQuery()->where("id", "=", true);
    }

    /**
     * @throws UniMapper\Exception\QueryException Expected integer but string given on property id!
     */
    public function testInvalidValueTypeInArray()
    {
        $this->createConditionableQuery()->where("id", "IN", ["test", 1]);
    }

    public function testValueEmptyString()
    {
        Assert::same([["text", "=", "", "AND"]], $this->createConditionableQuery()->where("text", "=", "")->conditions);
    }

    public function testOperatorIs()
    {
        Assert::same([["text", "IS", "", "AND"]], $this->createConditionableQuery()->where("text", "IS", "")->conditions);
        Assert::same([["text", "IS", null, "AND"]], $this->createConditionableQuery()->where("text", "IS", null)->conditions);
    }

    public function testOperatorIsNot()
    {
        Assert::same([["text", "IS NOT", "", "AND"]], $this->createConditionableQuery()->where("text", "IS NOT", "")->conditions);
        Assert::same([["text", "IS NOT", null, "AND"]], $this->createConditionableQuery()->where("text", "IS NOT", null)->conditions);
    }

    /**
     * @throws UniMapper\Exception\QueryException Null value can be combined only with IS and IS NOT!
     */
    public function testValueNullAndNotAllowedOperator()
    {
        $this->createConditionableQuery()->where("id", "=", null);
    }

}

$testCase = new QueryConditionableTest;
$testCase->run();