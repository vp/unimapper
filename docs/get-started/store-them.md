---
layout: docs
title: Store them
permalink: /docs/get-started/store-them/
prev_section: /get-started/describe-your-data
next_section: /get-started/work-with-it
---

The next thing you need is something that can user create, update, delete or retrieve from your data storage. It is generally assumed that the data will be stored in some database or behind some REST API. No problem, precisely for this purpose are [mappers]({{ site.baseurl }}/docs/reference/mapper/). To avoid having to create a new mapper from scratch, you can use some mapper from [extensions]({{ site.baseurl }}/docs/extensions/browse/).

In this case we prefer a database so choosing [Dibi]({{ site.baseurl }}/docs/extensions/browse/#dibi) is the best choice (excellent abstraction layer with different database types support).