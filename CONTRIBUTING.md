# Contributing to Respect\Relational

Contributions to Respect\Relational are always welcome. You make our lives easier by
sending us your contributions through [GitHub pull requests](http://help.github.com/pull-requests).

Pull requests for bug fixes must be based on the current stable branch whereas
pull requests for new features must be based on `master`.

Due to time constraints, we are not always able to respond as quickly as we
would like. Please do not take delays personal and feel free to remind us here,
on IRC, or on Gitter if you feel that we forgot to respond.

## Using Respect\Relational From a Git Checkout

The following commands can be used to perform the initial checkout of Respect\Relational:

```shell
git clone git://github.com/Respect/Relational.git
cd Relational
```

Retrieve Respect\Relational's dependencies using [Composer](http://getcomposer.org/):

```shell
composer install
```

## Running Tests

After run `composer install` on the library's root directory you must run PHPUnit.

### Linux

You can test the project using the commands:
```shell
$ vendor/bin/phpunit
```

### Windows

You can test the project using the commands:
```shell
> vendor\bin\phpunit
```

No test should fail.

You can tweak the PHPUnit's settings by copying `phpunit.xml.dist` to `phpunit.xml`
and changing it according to your needs.

### Running tests against MySQL and PostgreSQL

The default `vendor/bin/phpunit` run uses an in-memory SQLite database. To
exercise the full testsuite against MySQL and PostgreSQL as well, start the
bundled containers and use the driver-specific composer scripts:

```shell
docker compose up -d
composer phpunit:sqlite
composer phpunit:mysql
composer phpunit:pgsql
# or all three in sequence:
composer phpunit:all
```

The `docker-compose.yml` exposes MySQL on host port `33306` and PostgreSQL on
`55432` (non-default to avoid conflicts with locally installed databases).
The composer scripts hard-code the credentials defined in `docker-compose.yml`;
override `DB_DRIVER`, `DB_DSN`, `DB_USER`, and `DB_PASSWORD` to point at a
different setup — see `.env.example` for the supported variables.

CI runs the same three-driver matrix on every push and pull request via
GitHub Actions services (no Docker required in CI).

## Standards

We are trying to follow the [PHP-FIG](http://www.php-fig.org)'s standards, so
when you send us a pull request, be sure you are following them.

***

- [Home](README.md)
- [Db class](docs/DB-CLASS.md)
- [Feature Guide](docs/README.md)
- [Installation](docs/INSTALL.md)
- [License](LICENSE.md)
