// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-32 (B7): the label roll in the branch's label printer (branch_label_settings.size, width x height in mm). The cashier app lays the sticker out for this size (pos_core `LabelSize`, 203 dpi) and the TSPL driver sends it.
///
@JsonEnum()
enum LabelSize {
  @JsonValue('40x30')
  value40x30('40x30'),
  @JsonValue('58x40')
  value58x40('58x40'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const LabelSize(this.json);

  factory LabelSize.fromJson(String json) =>
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
  static List<LabelSize> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
