Respect\Relational
==================

[![Build Status](https://secure.travis-ci.org/Respect/Relational.png)](http://travis-ci.org/Respect/Relational) [![Latest Stable Version](https://poser.pugx.org/respect/relational/v/stable.png)](https://packagist.org/packages/respect/relational) [![Total Downloads](https://poser.pugx.org/respect/relational/downloads.png)](https://packagist.org/packages/respect/relational) [![Latest Unstable Version](https://poser.pugx.org/respect/relational/v/unstable.png)](https://packagist.org/packages/respect/relational) [![License](https://poser.pugx.org/respect/relational/license.png)](https://packagist.org/packages/respect/relational)
 
the Relational database persistence tool.

  * Near-zero configuration.
  * Fluent interfaces like `$mapper->author[7]->fetch();`
  * Adapts itself to different database styles.
  * Records are handled as Plain Data Object.
  * No need to generate a thing, nada, nothing, zilch, bugger all!

Disclaimer
----------

This documentation is a work in progress! Kindly forward any issues you may find back to us =)

Installation
------------

Packages available on [PEAR](http://respect.li/pear) and [Composer](http://packagist.org/packages/Respect/Relational).
Autoloading with [composer](http://getcomposer.org/) is [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) compatible.

### Dependence

* Respect\Data

Respect\Data allows you to use multiple, cooperative database mapping with a single solid API. You can even mix out MySQL and 
MongoDB databases in a single model.

Feature Guide
-------------

### The Near-zero Part

You're ready to go playing with your database with 2 lines of code:

```php
<?php use Respect\Relational\Mapper;
      $mapper = new Mapper(new PDO('sqlite:database.sq3'));
```

We love using SQLite, but you can use any PDO adapter. Even PDO adapters
that do not exist yet.

Here is an example of what a mysql connection might look like, just because we are nice. =)

```php
<?php use Respect\Relational\Mapper;
      $mapper = new Mapper(new PDO('mysql:host=127.0.0.1;port=3306;dbname=database_mysql','root',''));
```

### A Sample Database

For the samples below, consider the following database structure:

```sql
   author (id INT AUTO_INCREMENT, name VARCHAR(32))
   post (id INT AUTO_INCREMENT, author_id INT, title VARCHAR(255), text TEXT, created_at TIMESTAMP)
   comment (id INT AUTO_INCREMENT, post_id INT text TEXT, created_at TIMESTAMP)
```

Consider these tables with referencing foreign keys, but that's really
only a best practice and its not required for Respect\Relational to work. We'll
explain how that works later, but that's only a detail.

### Fetching

We now have a database and a configured `$mapper`. To get a list of all authors,
you only need:

```php
<?php $authors = $mapper->author->fetchAll();
```

This will give you an array of PHP objects that represents the authors. You can
use them like this:

```php
<?php foreach ($authors as $author) {
          echo $author->name . PHP_EOL;
      }
```

All properties are available, so you can `$author->created_at` if you want. We'll
dive on automatic `JOIN` mapping, ordering and limiting below, keep reading!

### Persisting

#### Persist with stdClass

You can insert a new author into the database using the following example. We're using
`stdClass`, but you will see later on in this guide just how easy it is to use specific classes
for each mapping.

```php
<?php $alexandre = new stdClass;
      $alexandre->name = 'Alexandre Gaigalas';
      $alexandre->created_at = date('Y-m-d H:i:s');

      $mapper->author->persist($alexandre);
      $mapper->flush();
```

We use `flush()` to persist all changes to the database in one batch.
You can perform several `persist()`s before calling `flush()`. Persist will
keep the state in memory, flushing sends it all to the database.

After a `flush()` if you `print $alexandre->id`, it will reflect the auto incremented
value from the database.

#### Persist with ArrayObject

You can create a new author with `ArrayObject` too. Let's supose that you get a post 
request from a form with the field _name_ to create an author. You can do something like this:

```php
<?php $alexandre = new \ArrayObject($_POST, \ArrayObject::STD_PROP_LIST);
      $alexandre->created_at = date('Y-m-d H:i:s');

      $mapper->author->persist($alexandre);
      $mapper->flush();
```

This is just to show what you can do, ofcourse you have to [validate](https://github.com/Respect/Validation) the `$_POST` var first.

### Joining

In the sample below we're going to get all the comments, from all the posts created
by the author that has the id 42. In one line:

```php
<?php $manyComments = $mapper->comment->post->author[42]->fetchAll();
```

We like to read this as:

  "Mapper, give me all comments from posts made by author 42"

This will be easier to understand if you know what Respect\Relational is doing.

Results are automatically hydrated, so you can:

```php
<?php print $manyCmments[0]->post_id->author_id->name; //prints the post author name from the
                                                       //first comment
```

Many-to-many and left joins are also possible and will be covered below.

### Mapping Shortcuts

Before digging in on complex joins, conditions, ordering, entity classes, database
styles and other complicated (but simple with Respect\Relational) things, let's simplify
everything.

You can assign shortcuts to the mapper. For example:

```php
<?php $mapper->postsFromAuthor = $mapper->post->author;
```

Then use them:

```php
<?php $mapper->postsFromAuthor[7]->fetchAll();
```

With this you can centralize most of the persistence logic and avoid
duplicating code.

### Conventions

For now, you must be asking yourself how Respect\Relational can work with these guys
with just stdClasses and no configuration. This is done by conventions.

Any good database is based on conventions. Our defaults are:

  * A table must have a primary key named `id` as the first column.
  * A foreign key must be named as `table_id`.
  * A many-to-many table must be named as `table_othertable`.

Nodes on the fluent chain are these table names.

Conventions differ in style, we support many styles of casing (camel case, studly
caps, lowercase) and underscoring:

  * Default style (The above)
  * Sakila style (MySQL sample database)
  * Northwind style (SQL Server sample database)
  * CakePHP style (to make it easier to migrate from apps written in CakePHP)

Usage:

```php
<?php $mapper->setStyle(new Styles\Sakila);
```

Styles are implemented as plugins. Refer to the `Respect\Relational\Styles\Stylable`
interface.

```php
<?php $mapper->setStyle(new MyStyle);
```

### Entity Classes

Every example we looked at so far used the stdClasses. You can use your own model classes for each
table by setting an entity namespace:

```php
<?php $mapper->entityNamespace = '\\MyApplication\\Entities';
```

This will search for entities like `\\My\Application\\Entities\\Comment`. Public
properties for each column must be set. You don't need to extend or implement
anything and you can put any methods you want. No mandatory params on the
constructor please.

Currently entity classes are only supported through the use of `->setStyle()`.

### Updating

You've fetched something, changed it and you need to save it back. Easy:

```php
<?php $post122 = $mapper->post[122]->fetch();
      $post122->title = "New Title!";
      $mapper->post->persist($post122);
      $mapper->flush();
```

You may also change multiple items all at once:

```php
<?php //Mapper, give me comments from post 5
      $commentsFromPost5 = $mapper->comment->post[5];

      //Marking all comments as moderated
      foreach ($commentsFromPost5 as $c) {
          $c->text = "Moderated comment";
          $mapper->comment->persist($c);
      }

      $mapper->flush();
```

### Removing

...is also trivial:

```php
<?php $mapper->author->remove($alexandre);
      $mapper->flush();
```

### Conditions

First author named "Alexandre":

```php
<?php $mapper->author(array("name"=>"Alexandre"))->fetch();
```

Posts created after 2012 (note the `>=` sign):

```php
<?php $mapper->post(array("created_at >="=>strtotime('2012-01-01'))->fetchAll();
```

The same to LIKE:
```php
<?php $mapper->post(array("name LIKE "=>"Ale"))->fetchAll();
```

IS NULL or IS NOT NULL is very simple:
```php
<?php $mapper->post(array("name IS NOT NULL"))->fetchAll();
```

Comments on any post from the author named Alexandre:

```php
<?php //Mapper, give me comments from posts by author named Alexandre
      $mapper->comment->post->author(array("name"=>"Alexandre"))->fetchAll();
```

Comments from today on posts of the past week by the author 7:

```php
<?php $mapper->comment(array("created_at >"=>strtotime('today')))
             ->post(array("created_at >"=>strtotime('7 days ago')))
             ->author[7]
             ->fetchAll();
```

Just for the curiosity, the generated query from those complex conditions look exactly like this:

```sql
        SELECT
          *
        FROM
          comment
        INNER JOIN
          post
        ON
          comment.post_id = post.id
        INNER JOIN
          author
        ON
          post.author_id = author.id
        WHERE
          comment.created_at > 123456
        AND
          post.created_at > 234567
        AND
          author.id = 7;
```

### Ordering, Limiting

We accomplish ordering and limiting of results through the Sql helper so ensure that you've
sperified the following 'use':

```php
<?php use Respect\Relational\Sql;
```

10 last posts ordered by creation time

```php
<?php $mapper->post->fetchAll(Sql::orderBy('created_at')->desc()->limit(10));
```

Using multiple tables:

```php
<?php $mapper->comment->post[5]->fetchAll(Sql::orderBy('comment.created_at')->limit(20));
```

***You must use `Sql::` as a suffix. It must respect SQL order, so LIMIT must always
be used after ORDER BY. All extra Sql is placed at the end of the query.***


### Left Joins

Getting all posts left joining authors:

```php
<?php $mapper->post($mapper->author)->fetchAll();
```

If a post doesn't have an author, it will return a `null` when hydrated. You can
left join at any point in the chain.

Left joining with conditions are also possible:

```php
<?php $mapper->post($mapper->author, array("title" => "Spammed Title"))->fetchAll();
```

It doesn't matter which order they are in place either Conditions or the Joins first.
Queries by primary key are also easy with left joins:

```php
<?php //Post with id, optionally its author if present
      $mapper->post(7, $mapper->author)->fetch();
```

### Many-to-Many queries

For this sample, we're now assuming two more tables:

```sql
    category (id INT AUTO_INCREMENT, name VARCHAR(32))
    post_category (id INT AUTO_INCREMENT, post_id INT, category_id INT)
```

All categories from a post:

```php
<?php $mapper->category->post_category->post[7]->fetchAll();
```

Please, use shortcuts for these! Is this not easier to remember them by?

```php
<?php $mapper->categoriesFromPost = $mapper->category->post_category->post;
```

### Multi-join tables

A hint: a table may appear multiple times in the same chain. They're aliased suffixed
by a number in the SQL statement ie. (post, post2, post3, etc).

### Sql

The Sql class is a bonus. Its an advanced gramatical lightweight query builder.

```php
<?php print Sql::select('*')
                 ->from('post', 'comment', 'author')
                 ->where(array(
                    "post.id" => 7,
                    "comment.post_id" => "post.id",
                    "post.author_id" => "author.id"
                 ));
```

Do yourself a favour and consult the tests for the complete reference implementation for this class.

## Db Class

This class is also a bonus. It serves as a building block for the mapper
but can be used by itself:

```php
<?php $db = new Db(new Pdo('sqlite:mydb.sq3'));
```

Raw stdClass:

```php
<?php $db->select('*')->from('author')->fetchAll();
```

Custom class:

```php
<?php $db->select('*')->from('author')->fetchAll('MyAuthorClass');
```

Into existing object:

```php
<?php $db->select('*')->from('author')->limit(1)->fetch($alexandre);
```

Array:

```php
<?php $db->select('*')->from('author')->fetchAll(array());
```

Callback:

```php
<?php $db->select('*')->from('author')->fetchAll(function($obj) {
          return AuthorFactory::create((array) $obj);
      });
```
