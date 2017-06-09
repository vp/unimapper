<?php

use Tester\Assert;
use UniMapper\Query;
use UniMapper\Entity\Reflection;
use UniMapper\Entity\Reflection\Property\Option\Assoc;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class QuerySelectableTest extends TestCase
{

    public function testAssociate()
    {
        \UniMapper\QueryBuilder::registerQuery(TestSelectable::class);

        $connectionMock = Mockery::mock("UniMapper\Connection");

        $query =  Foo::query()->testSelectable()->associate("adapterAssoc", "remoteAssoc");
        $result = $query->run($connectionMock);

        Assert::same(
            Foo::getReflection()->getProperty("adapterAssoc")->getOption(Assoc::KEY),
            $result["adapterAssociations"]["adapterAssoc"]
        );

        Assert::type(
            "UniMapper\Association\OneToOne",
            $result["remoteAssociations"]["remoteAssoc"]
        );

        Assert::equal(
            [
                0 => 'id',
                1 => 'foo',
                2 => 'disabledMap',
                'adapterAssoc' =>
                    [
                        0 => 'id',
                        1 => 'foo',
                        2 => 'disabledMap',
                    ],
                'remoteAssoc' =>
                    [
                        0 => 'id',
                    ],
            ],
            $result['selection']
        );
    }

    /**
     * @throws UniMapper\Exception\QueryException Property 'undefined' is not defined on entity Foo!
     */
    public function testAssociateUndefinedProperty()
    {
        $this->createQuery()->associate("undefined");
    }

    /**
     * @throws UniMapper\Exception\QueryException Property 'id' is not defined as association on entity Foo!
     */
    public function testAssociateNonAssociation()
    {
        $this->createQuery()->associate("id");
    }

    public function testSelect()
    {
        Assert::same(["id"], $this->createQuery()->select("id")->selection);
        Assert::same(["id", "foo"], $this->createQuery()->select(["id", "foo"])->selection);
        Assert::same(["id", "foo"], $this->createQuery()->select("id", "foo")->selection);
    }

    /**
     * @throws UniMapper\Exception\QueryException Property 'undefined' is not defined on entity Foo!
     */
    public function testSelectUndefinedProperty()
    {
        $this->createQuery()->select("undefined");
    }

    /**
     * @throws UniMapper\Exception\QueryException Associations, computed and properties with disabled mapping can not be selected!
     */
//    public function testSelectDisabledMapping()
//    {
//        $this->createQuery()->select("disabledMap");
//    }

    private function createQuery()
    {
        return Foo::query()->select();
    }

}

class TestSelectable extends Query {

    use Query\Selectable;

    protected function onExecute(\UniMapper\Connection $connection)
    {
        return [
            'remoteAssociations' => $this->createRemoteAssociations(),
            'selection' => $this->createQuerySelection(),
            'adapterAssociations' => $this->getAdapterAssociations(),
        ];
    }
}

/**
 * @adapter FooAdapter
 *
 * @property int    $id           m:primary
 * @property string $foo
 * @property Foo[]  $adapterAssoc m:assoc(type)
 * @property Bar[]  $remoteAssoc  m:assoc(1:1)
 * @property string $disabledMap  m:map(false)
 */
class Foo extends \UniMapper\Entity {}

/**
 * @adapter BarAdapter
 *
 * @property int $id m:primary
 */
class Bar extends \UniMapper\Entity {}

$testCase = new QuerySelectableTest;
$testCase->run();