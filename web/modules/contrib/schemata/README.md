# Schemata

> Facilitate generation of schema definitions of Drupal 8 data models.

A schema is a declarative definition of an entity's makeup. A way of describing
the different pieces that make up the entity, much like an interface defines a
class and exactly like an XML DTD describes an XML document. This project uses
Drupal's new Typed Data system to faciliate the creation of schemas for your
site.

This is especially powerful in conjunction with the Drupal router, as your
content model schemata can help with testing, client code generation,
documentation generation, and more, especially in conjunction with external
tools that process schemas.

## What has a Schema?

All Entity Types and Bundles have a schema automatically via the Schemata module.

## Where is the Schema?

Schemata are accessed via regular routes. Once enabled, Schemata resources are
found at `/schemata/{entity_type}/{bundle?}`. These resources are dynamically
generated based on the Typed Data Definition system in Drupal core, which means
any change to fields on an Entity will automatically be reflected in the schema.

## Requirements

Schema will only apply if [Issue #2751325: Specifically-typed properties in json output](https://www.drupal.org/node/2751325)
is fixed or patched in your Drupal instance. Otherwise your serialized API
output for JSON, HAL and JSON API will only produce string values.

Schemata-the-module is a dependency for the sub-modules of the project:

* **JSON Schema**: A serializer which processes Schemata schema objects into
  [JSON Schema v4](http://json-schema.org). Describes the output of content 
  entities via the core JSON, HAL and JSON API serializers.

From a "product" standpoint, JSON Schema is the valuable thing, and Schemata
the technical dependency. Only install Schemata if you plan to install a
serializer that can do something with it

## Architecture

The Schemata project contains the Schemata module. The module provides routes to
retrieve a schema object. The Schema is assembled by Drupal based
on the [Typed Data API](https://www.drupal.org/node/1794140), configuration,
and in the future, router introspection. The resulting Schema object can then
be requested via a GET request using a `_format` parameter to select a
particular serializer.

In order to serialize the Schema Object, the serializer must be able to support
implementations of the Drupal\schemata\Schema\SchemaInterface class. At this
time, the only serializer support for Schemata is within this project, you can
see an example of this in the packaged submodule **JSON Schema**.

## Usage

You can obtain the schema either making a request to an exposed route or by
using the programmatic API.

Each output format should be contained in its own submodule. Enable the
submodule for the format that you need first. For instance:

```
drush en -y schemata_json_schema
```

Finally you need to grant permission to access the data models to roles that
need it.

### Request
Create a request against `/schemata/{entity_type}/{bundle}?_format={output_format}&_describes={described_format}`
For instance:

  * `/schemata/node/article?_format=schema_json&_describes=api_json`
  * `/schemata/user?_format=schema_json&_describes=api_json` (omit the bundle
  if the entity type has no bundles).

### Programmatically
```php
// Input variables.
$entity_type_id = 'node';
$bundle = 'article';
$output_format = 'schema_json';
$described_format = 'api_json';

$schema_factory = \Drupal::service('schemata.schema_factory');
$serializer = \Drupal::service('serializer');
$schema = $schema_factory->create($entity_type_id, $bundle);
$format = $output_format . ':' . $described_format;

// Output.
$serializer->serialize($schema, $format);
```

## Security

* Bug reports should follow [Drupal.org security reporting procedures](https://www.drupal.org/node/101494).

## URLs

* [Homepage](https://www.drupal.org/project/schemata)
* [Development](https://github.com/phase2/schemata)

## Maintainers

Adam Ross a.k.a. Grayside

## Contributors

Creation of this module was sponsored by [Norwegian Cruise Line](https://www.drupal.org/norwegian-cruise-line).

Fubhy's work on [GraphQL](https://www.drupal.org/project/graphql) was a great
help in early architecture of this project. Thank you to [Fubhy](https://www.drupal.org/u/fubhy)
and the GraphQL sponsors.

## F.A.Q.

### Will there be a Drupal 7 backport?

This module can be summarized as follows:

A Drupal 8 subsystem (routing) and a Symfony subsystem (Serialization) use a touchy
Drupal 8 subsystem (Typed Data) to describe the output of another Drupal 8
subsystem (Entity).

It gets worse--the use cases of this module are most applicable when said entity
output comes by way of the routing and Serialization systems.

It does not make sense to discuss a backport unless you first backport large
swathes of Drupal 8.

### Why not use some other module?

[Self Documenting REST API](https://www.drupal.org/project/rest_api_doc) produces
webpage reports about REST resources on the site. This project might eventually
compete with that if it builds out support for the [Swagger specification](http://swagger.io/).

[Field Report](https://www.drupal.org/project/field_report) enhances the core
reports about fields and content types. This has some similarity with this
project, but the reports provided by Field Report are for people orienting on
a site. This project produces schemas that can be used for machine integrations
or feeding into other report generation systems.