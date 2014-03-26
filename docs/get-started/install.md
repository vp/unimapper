---
layout: docs
title: Install
permalink: /docs/get-started/install/
---

## Requirements

<a href="http://php.net">PHP 5.4</a> and higher

## Install

The best way to download package to your project is [Composer](https://getcomposer.org) with all those nice things it provides.

```shell
composer require bauer01/unimapper@dev
```

## Directory structure

First of all, you need to realize your app structure. Maybe you heared about models, active record and stuff like that.
but it is better to separate logic entities, repository and queries.

```shell
/model
../entity
../mapper
../repository
```