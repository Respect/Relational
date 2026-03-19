<?php

declare(strict_types=1);

namespace Respect\Relational\Hydrators;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use stdClass;

#[CoversClass(FlatNum::class)]
class FlatNumTest extends TestCase
{
    private PDO $pdo;

    private EntityFactory $factory;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE author (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE post (id INTEGER PRIMARY KEY, title TEXT, author_id INTEGER)');
        $this->pdo->exec("INSERT INTO author VALUES (1, 'Alice')");
        $this->pdo->exec("INSERT INTO post VALUES (10, 'Hello', 1)");
        $this->factory = new EntityFactory();
    }

    #[Test]
    public function hydrateSingleEntityFromNumericRow(): void
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM author');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_NUM);

        $hydrator = new FlatNum($stmt);
        $result = $hydrator->hydrate($row, Collection::author(), $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals(1, $this->factory->get($entity, 'id'));
        $this->assertEquals('Alice', $this->factory->get($entity, 'name'));
    }

    #[Test]
    public function hydrateMultipleEntitiesFromJoinedRow(): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT author.id, author.name, post.id, post.title, post.author_id'
            . ' FROM author INNER JOIN post ON post.author_id = author.id',
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_NUM);

        $hydrator = new FlatNum($stmt);
        $collection = Collection::author()->post;
        $result = $hydrator->hydrate($row, $collection, $this->factory);

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function hydrateReturnsFalseForEmptyResult(): void
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM author WHERE id = 999');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_NUM);

        $hydrator = new FlatNum($stmt);

        $this->assertFalse($hydrator->hydrate($row, Collection::author(), $this->factory));
    }

    #[Test]
    public function hydrateResolvesTypedEntity(): void
    {
        $this->pdo->exec('CREATE TABLE issue (id INTEGER PRIMARY KEY, title TEXT, type TEXT)');
        $this->pdo->exec("INSERT INTO issue VALUES (1, 'Bug Report', 'stdClass')");

        $stmt = $this->pdo->prepare('SELECT id, title, type FROM issue');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_NUM);

        $factory = new EntityFactory(entityNamespace: 'Respect\Relational\Hydrators\\');
        $hydrator = new FlatNum($stmt);
        $collection = Typed::by('type')->issue();
        $result = $hydrator->hydrate($row, $collection, $factory);

        $this->assertNotFalse($result);
        $result->rewind();
        $this->assertInstanceOf(stdClass::class, $result->current());
    }
}
