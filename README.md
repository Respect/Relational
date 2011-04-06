Respect\Relational
==================

A tool for any relational database that grows complex according to your needs.

This package is composed of the following components:

 * **Sql**, an abstraction of the SQL Language Syntax.
 * **Db**, a simple database toolkit.
 * **Mapper**, an ORM.

Mapper
------

The Mapper itself relies on different *Schema Providers*:

 * **Infered** infers entity information from naming conventions
 * **ReverseEngineered** extract entity information from the database itself
   (through clauses like `SHOW TABLES`)
 * **Reflected** extract entity information from PHP classes

These schemas have different levels of complexity, and you can migrate from a
simpler to a more complex schema seamlessly.

For example, you can start with the *Infered* Schema Provider that requires
zero-configuration and migrate to a *ReverseEngineered* Schema Provider
later, which has less limitations (but requires more configuration).

### The Infered Schema Provider (implemented)

This is the only provider that require database naming conventions:

 * Each table must have a single primary key named `id`
 * Each foreign key must be `table_id`
 * Each N-to-N table must be `table_othertable`

Samples:

  > post (id, title, text)
  > comment (id, post_id, name, text)
  > category (id, name)
  > post_category (id, post_id, category_id)

Usage sample:

    <?php
    use PDO;
    use Respect\Relational\Mapper;
    use Respect\Relational\Db;
    use Respect\Relational\Schemas\Infered;

    $mapper = new Mapper(new Db(new PDO('my_sqlite.sq3')), new Infered());

    $commentsFromPost12 = $mapper->comment->post[12]->fetchAll();
    $categoriesFromPost7 = $mapper->category->category_post->post[7]->fetchAll();

    $post3 = $mapper->post[3]->fetch();
    $post3->title = 'Hey!';

    $mapper->persist($post3);
    $mapper->flush();

More info soon...

### The ReverseEngineered Provider (not implemented yet)

This SP doesn't have the limitations of the above:

  * You can have any table and column names
  * You can have composite primary keys
  * You can have multiple foreign keys to the same table on the same origin

Configuration needed:

  * Cache implementation for the metadata retrieved
  * Abstraction of the metadata provider

More info soon...

### The Reflected Provider (not implemented yet)

This schema allows you to:

  * Use abstracted classes to specify single table inheritance
  * Use concrete classes to specify class table inheritance
  * Use method parameters to specify value objects

Configuration needed:

  * Classes for each one of the entities
  * Cache implementation for Reflection data

More info soon...

SQL (implemented)
---

You don't need to write SQL to use Respect\Relational, but if you *do want* to
write SQL, we provide a great tool for that. The SQL language is a first-class
citizen on our project, even if it runs just on the internal stuff.

The *Sql* class were created just to overcome the most annoying problems of
writing SQL:

 * Concatenate values
 * Optional conditions on `WHERE` clauses
 * Functions inside SQL clauses

Writing SQL on Respect\Relational is something like this:

    <?php
    use Respect\Relational\Sql;

    $conditions = array(
        'foo' => 'bar',
        'MD5(password)' => 'd1d1d1d1d1d1d1d1d1d'
    );
    $sql = Sql::select('my_column', 'my_column_2')
              ->from('my_table')
              ->where($conditions)
              ->orderBy('my_column DESC');
              ->limit(5,10);

Relational\Sql will create the query and parse the parameters (avaliable
on `$sql->getParams()`). If you pass an empty `$conditions` array, the `WHERE`
clause isn't even created.

Parameters, functions and everthing also works on `INSERT`, `UPDATE`, `DELETE`
or any other clause. In fact, our implementation allows you to use clauses that
weren't even known by our developers, because we've abstracted the language
syntax, not its grammar.

    //EXPLODE DINOSAUR ted USING tnt
    Sql::explodeDinosaur('ted')->using('tnt');

More info soon...

Db (implemented)
--

The Respect\Relational\Db tool is the most simple direct interaction with
a database you could think of. It allows you to do thinks like this:

    <?php
    use PDO;
    use Respect\Relational\Db;

    $db = new Db(new PDO('my_sqlite.sq3'));
    $conds = array('foo'=>'bar');
    $myObjects = $db->select('*')->from('my_table')->where($conds)->fetchAll();

The `$conds` array is automatically parsed, the SQL is prepared and the statement
is executed with the correct parameters.

More info soon...

License Information
===================

Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice,
  this list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of Alexandre Gomes Gaigalas nor the names of its
  contributors may be used to endorse or promote products derived from this
  software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.