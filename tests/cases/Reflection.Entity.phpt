<?php

use Tester\Assert;
use UniMapper\Reflection;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class ReflectionEntityTest extends \Tester\TestCase
{

    public function testCreateEntity()
    {
        $reflection = new Reflection\Entity(
            "UniMapper\Tests\Fixtures\Entity\Simple"
        );

        $entity = $reflection->createEntity(
            ["text" => "foo", "publicProperty" => "foo", "readonly" => "foo"]
        );
        Assert::type("UniMapper\Tests\Fixtures\Entity\Simple", $entity);
        Assert::same("foo", $entity->text);
        Assert::same("foo", $entity->publicProperty);
        Assert::same("foo", $entity->readonly);
    }

    public function testNoAdapterDefined()
    {
        $reflection = new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\NoAdapter");
        Assert::same("UniMapper\Tests\Fixtures\Entity\NoAdapter", $reflection->getClassName());
    }

    /**
     * @throws UniMapper\Exception\EntityException Property 'id' already defined as public property!
     */
    public function testDuplicatePublicProperty()
    {
        new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\DuplicatePublicProperty");
    }

    public function testNoPropertyDefined()
    {
        $reflection = new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\NoProperty");
        Assert::count(0, $reflection->getProperties());
    }

    public function testGetProperties()
    {
        $reflection = new Reflection\Entity("UniMapper\Tests\Fixtures\Entity\Simple");
        Assert::same(
            array(
                'id',
                'text',
                'empty',
                'url',
                'email',
                'time',
                'date',
                'year',
                'ip',
                'mark',
                'entity',
                'collection',
                'oneToMany',
                'oneToManyRemote',
                'manyToMany',
                'mmFilter',
                'manyToOne',
                'oneToOne',
                'ooFilter',
                'readonly',
                'storedData',
                'enumeration',
            ),
            array_keys($reflection->getProperties())
        );
    }

    public function testHasPrimary()
    {
        $noPrimary = new Reflection\Entity(
            "UniMapper\Tests\Fixtures\Entity\NoPrimary"
        );
        Assert::false($noPrimary->hasPrimary());
        $simple = new Reflection\Entity(
            "UniMapper\Tests\Fixtures\Entity\Simple"
        );
        Assert::true($simple->hasPrimary());
    }

    public function testGetPrimaryProperty()
    {
        $reflection = new Reflection\Entity(
            "UniMapper\Tests\Fixtures\Entity\Simple"
        );
        Assert::same("id", $reflection->getPrimaryProperty()->getName());
    }

    /**
     * @throws Exception Primary property not defined in UniMapper\Tests\Fixtures\Entity\NoPrimary!
     */
    public function testGetPrimaryPropertyNotDefined()
    {
        $reflection = new Reflection\Entity(
            "UniMapper\Tests\Fixtures\Entity\NoPrimary"
        );
        $reflection->getPrimaryProperty();
    }

}

$testCase = new ReflectionEntityTest;
$testCase->run();