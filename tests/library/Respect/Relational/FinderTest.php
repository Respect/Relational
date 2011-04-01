<?php

namespace Respect\Relational;

class FinderTest extends \PHPUnit_Framework_TestCase
{

    protected function formatSql($sql)
    {
        return preg_replace(
            '/(select|from|(inner|left) join|where|limit|update|set|insert|values)/i', "\n$1\n    ", str_replace("\n", "", $sql)
        );
    }

    public function testBasicStatement()
    {
        $finder = new Finder('like');
        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);

        $this->assertEquals(
            'SELECT like.* FROM like',
            (string) $query
        );
    }

    public function testBasicStatementWherePrimaryKey()
    {
        $finder = new Finder('like');
        $finder[12];
        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);
        $params = $query->getParams();

        $this->assertEquals(
            'SELECT like.* FROM like WHERE like.id=:LikeId',
            (string) $query
        );
        $this->assertEquals(12, $params['LikeId']);
    }

    public function testBasicStatementWhere()
    {
        $finder = new Finder('like');
        $finder->setCondition(array('author_name' => 'alganet', 'text' => 'foo', 'UPPER(like.mycolumn)="OI"'));
        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);
        $params = $query->getParams();

        $this->assertEquals(
            'SELECT like.* FROM like WHERE like.author_name=:LikeAuthorName AND like.text=:LikeText AND UPPER(like.mycolumn)="OI"',
            (string) $query
        );
        $this->assertEquals('alganet', $params['LikeAuthorName']);
        $this->assertEquals('foo', $params['LikeText']);
    }

    public function testJoinSimple()
    {
        $finder = Finder::like()->comment->user;

        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);

        $this->assertEquals(
            'SELECT like.*, comment.*, user.* FROM like INNER JOIN comment ON like.comment_id = comment.id INNER JOIN user ON comment.user_id = user.id',
            (string) $query
        );
    }

    public function testJoinSimpleWhere()
    {
        $finder = Finder::like()->comment[1]->user[2];

        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);
        $params = $query->getParams();

        $this->assertEquals(
            'SELECT like.*, comment.*, user.* FROM like INNER JOIN comment ON like.comment_id = comment.id INNER JOIN user ON comment.user_id = user.id WHERE comment.id=:CommentId AND user.id=:UserId',
            (string) $query
        );

        $this->assertEquals(1, $params['CommentId']);
        $this->assertEquals(2, $params['UserId']);
    }

    public function testJoinChildren()
    {
        $finder = Finder::comment(Finder::post()->author)->author->company;

        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);

        $this->assertEquals(
            'SELECT comment.*, post.*, author.*, author2.*, company.* FROM comment LEFT JOIN post ON comment.post_id = post.id INNER JOIN author ON post.author_id = author.id INNER JOIN author AS author2 ON comment.author_id = author2.id INNER JOIN company ON author2.company_id = company.id',
            (string) $query
        );
    }

    public function testJoinChildrenWhere()
    {
        $finder = Finder::comment(Finder::post()->author[122])->author[122]->company(array('name' => 'Google'));

        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);

        $this->assertEquals(
            'SELECT comment.*, post.*, author.*, author2.*, company.* FROM comment LEFT JOIN post ON comment.post_id = post.id INNER JOIN author ON post.author_id = author.id INNER JOIN author AS author2 ON comment.author_id = author2.id INNER JOIN company ON author2.company_id = company.id WHERE author.id=:AuthorId AND author2.id=:Author2Id AND company.name=:CompanyName',
            (string) $query
        );
    }

    public function testJoinNtoN()
    {
        $finder = Finder::like()->like_user->user;

        $schema = new Schemas\Infered();
        $query = $schema->generateQuery($finder);

        $this->assertEquals(
            'SELECT like.*, like_user.*, user.* FROM like INNER JOIN like_user ON like_user.like_id = like.id INNER JOIN user ON like_user.user_id = user.id',
            (string) $query
        );
    }

    public function testFetchHydrated()
    {
        $finder = Finder::comment()->post();
        $schema = new Schemas\Infered();
        $conn = new \PDO('sqlite::memory:');
        $statement = $conn->query("SELECT 1 AS id, 5 AS post_id, 'comm doido' AS text, 5 AS id, 'post loko' AS title, 'opaaa' AS text");
        $statement->setFetchMode(\PDO::FETCH_NUM);
        $entities = $schema->fetchHydrated($finder, $statement);
        $this->assertArrayHasKey('comment', $entities);
        $this->assertArrayHasKey('post', $entities);
        $this->assertArrayHasKey(1, $entities['comment']);
        $this->assertArrayHasKey(5, $entities['post']);
        $this->assertEquals(5, $entities['post'][5]->id);
        $this->assertEquals(1, $entities['comment'][1]->id);
        $this->assertSame($entities['post'][5], $entities['comment'][1]->post_id);
        $this->assertEquals('comm doido', $entities['comment'][1]->text);
        $this->assertEquals('opaaa', $entities['post'][5]->text);
        $this->assertEquals('post loko', $entities['post'][5]->title);
        $this->assertEquals(3, count(get_object_vars($entities['post'][5])));
        $this->assertEquals(3, count(get_object_vars($entities['comment'][1])));
    }

    public function testFetchHydratedSingle()
    {
        $finder = Finder::comment();
        $schema = new Schemas\Infered();
        $conn = new \PDO('sqlite::memory:');
        $statement = $conn->query("SELECT 1 AS id, 5 AS post_id, 'comm doido' AS text");
        $statement->setFetchMode(\PDO::FETCH_NUM);
        $entities = $schema->fetchHydrated($finder, $statement);
        $this->assertArrayHasKey('comment', $entities);
        $this->assertArrayHasKey(1, $entities['comment']);
        $this->assertEquals(1, $entities['comment'][1]->id);
        $this->assertEquals('comm doido', $entities['comment'][1]->text);
        $this->assertEquals(3, count(get_object_vars($entities['comment'][1])));
    }

}
