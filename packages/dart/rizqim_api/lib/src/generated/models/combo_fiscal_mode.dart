// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-31 (owner decision 30.10.2026): how a combo goes on the fiscal receipt (stores.fiscal_settings.combo_fiscal_mode). - `single` (default): ONE line, the combo product's own name, MXIK and package code, quantity and price; the parts    are printed under it without prices on the guest's receipt only;  - `split`: one line per part, each with its dish's own MXIK, whose amounts are shares of the combo line by the parts'    list prices (ComboFiscalSplit: largest remainder in tiyin, ties to the earlier part), so they add up exactly to    the combo line (after its discount share; the service charge stays its own line). For when the accountant asks.
///
@JsonEnum()
enum ComboFiscalMode {
  @JsonValue('single')
  single('single'),
  @JsonValue('split')
  split('split'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const ComboFiscalMode(this.json);

  factory ComboFiscalMode.fromJson(String json) =>
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
  static List<ComboFiscalMode> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
