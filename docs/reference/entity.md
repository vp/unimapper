---
layout: docs
title: Entity
permalink: /docs/reference/entity/
---

Entity usually represents a unique object in your application model schema with which you are trying to faithfully capture the reality.

The easiest entity can be a simple class with properties that represents a single table record in database for example.

```php
/**
 * @mapper Database(table_name)
 *
 * @property integer  $id        m:map(Database:) m:primary
 * @property string   $username  m:map(Database:)
 * @property DateTime $createdOn m:map(Database:)
 */
class User extends \UniMapper\Entity
{}
```

### Supported types

**Basic** `string`, `integer`, `boolean`, `double`, `array`

**[DateTime](http://www.php.net/manual/en/class.datetime.php)**

**Entity** Single entity, named according to your [naming conventions]({{ site.baseurl }}/docs/reference/naming-conventions/)

**Entity\[\]** Entity collection as `UniMapper\EntityCollection`

### Primary property
It defines a unique value by which an entity can be identified. Usually some `id` column in your database for example.

### Property validators
Built your own validation logic on every property. You only need to add `m:validate` filter with validation rule and define the corresponding function like `validationRuleName($value)`.

See the example below.

```php
/**
 * @property integer $score m:validate(score)
 */
class User extends \UniMapper\Entity
{
    public static function validateScore($value)
    {
         return $value >= 1 && $value <= 5;
    }
}

$user = new User;
$user->score = 1 // OK
$user->score = 6 // Throws exception
```

> Tip! You can even define multiple rules `m:validate(rule1|rule2...)`. Each rule must be met.

There are also some built-in rules: `url`, `email`, `ipv4`, `ipv6`.

### Computed property
A kind of virtual property, mostly dependent on other real properties so it is readonly and can not be set directly. It can be helpful in situations like price computing for example.

```php

/**
 * @property double $price
 */
class Product extends \UniMapper\Entity
{}

/**
 * @property Product[] $products
 * @property double    $price    m:computed
 */
class Order extends \UniMapper\Entity
{
    protected function computePrice()
    {
    	$price = 0;
    	foreach ($this->products as $product) {
    	    $price += $product->price;
    	}
        return $price;
    }
}

$order = new Order;
$order->products[] = Product::create(["price" => 5.0]);
echo $order->price; // Will be 5.0
$order->products[] = Product::create(["price" => 10.0]);
echo $order->price; // Will be 15.0
```

> Remember! Computed property can not be mixed with other filters.

### Entity inheritance
You can even extend entity with a new one. All properties will be inherited too. Just write a {@inheritdoc}.

```php
/**
 * {@inheritdoc}
 *
 * @property string $fullName  m:map(Database:)
 */
class UserDetail extends User
{}
```

### Active entity
@todo

### Hybrid entity

> This feature is experimental!

In some specials cases your entity could represent data stored across the different sources (database, REST api, whatever else ...).
For example, you have some Order entity that holds some data in local database and some data are stored in external application available through the REST api.

```php
/**
 * @mapper Database(table_name)
 * @mapper RestApi(resource)
 *
 * @property integer  $id         m:map(RestApi:apiRowWithId|Database:) m:primary
 * @property string   $note       m:map(RestApi:)
 * @property User     $assignedTo m:map(Database:)
 * @property DateTime $createdOn  m:map(Database:)
 */
class Order extends \UniMapper\Entity
{}
```

Very important is `m:primary` as [primary property](#primary-property), because it represents some kind of *foreign key* similar to relational database.