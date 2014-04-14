---
layout: docs
title: Describe your data
permalink: /docs/get-started/describe-your-data/
prev_section: /get-started/install
next_section: /get-started/store-them
---

[Entity]({{ site.baseurl }}/docs/reference/entity/) should represent some real object in your application, maybe **User** for example.

```php
/**
 * @property string   $username
 * @property DateTime $createdOn
 */
class User extends \UniMapper\Entity
{}
```

There is some `username` property as *string* and `createdOn` property with user's registration time.