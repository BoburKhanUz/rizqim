// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// Where an order line is in the kitchen (order_items.kitchen_status, P2-11): `pending` until a cook bumps it on the kitchen display, then `ready`. P3-12 (docs/POS_SPEC.md §2.8, §10.3): a POS line is `pending` while it is only on the order (not sent yet, or held), `sent` once the POS sends it to the kitchen, then `ready`, and `served` when it reached the guests; `void` when it was taken off the order ("O'CHIRILDI"). An online (Telegram, mobile) line keeps the P2-11 meaning: `pending` is in the kitchen from the start (it has no sent_at and no fire_mode).
///
@JsonEnum()
enum KitchenItemStatus {
  @JsonValue('pending')
  pending('pending'),
  @JsonValue('ready')
  ready('ready'),
  @JsonValue('sent')
  sent('sent'),
  @JsonValue('served')
  served('served'),

  /// The name has been replaced because it contains a keyword. Original name: `void`.
  @JsonValue('void')
  valueVoid('void'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const KitchenItemStatus(this.json);

  factory KitchenItemStatus.fromJson(String json) =>
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
  static List<KitchenItemStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
