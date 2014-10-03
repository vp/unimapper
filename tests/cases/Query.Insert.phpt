<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class QueryInsertTest extends UniMapper\Tests\TestCase
{

    /** @var \Mockery\Mock */
    private $adapterMock;

    public function setUp()
    {
        $this->adapterMock = Mockery::mock("UniMapper\Tests\Fixtures\Adapter\Simple");
        $this->adapterMock->shouldReceive("getMapping")->once()->andReturn(new UniMapper\Mapping);
    }

    public function testSuccess()
    {
        $this->adapterMock->shouldReceive("insert")
            ->once()
            ->with("simple_resource", ['text'=>'foo'])
            ->andReturn("1");

        $query = new \UniMapper\Query\Insert(
            new \UniMapper\Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"),
            ["FooAdapter" => $this->adapterMock],
            ["text" => "foo", "oneToOne" => ["id" => 3]]
        );
        Assert::same(1, $query->execute());
    }

    /**
     * @throws Exception Nothing to insert!
     */
    public function testNoValues()
    {
        $query = new \UniMapper\Query\Insert(
            new \UniMapper\Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple"),
            ["FooAdapter" => $this->adapterMock],
            []
        );
        $query->execute();
    }

}

$testCase = new QueryInsertTest;
$testCase->run();
