<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class EntityTest extends TestCase
{

    /** @var Entity */
    private $entity;

    public function setUp()
    {
        $this->entity = new Entity;
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS = [
            'public' => false,
            "defined" => false,
            "computed" => true,
            "excludeNull" => true,
        ];
    }

    public function testConstruct()
    {
        $entity = new Entity(
            [
                "computed" => 1, // Skip comuted
                "readonly" => 1, // Set readonly
                "undefined" => "foo", // Skip undefined,
                "string" => 1, // Convert type automatically
            ]
        );
        Assert::null($entity->computed);
        Assert::same(1, $entity->readonly);
        Assert::same("1", $entity->string);
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Can not convert value on property 'integer' automatically!
     */
    public function testConstructNotAbleToConvertType()
    {
        new Entity(["integer" => new DateTime]);
    }

    public function testCreateCollection()
    {
        Assert::same("Entity", Entity::createCollection()->getEntityClass());
    }

    public function testGetProperty()
    {
        Assert::null($this->entity->string);
        Assert::null($this->entity->integer);
        Assert::null($this->entity->entity);
        Assert::null($this->entity->computed);
        Assert::null($this->entity->collection);

        $this->entity->collection = []; // create collection instance
        Assert::type("UniMapper\Entity\Collection", $this->entity->collection);
        Assert::count(0, $this->entity->collection);

        $this->entity->integer = 1;
        Assert::same(1, $this->entity->computed);
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Undefined property 'undefined'!
     */
    public function testGetUndefinedProperty()
    {
        $this->entity->undefined;
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Property 'readonly' is read-only!
     */
    public function testSetReadonly()
    {
        $this->entity->readonly = "foo";
    }

    public function testIsset()
    {
        $this->entity->string = "foo";
        Assert::true(isset($this->entity->string));
        Assert::false(isset($this->entity->integer));
    }

    public function testEmpty()
    {
        Assert::true(empty($this->entity->string));

        $this->entity->string = null;
        Assert::true(empty($this->entity->string));

        $this->entity->string = "foo";
        Assert::false(empty($this->entity->string));
    }

    public function testUnset()
    {
        $this->entity->integer = 1;
        unset($this->entity->integer);
        Assert::null($this->entity->integer);
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Property 'readonly' is read-only!
     */
    public function testUnsetReadonlyProperty()
    {
        unset($this->entity->readonly);
    }

    public function testSetProperty()
    {
        $this->entity->integer = 1;
        Assert::same(1, $this->entity->integer);

        $this->entity->collection = [new Entity];
        Assert::same(1, count($this->entity->collection));
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Expected integer but string given on property integer!
     */
    public function testSetPropertyInvalidType()
    {
        $this->entity->integer = "foo";
    }

    public function testToArrayDefined()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['defined'] = true;

        $this->entity->collection = [new Entity];
        $this->entity->entity = new Entity;

        $a = $this->entity->toArray();
        Assert::type("array", $this->entity->toArray());
        Assert::count(9, $this->entity->toArray());
        Assert::type("UniMapper\Entity\Collection", $this->entity->toArray()["collection"]);
        Assert::type("Entity", $this->entity->toArray()["entity"]);

        Assert::same(
            [
                'integer' => NULL,
                'string' => NULL,
                'dateTime' => NULL,
                'date' => NULL,
                'computed' => NULL,
                'enum' => NULL,
                'collection' => [
                    [
                        'integer' => NULL,
                        'string' => NULL,
                        'dateTime' => NULL,
                        'date' => NULL,
                        'computed' => NULL,
                        'enum' => NULL,
                        'collection' => NULL,
                        'entity' => NULL,
                        'readonly' => NULL,
                    ],
                ],
                'entity' => [
                    'integer' => NULL,
                    'string' => NULL,
                    'dateTime' => NULL,
                    'date' => NULL,
                    'computed' => NULL,
                    'enum' => NULL,
                    'collection' => NULL,
                    'entity' => NULL,
                    'readonly' => NULL,
                ],
                'readonly' => NULL,
            ],
            $this->entity->toArray(true)
        );
    }

    public function testToArrayJustSet()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['defined'] = false;

        $this->entity->collection = [new Entity(['string' => 'foo'])];
        $this->entity->entity = new Entity(['string' => 'bar']);
        $this->entity->integer = 1;

        Assert::equal(
            [
                'integer' => 1,
                 'collection' => [
                    [
                        'string' => 'foo',
                    ],
                ],
                'entity' => [
                    'string' => 'bar',
                ],
                'computed' => 1,
            ],
            $this->entity->toArray(true)
        );
    }

    public function testGetData()
    {
        $this->entity->integer = 1;
        $this->entity->string = "foo";
        Assert::same(
            array('integer' => 1, 'string' => 'foo'),
            $this->entity->getData()
        );
    }

    public function testJsonSerializable()
    {
        $this->entity->dateTime = new DateTime("1999-12-31 12:00:00");
        $this->entity->date = new DateTime("1999-12-31");
        Assert::same(
            '{"dateTime":{"date":"1999-12-31 12:00:00.000000","timezone_type":3,"timezone":"Europe\/Prague"},"date":{"date":"1999-12-31","timezone_type":3,"timezone":"Europe\/Prague"}}',
            json_encode($this->entity)
        );
    }

    public function testJsonSerializableAllDefined()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['defined'] = true;
        $this->entity->dateTime = new DateTime("1999-12-31 12:00:00");
        $this->entity->date = new DateTime("1999-12-31");
        Assert::same(
            '{"integer":null,"string":null,"dateTime":{"date":"1999-12-31 12:00:00.000000","timezone_type":3,"timezone":"Europe\/Prague"},"date":{"date":"1999-12-31","timezone_type":3,"timezone":"Europe\/Prague"},"computed":null,"enum":null,"collection":null,"entity":null,"readonly":null}',
            json_encode($this->entity)
        );
    }

    public function testQuery()
    {
        Assert::type("UniMapper\QueryBuilder", Entity::query());
    }

    public function testSerializable()
    {
        $this->entity->string = "foo";
        $serialized = 'C:6:"Entity":49:{a:2:{i:0;a:1:{s:6:"string";s:3:"foo";}i:1;a:0:{}}}';
        Assert::same($serialized, serialize($this->entity));

        $unserialized = unserialize($serialized);
        Assert::type("Entity", $unserialized);
        Assert::same(["string" => "foo"], $unserialized->getData());
    }

    public function testSerializableWithPublic()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['public'] = true;

        $this->entity->string = "foo";
        $this->entity->publicProperty = false;

        $serialized = 'C:6:"Entity":75:{a:2:{i:0;a:2:{s:6:"string";s:3:"foo";s:14:"publicProperty";b:0;}i:1;a:0:{}}}';
        Assert::same($serialized, serialize($this->entity));

        $unserialized = unserialize($serialized);
        Assert::type("Entity", $unserialized);
        Assert::same(["string" => "foo"], $unserialized->getData());
    }

    public function testImport()
    {
        $object = new stdClass;

        $this->entity->import(
            [
                "integer" => "2",
                "string" => 3.0,
                "collection" => [],
                "dateTime" => "1999-12-31 12:00:00",
                "entity" => $object,
                "collection" => [$object],
                "readonly" => 1,
                "undefined" => null,
                "computed" => 1,
                "publicProperty" => 'foo'
            ]
        );
        Assert::same(2, $this->entity->integer);
        Assert::same("3", $this->entity->string);
        Assert::type("UniMapper\Entity\Collection", $this->entity->collection);
        Assert::type("DateTime", $this->entity->dateTime);
        Assert::type("Entity", $this->entity->entity);
        Assert::same(1, count($this->entity->collection));
        Assert::same($this->entity->integer, $this->entity->computed);
        Assert::null($this->entity->readonly);
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Values must be traversable data!
     */
    public function testImportInvalidArgumentNotTraversable()
    {
        $this->entity->import(null);
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Can not convert value on property 'collection' automatically!
     */
    public function testImportInvalidArgumentCollection()
    {
        $this->entity->import(["collection" => "foo"]);
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Can not convert value on property 'dateTime' automatically!
     */
    public function testImportInvalidArgumentDateTime()
    {
        $this->entity->import(["dateTime" => []]);
    }

    public function testGetChanges()
    {
        $this->entity->integer = 1;

        $entity = new Entity;
        $entity->integer = 2;

        $this->entity->collection()->attach($entity);
        $this->entity->collection()->add($entity);
        $this->entity->collection()->detach($entity);

        $this->entity->entity()->integer = 3;
        $this->entity->entity()->attach();

        Assert::same(
            [
                "collection" => $this->entity->collection(),
                "entity" => $this->entity->entity()
            ],
            $this->entity->getChanges()
        );
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Computed property is read-only!
     */
    public function testSetComputedProperty()
    {
        $this->entity->computed = 1;
    }

    public function testIterateDefault()
    {
        $this->entity->integer = 1;
        $this->entity->string = "foo";

        $given = [];
        foreach ($this->entity as $name => $value) {
           $given[$name] = $value;
        }

        Assert::same(
            [
                'integer' => 1,
                'string' => 'foo',
                'computed' => 1,
            ],
            $given
        );
    }

    public function testIterateComputedNull()
    {
        $this->entity->string = "foo";

        $given = [];
        foreach ($this->entity as $name => $value) {
            $given[$name] = $value;
        }

        Assert::same(
            [
                'string' => 'foo',
            ],
            $given
        );
    }

    public function testIterateWithPublic()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['public'] = true;

        $this->entity->integer = 1;
        $this->entity->string = "foo";
        $this->entity->publicProperty = 'bar';

        $given = [];
        foreach ($this->entity as $name => $value) {
            $given[$name] = $value;
        }

        Assert::equal(
            [
                'integer' => 1,
                'string' => 'foo',
                'computed' => 1,
                'publicProperty' => 'bar'
            ],
            $given
        );
    }

    public function testIterateDefined()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['defined'] = true;

        $this->entity->integer = 1;
        $this->entity->string = "foo";

        $given = [];
        foreach ($this->entity as $name => $value) {
            $given[$name] = $value;
        }

        Assert::same(
            [
                'integer' => 1,
                'string' => 'foo',
                'dateTime' => null,
                'date' => null,
                'computed' => 1,
                'enum' => null,
                'collection' => null,
                'entity' => null,
                'readonly' => null
            ],
            $given
        );
    }

    public function testIterateDefinedAll()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['defined'] = true;
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['public'] = true;
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['excludeNull'] = false;

        $this->entity->integer = 1;
        $this->entity->string = "foo";

        $given = [];
        foreach ($this->entity as $name => $value) {
            $given[$name] = $value;
        }

        Assert::equal(
            [
                'integer' => 1,
                'string' => 'foo',
                'dateTime' => NULL,
                'date' => NULL,
                'computed' => 1,
                'enum' => NULL,
                'collection' => NULL,
                'entity' => NULL,
                'readonly' => NULL,
                'publicProperty' => NULL,
            ],
            $given
        );
    }

    public function testIterateWithSelection()
    {
        \UniMapper\Entity\Iterator::$ITERATE_OPTIONS['public'] = true;

        $this->entity->integer = 1;
        $this->entity->string = "foo";
        $this->entity->publicProperty = "bar";

        $selection = ['integer', 'string', 'publicProperty'];
        $this->entity->setSelection($selection);
        $given = [];
        foreach ($this->entity as $name => $value) {
            if (in_array($name, $selection) !== false) {
                $given[$name] = $value instanceof \UniMapper\Entity\Collection ? $value->toArray() : $value;
            }
        }

        Assert::same(
            [
                'integer' => 1,
                'string' => 'foo',
                'publicProperty' => 'bar',
            ],
            $given
        );
    }

    public function testCallOnCollection()
    {
        $collection = Entity::createCollection();

        Assert::same($collection, $this->entity->collection($collection));
        Assert::same($collection, $this->entity->collection());
        Assert::same($collection, $this->entity->collection(null));
        Assert::notSame($collection, $this->entity->collection(false));
    }

    public function testCallOnEntity()
    {
        $entity = new Entity;

        Assert::same($entity, $this->entity->entity($entity));
        Assert::same($entity, $this->entity->entity());
        Assert::same($entity, $this->entity->entity(null));
        Assert::notSame($entity, $this->entity->entity(false));
    }

    public function testAttach()
    {
        $this->entity->integer = 1;
        $this->entity->attach();
        Assert::same(Entity::CHANGE_ATTACH, $this->entity->getChangeType());
    }

    public function testAdd()
    {
        $this->entity->integer = 1;
        $this->entity->add();
        Assert::same(Entity::CHANGE_ADD, $this->entity->getChangeType());
    }

    public function testDetach()
    {
        $this->entity->integer = 1;
        $this->entity->detach();
        Assert::same(Entity::CHANGE_DETACH, $this->entity->getChangeType());
    }

    public function testRemove()
    {
        $this->entity->integer = 1;
        $this->entity->remove();
        Assert::same(Entity::CHANGE_REMOVE, $this->entity->getChangeType());
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Undefined property 'undefined'!
     */
    public function testCallUndefinedProperty()
    {
        $this->entity->undefined();
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException Only properties with type entity or collection can call changes!
     */
    public function testCallInvalidPropertyType()
    {
        $this->entity->string();
    }

    /**
     * @throws UniMapper\Exception\InvalidArgumentException You must pass instance of entity collection!
     */
    public function testCallInvalidArgumentType()
    {
        $this->entity->collection("");
    }

    public function testGetReflection()
    {
        Assert::same("Entity", $this->entity->getReflection()->getName());
    }

}

/**
 * @property int      $integer    m:primary
 * @property string   $string
 * @property DateTime $dateTime
 * @property Date     $date
 * @property int      $computed   m:computed
 * @property int      $enum       m:enum(self::ENUMERATION_*)
 * @property Entity[] $collection
 * @property Entity   $entity
 *
 * @property-read int $readonly
 */
class Entity extends \UniMapper\Entity
{
    public $publicProperty;

    const ENUMERATION_ONE = 1;
    const ENUMERATION_TWO = 2;

    protected function computeComputed()
    {
        return $this->integer;
    }

    public static function stringToArray($value)
    {
        return explode(',', $value);
    }

    public static function arrayToString($value)
    {
        return implode(',', $value);
    }
}

$testCase = new EntityTest;
$testCase->run();