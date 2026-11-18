<?php

namespace Tests\Concerns;

use Attribute;

/**
 * M4: the method-level form of UsesOwnerConnection, for one test of a class that does DDL itself (a migration's down()
 * and up(), a probe table): only that test runs as the schema owner when the roles are split; the rest of the class
 * keeps running as the application role. Applied by tests/TestCase.php.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class AsSchemaOwner {}
