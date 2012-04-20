Respect\Relational [![Build Status](https://secure.travis-ci.org/Respect/Relational.png)](http://travis-ci.org/Respect/Relational)
==================

Relational database persistence tool.

  * Near-zero configuration.
  * Fluent interfaces like `$mapper->author[7]->fetch();`
  * Adapts itself to different database styles.

Disclaimer
----------

This documentation is experimental! Left any issues you find to us =)

For now, installation is done by cloning this repo, Respect\Data and 
setting up a PSR-0 autoloader. PEAR and Composer packages are on their way.

Feature Guide
-------------

### The Near-zero Part

You're ready to go playing with your database with 2 lines of code:

    <?php

    use Respect\Relational\Mapper;

    $mapper = new Mapper(new PDO('sqlite:database.sq3'));

We love using SQLite, but you can use any PDO adapter. Even PDO adapters
that do not exist yet.

### A Sample Database

For the samples below, consider the following database structure:

  * author (id INT AUTO_INCREMENT, name VARCHAR(32))
  * post (id INT AUTO_INCREMENT, author_id INT, title VARCHAR(255), text TEXT, created_at TIMESTAMP)
  * comment (id INT AUTO_INCREMENT, post_id INT text TEXT, created_at TIMESTAMP)

Consider these tables with referencing foreign keys, but that's really
only a best practice and its not required for Relational to work. We'll
explain how that works later, but that's only a detail.

### Fetching

We now have a database and a configured `$mapper`. To get a list of all authors, 
you only need:

    $authors = $mapper->author->fetchAll();

This will give you an array of PHP objects that represents the authors. You can
use them like this:

    foreach ($authors as $author) {
        echo $author->name . PHP_EOL;
    }

All properties are available, so you can `$author->created_at` if you want. We'll
dive on automatic `JOIN` mapping, ordering and limiting below, keep reading!

### Persisting

You can insert a new author on the database using the following sample. We're using
`stdClass`, but we'll see later on this guide how to easily use specific classes
for each mapping.

    $alexandre = new stdClass;
    $alexandre->name = 'Alexandre Gaigalas';
    $alexandre->created_at = date('Y-m-d H:i:s');

    $mapper->author->persist($alexandre);
    $mapper->flush();

You can perform several `persist()`s before calling `flush()`. Persisting does
its things in memory, flushing send them to the database.

After the `flush()` if you `print $alexandre->id`, it will have the auto incremented
value from the database.

### Joining

In the sample below we're going to get all the comments, from all the posts created
by the author that has the id 42. In one line:

    $manyComments = $mapper->comment->post->author[42]->fetchAll();

We like to read this as:

  "Mapper, give me comments from posts from the author 42"

This makes easier to know what Relational is doing.

Results are automatically hidrated, so you can:

    print $manyCmments[0]->post_id->author_id->name; //prints the post author name from the 
                                                     //first comment

Many-to-many and left joins are also possible and will be covered below.

### Mapping Shortcuts

Before digging in on complex joins, conditions, ordering, entity classes, database
styles and other complicated (but simple on Relational) things, let's simplify 
everything.

You can assing shortcuts to the mapper. For example:

    $mapper->postsFromAuthor = $mapper->post->author;

Then use them:

    $mapper->postsFromAuthor[7]->fetchAll();

With this you can centralize much of the persistence logic and avoid
duplicating code.

### Conventions

For now, you must be asking yourself how Relational can work with these guys
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
  * CakePHP style (to make easier to migrate from apps written in it)

Usage:

    $mapper->setStyle(new Styles\Sakila);

Styles are implemented as plugins. Refer to the `Respect\Relational\Styles\Stylable`
interface.

    $mapper->setStyle(new MyStyle);

### Entity Classes

Every sample we saw until now used stdClasses. You can use your own classes for each
table by setting an entity namespace:

    $mapper->entityNamespace = '\\MyApplication\\Entities';

This will search for entities like `\\My\Application\\Entities\\Comment`. Public
properties for each column must be set. You don't need to extend or implement
anything and you can put any methods you want. No mandatory params on the
constructor.

For now, entity classes only work when you `->setStyle()`.

### Updating

You've fetched something, changed it and you need to save it back. Easy:

    $post122 = $mapper->post[122]->fetch();
    $post122->title = "New Title!";
    $maper->post->persist($post122);
    $mpper->flush();

Changing many things at once and saving them all is also possible:

    //Mapper, give me comments from the post 5
    $commentsFromPost5 = $mapper->comment->post[5];

    //Marking all comments as moderated
    foreach ($commentsFromPost5 as $c) {
        $c->text = "Moderated comment";
        $mapper->comment->persist($c);
    }

    $mapper->flush();

### Removing 

...is also trivial:

    $mapper->author->remove($alexandre);
    $mapper->flush();

### Conditions

First author named "Alexandre":

    $mapper->author(array("name"=>"Alexandre"))->fetch();

Posts created after 2012 (note the `>` sign):

    $mapper->post(array("created_at >"=>strtotime('2012-01-01'))->fetchAll();

Comments on any post from the author named Alexandre:

    //Mapper, give me comments from posts from authors with name Alexandre
    $mapper->comment->post->author(array("name"=>"Alexandre"))->fetchAll();

Comments from today on posts from the past week from the author 7:

    $mapper->comment(array("created_at >"=>strtotime('today')))
           ->post(array("created_at >"=>strtotime('7 days ago')))
           ->author[7]
           ->fetchAll();

Just as a curiosity, the generated query is exactly like this:

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
      author.id = 7    

### Ordering, Limiting

First of all, put this on your `use` clauses:

    use Respect\Relational\Sql;

10 last posts ordered by creation time

    $mapper->post->fetchAll(Sql::orderBy('created_at')->desc()->limit(10));

Using multiple tables:

    $mapper->comment->post[5]->fetchAll(Sql::orderBy('comment.created_at')->limit(20));

You must use `Sql::` as a suffix. It must respect SQL order, so LIMIT must always
be used after ORDER BY. All extra Sql is placed at the end of the query.


### Left Joins

Getting all posts left joining authors:

    $mapper->post($mapper->author)->fetchAll();

If a post doesn't have an author, it will come as `null` when hidrated. You can
left join in any point of the chain.

Left joining with conditions is also possible:

    $mapper->post($mapper->author, array("title" => "Spammed Title"))->fetchAll();

It doesn't matter the order. Condition can be first or the join. Queries by the
primary key are also easy with left joins:

    //Post with id, optionally its author if present
    $mapper->post(7, $mapper->author)->fetch();

### Many-to-Many queries

For this sample, we're now assuming two more tables:

    category (id INT AUTO_INCREMENT, name VARCHAR(32))
    post_category (id INT AUTO_INCREMENT, post_id INT, category_id INT)

All categories from a post:

    $mapper->category->post_category->post[7]->fetchAll();

Please, use shortcuts on this! Remember them?

    $mapper->categoriesFromPost = $mapper->category->post_category->post;

A hint: a table may appear more than one time in the same
chain. They're aliased suffixed by number on the SQL (post, post2, post3, etc).

### Sql

The Sql class is a bonus. Its an advanced gramatical lightweight query builder.

    print Sql::select('*')
             ->from('post', 'comment', 'author')
             ->where(array(
                "post.id" => 7,
                "comment.post_id" => "post.id",
                "post.author_id" => "author.id"
              ));

Please see the tests for this class for a complete reference.

## Db Class

This class is also a bonus. It serves as a building block for the mapper
but can be used by itself:

    $db = new Db(new Pdo('sqlite:mydb.sq3'));

Raw stdClass:

    $db->select('*')->from('author')->fetchAll();

Custom class:

    $db->select('*')->from('author')->fetchAll('MyAuthorClass');

Into existing object:

    $db->select('*')->from('author')->limit(1)->fetch($alexandre);

Array:

    $db->select('*')->from('author')->fetchAll(array());

Callback:

    $db->select('*')->from('author')->fetchAll(function($obj) {
        return AuthorFactory::create((array) $obj);
    });
