---
layout: docs
title: Entity
permalink: /docs/api/entity/
---

Entity usually represents a unique object in your application model schema with which you are trying to faithfully capture the reality.

## Simple entity
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


### Primary property
It defines a unique value by which an entity can be identified. Usually some `id` column in your database for example.

### Inherited entity
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


## Hybrid entity

> If possible, please try to avoid this technique during the design phase!

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