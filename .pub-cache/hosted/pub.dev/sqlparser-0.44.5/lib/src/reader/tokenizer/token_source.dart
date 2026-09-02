import 'package:meta/meta.dart';

import 'scanner.dart';
import 'token.dart';

@internal
final class TokenSource {
  /// A list of tokens that have been scanned.
  ///
  /// Note that this just collects tokens obtained through [readToken] and thus
  /// doesn't include potential tokens of source regions that haven't been
  /// parsed yet.
  final List<Token> tokens = [];
  final Scanner scanner;

  int _index = 0;
  Token? _previous;
  Token? lastConsumedToken;
  // Up to two tokens of lookahead in used by the parser.
  Token? _pendingLookahead0;
  Token? _pendingLookahead1;

  TokenSource(this.scanner);

  Token lookahead1({bool treatCommentAsDriftAnnotation = false}) {
    if (_pendingLookahead0 case final token?) {
      return token;
    } else {
      return _pendingLookahead0 = _readToken();
    }
  }

  /// Returns the next two tokens, but only if the next token is a keyword
  /// token.
  ///
  /// This special case is used to parse `NOT IN`, `NOT BETWEEN` and similar
  /// structures.
  (Token, Token?) keywordLookahead2() {
    final peek = lookahead1();
    if (peek is! KeywordToken) {
      return (peek, null);
    }

    final next = switch (_pendingLookahead1) {
      null => _pendingLookahead1 = _readToken(),
      final pending => pending,
    };
    return (peek, next);
  }

  Token consume() {
    if (_pendingLookahead0 case final token?) {
      _pendingLookahead0 = _pendingLookahead1;
      _pendingLookahead1 = null;
      return lastConsumedToken = token;
    } else {
      // We should have rolled this into pendingLookahead0 when consuming.
      assert(_pendingLookahead1 == null);
      return lastConsumedToken = _readToken();
    }
  }

  Token _readToken() {
    while (true) {
      final read = scanner.scanToken();
      if (read is TokenizerError) {
        scanner.errors.add(read);
        continue;
      }

      read
        ..index = _index++
        ..previous = _previous;
      _previous?.next = read;
      _previous = read;
      tokens.add(read);
      if (read.invisibleToParser) {
        continue;
      }

      return read;
    }
  }

  void readTokensUntilEnd() {
    Token token;
    do {
      token = _readToken();
    } while (token.type != TokenType.eof);
  }
}
