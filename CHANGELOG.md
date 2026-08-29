# CHANGELOG

## v0.6.0

Breaking change:
* Simplify migration runner
  * Migration file must now return instance of migration class, initialised with closures.
    Variant format (arrays, strings) not allowed, to simplify surface area

Also, updated and tightened project's linting setup.

## v0.5.0

Add migration runner.

## v0.4.0

`Query::fetchModels` method for easily transforming a SELECT query into an array of typed objects.

## v0.3.0

Breaking change:
* On the Query class, `select`, `update`, `insertInto`, `deleteFrom`, and `with` are now static
  methods, which return a new instance, rather than instance methods.
* New instance methods `appendSelect`, `appendUpdate`, etc., are provided to replace the old
  instance method versions of the above functions.

## v0.2.0

Breaking change:
* Remove pointless Query::clear() method.

## v0.1.1

* Fix composer package description.

## v0.1.0

* Initial package release.
