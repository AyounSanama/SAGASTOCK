/// Port of [`validateDatabaseSchema`](https://drift.simonbinder.eu/migrations/tests/#verifying-a-database-schema-at-runtime)
/// to a preview of drift version 3.
library;

// ignore: implementation_imports
import 'package:drift/src/drift3_preview/drift.dart' as drift3;
import 'package:drift_dev/src/services/schema/verifier_common.dart';
import 'migrations_common.dart' as common;

/// Utilities verifying that the current schema of the database matches what
/// the generated code expects.
extension VerifySelf on drift3.GeneratedDatabase {
  /// Compares and validates the schema of the current database with what the
  /// generated code expects.
  ///
  /// When changing tables or other elements of your database schema, you need
  /// to increate your [drift3.GeneratedDatabase.schemaVersion] and write a migration
  /// to transform your existing tables to the new structure.
  ///
  /// For queries, drift always assumes that your database schema matches the
  /// structure of your defined tables. This isn't the case when you forget to
  /// write a schema migration, which can cause all kinds of problems later.
  ///
  /// For this reason, the [validateDatabaseSchema] method can be used in your
  /// database, (perhaps in a [drift3.MigrationStrategy.beforeOpen] callback) to verify
  /// that your database schema is what drift expects.
  ///
  /// The [drift3.DriftConnection] is used to open a temporary SQLite database
  /// to create a reference schema. Typically, an in-memory SQLite connection is
  /// suitable for this purpose.
  ///
  /// The [common.ValidationOptions] can be used to make the schema validation
  /// more strict (e.g. by enabling [common.ValidationOptions.validateDropped]
  /// to ensure that no old tables continue to exist if they're not referenced
  /// in the new schema) or more lenient (e.g. by disabling
  /// [common.ValidationOptions.validateColumnConstraints]).
  ///
  /// This variant of [validateDatabaseSchema] is only supported on native
  /// platforms (Android, iOS, macOS, Linux and Windows).
  Future<void> validateDatabaseSchema({
    required drift3.DriftConnection connection,
    common.ValidationOptions options = const common.ValidationOptions(),
  }) async {
    await verifyDrift3Database(this, options, connection);
  }
}
