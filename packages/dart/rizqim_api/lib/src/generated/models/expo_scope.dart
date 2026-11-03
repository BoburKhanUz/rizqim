// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// Which orders go through a branch's expo (assembly) station (branches.pos_settings.expo_scope, owner decision 03.11.2026, docs/POS_SPEC.md "KDS v2"): `all` (the default) every order a customer or courier waits for, one station or more; `multi_station` only an order cooked on two or more stations (the rule of P6-33 before the decision).
///
@JsonEnum()
enum ExpoScope {
  @JsonValue('all')
  all('all'),
  @JsonValue('multi_station')
  multiStation('multi_station'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const ExpoScope(this.json);

  factory ExpoScope.fromJson(String json) =>
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
  static List<ExpoScope> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
