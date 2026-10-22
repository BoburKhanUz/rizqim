// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// What a place on the floor map is (dining_tables.kind, P3-12): a plain table, a room ("xona", "kabinka": a long session with a room fee and often a minimum spend) or a tapchan.
///
@JsonEnum()
enum DiningTableKind {
  @JsonValue('table')
  table('table'),
  @JsonValue('room')
  room('room'),
  @JsonValue('tapchan')
  tapchan('tapchan'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const DiningTableKind(this.json);

  factory DiningTableKind.fromJson(String json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final String? json;
  String toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to String. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as String;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<DiningTableKind> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
