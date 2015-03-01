# Db class

This class is also a bonus. It serves as a building block for the mapper
but can be used by itself:

```php
$db = new Db(new Pdo('sqlite:mydb.sq3'));
```

Raw stdClass:

```php
$db->select('*')->from('author')->fetchAll();
```

Custom class:

```php
$db->select('*')->from('author')->fetchAll('MyAuthorClass');
```

Into existing object:

```php
$db->select('*')->from('author')->limit(1)->fetch($alexandre);
```

Array:

```php
$db->select('*')->from('author')->fetchAll(array());
```

Callback:

```php
$db->select('*')->from('author')->fetchAll(function($obj) {
    return AuthorFactory::create((array) $obj);
});
```

***

See also:

- [Home](../README.md)
- [Contributing](../CONTRIBUTING.md)
- [Feature Guide](README.md)
- [Installation](INSTALL.md)
- [License](../LICENSE.md)
