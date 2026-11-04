// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6 local bridge: what a terminal is on its branch's LAN (GET /pos/bootstrap `lan.role`): the hub (the main till runs the bridge and holds the branch's secret) or a client (a waiter's phone, a KDS, a customer display, another till).
///
@JsonEnum()
enum LanRole {
  @JsonValue('hub')
  hub('hub'),
  @JsonValue('client')
  client('client'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const LanRole(this.json);

  factory LanRole.fromJson(String json) =>
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
  static List<LanRole> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
