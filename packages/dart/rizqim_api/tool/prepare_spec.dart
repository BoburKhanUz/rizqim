// Copies packages/api-spec/openapi.json to .dart_tool/openapi.prepared.json for swagger_parser.
//
// swagger_parser cannot generate valid json_serializable code for an `anyOf`/`oneOf` of several real types (it
// writes "sealed" classes it never creates). Such a union becomes a free-form value here:
//   * a whole response body (GET /v1/orders answers with a cursor page or a plain list) -> `dynamic` (decoded JSON);
//   * a property or item -> `dynamic`.
// A nullable type (`anyOf: [X, {type: null}]`) is not a union and is kept as is; so is everything else: all other
// models and endpoints are generated unchanged. The apps read the few free-form fields they need.
//
// Enum fields of multipart/form-data bodies become plain strings: Retrofit writes `field.toJson()` (a `String?` for
// swagger_parser enums) into the form, which does not compile.
import 'dart:convert';
import 'dart:io';

int _replaced = 0;
int _formEnums = 0;

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

/// Drops `enum` from the properties of multipart/form-data request bodies (see the header).
void _plainFormEnums(Object? spec) {
  final Object? paths = spec is Map<String, Object?> ? spec['paths'] : null;
  if (paths is! Map<String, Object?>) return;
  for (final Object? item in paths.values) {
    if (item is! Map<String, Object?>) continue;
    for (final Object? operation in item.values) {
      final Object? body = operation is Map<String, Object?>
          ? operation['requestBody']
          : null;
      final Object? content = body is Map<String, Object?>
          ? body['content']
          : null;
      final Object? form = content is Map<String, Object?>
          ? content['multipart/form-data']
          : null;
      final Object? schema = form is Map<String, Object?>
          ? form['schema']
          : null;
      final Object? properties = schema is Map<String, Object?>
          ? schema['properties']
          : null;
      if (properties is! Map<String, Object?>) continue;
      for (final Object? property in properties.values) {
        if (property is Map<String, Object?> &&
            property.remove('enum') != null) {
          _formEnums++;
        }
      }
    }
  }
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
  _plainFormEnums(walked);

  File(target)
    ..createSync(recursive: true)
    ..writeAsStringSync(const JsonEncoder.withIndent('  ').convert(walked));
  stdout.writeln(
    'prepare_spec: $_replaced union(s) replaced by free-form values, '
    '$_formEnums form enum(s) made plain strings -> $target',
  );
}
