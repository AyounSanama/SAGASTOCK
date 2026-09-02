part of '../ast.dart';

abstract class Renamable {
  AliasClause? get as;
}

/// An alias clause (e.g. `AS foo`) in SQL.
final class AliasClause extends AstNode {
  /// The `AS` token of this clause.
  Token? as;

  /// The name this entity has been renamed to.
  String name;

  IdentifierToken? nameToken;

  AliasClause(this.name);

  @override
  R accept<A, R>(AstVisitor<A, R> visitor, A arg) {
    return visitor.visitAliasClause(this, arg);
  }

  @override
  Iterable<AstNode> get childNodes => const Iterable.empty();

  @override
  void transformChildren<A>(Transformer<A> transformer, A arg) {}
}
