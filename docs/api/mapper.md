---
layout: docs
title: Mapper
permalink: /docs/api/mapper/
---

Mapper is used to translate the ORM query syntax to some database query, REST API call or something else. Once the query is correctly translated and executed, the returned data are mapped to desired [entity]({{ site.baseurl }}/docs/api/entity/) or [collection]({{ site.baseurl }}/docs/api/collection/).

The idea is that each of your data source should have created such a mapper.