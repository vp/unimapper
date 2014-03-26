---
layout: docs
title: Quickstart
permalink: /docs/get-started/quickstart/
---

## Entity

Entity should represent some real object in your application, maybe **User** for example.

```php
/**
 * @property string   $username
 * @property DateTime $createdOn
 */
class User extends \UniMapper\Entity
{}
```

There is some `username` property as *string* and `createdOn` property with user's registration time.