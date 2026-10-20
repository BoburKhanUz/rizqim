// Copies packages/api-spec/openapi.json to .dart_tool/openapi.prepared.json for swagger_parser.
//
// swagger_parser cannot generate valid json_serializable code for an `anyOf`/`oneOf` of several real types (it
// writes "sealed" classes it never creates). Such a union becomes a free-form value here:
//   * a whole response body (GET /v1/orders answers with a cursor page or a plain list) -> `dynamic` (decoded JSON);
//   * a property or item -> `dynamic`.
// A nullable type (`anyOf: [X, {type: null}]`) is not a union and is kept as is; so is everything else: all other
// models and endpoints are generated unchanged. The apps read the few free-form fields they need.
import 'dart:convert';
import 'dart:io';

int _replaced = 0;

bool _isNull(Object? schema) =>
    schema is Map<String, Object?> && schema['type'] == 'null';

/// True when [node] is an `anyOf`/`oneOf` with more than one non-null branch.
bool _isUnion(Map<String, Object?> node) {
  for (final String key in <String>['anyOf', 'oneOf']) {
    final Object? branches = node[key];
    if (branches is List<Object?> &&
        branches.where((Object? b) => !_isNull(b)).length > 1) {
      return true;
    }
  }
  return false;
}

Object? _walk(Object? node, {bool responseRoot = false}) {
  if (node is List<Object?>) {
    return node.map<Object?>((Object? e) => _walk(e)).toList();
  }
  if (node is! Map<String, Object?>) return node;
  if (_isUnion(node)) {
    _replaced++;
    return responseRoot
        ? <String, Object?>{'type': 'object', 'additionalProperties': true}
        : <String, Object?>{};
  }
  return <String, Object?>{
    for (final MapEntry<String, Object?> e in node.entries)
      e.key: _walk(e.value),
  };
}

void main(List<String> args) {
  final String source = args.isNotEmpty
      ? args[0]
      : '../../api-spec/openapi.json';
  final String target = args.length > 1
      ? args[1]
      : '.dart_tool/openapi.prepared.json';
  final Map<String, Object?> spec =
      jsonDecode(File(source).readAsStringSync()) as Map<String, Object?>;

  // Response bodies first (their root differs), then everything else
  final Map<String, Object?> paths =
      (spec['paths'] as Map<String, Object?>?) ?? <String, Object?>{};
  for (final Object? item in paths.values) {
    if (item is! Map<String, Object?>) continue;
    for (final Object? operation in item.values) {
      if (operation is! Map<String, Object?>) continue;
      final Object? responses = operation['responses'];
      if (responses is! Map<String, Object?>) continue;
      for (final Object? response in responses.values) {
        final Object? content = response is Map<String, Object?>
            ? response['content']
            : null;
        if (content is! Map<String, Object?>) continue;
        for (final Object? media in content.values) {
          if (media is Map<String, Object?> &&
              media['schema'] is Map<String, Object?>) {
            media['schema'] = _walk(media['schema'], responseRoot: true);
          }
        }
      }
    }
  }
  final Object? walked = _walk(spec);

  File(target)
    ..createSync(recursive: true)
    ..writeAsStringSync(const JsonEncoder.withIndent('  ').convert(walked));
  stdout.writeln(
    'prepare_spec: $_replaced union(s) replaced by free-form values -> $target',
  );
}
