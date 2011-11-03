Respect\Relational
==================

Fluent Database Toolkit based on conventions. 

Quintessential sample
---------------------

Tables used:

  * author (id, name)
  * post (id, author_id, title, text)
  * comment (id, post_id, author_id, text)

PHP:

    <?php
    use PDO;
    use Respect\Relational\Mapper;

    $mapper = new Mapper(new PDO( /* my db conf */ ));

    $postTwelve = $mapper->post[12]->fetch();
    $commentsOnPostTwelve = $mapper->comment->post[12]->fetchAll();
    $commentsOnPostsFromAuthorSeven = $mapper->comment->post->author[7]->fetchAll();

Thats it. It doesn't need configuration, entity classes or anything else. *Plug
and rock on*.

Nice, but how it works?
-----------------------

If you're wondering how dirty are the SQL statements generated, well, they're 
pretty readable and smart. The last sample above generate something like this:

    SELECT
        *
    FROM 
        comment
    INNER JOIN
        post
        ON comment.post_id = post.id
    INNER JOIN
        author
        ON comment.author_id = author.id
    WHERE
        author.id = 7;

Seems written by a human, doesn't? The mapper is able to treat duplicated columns, 
human readable joins and everything else without any configuration or extra SQL
statements. It **does not** use `SHOW TABLES` or anything like it by default, 
but you can use it if you want.

More samples
------------

Edit a post:

    $post = $mapper->post[12]->fetch();
    $post->title = "New Post title";
    $mapper->persist($post, "post");
    $mapper->flush();

New comment:

    $comment = new stdClass;
    $comment->post_id = 3;   //you can use a post object if you want
    $comment->author_id = 7; //same here
    $comment->text = "hi there";
    $mapper->persist($comment, 'comment');
    $mapper->flush();
    
Edit a bunch of comments:

    $comments = $mapper->comment->author[15]->fetchAll();

    foreach ($comments as $c) {
        $c->text = "This user has been banned";
        $mapper->persist($c);
    }

    $mapper->flush();

Remove a previously fetched comment:

    $comment = $mapper->comment[1651]->fetch();
    $mapper->remove($comment);
    $mapper->flush();

Last 10 comments from author (full code):

    <?php
    use PDO;
    use Respect\Relational\Mapper;
    use Respect\Relational\Sql;

    $mapper = new Mapper(new PDO( /* my db conf */ ));

    $lastComments = $mapper->comment
                           ->author[15]
                           ->fetchAll(Sql::orderBy('id desc')->limit(10));


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