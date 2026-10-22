// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

@JsonEnum()
enum PaperWidth {
  @JsonValue(58)
  value58(58),
  @JsonValue(80)
  value80(80),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const PaperWidth(this.json);

  factory PaperWidth.fromJson(int json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final int? json;
  int toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to int. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as int;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<PaperWidth> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
