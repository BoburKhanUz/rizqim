// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// Where an order came from (orders.channel). One Order model serves every channel (docs/ROADMAP.md): the kitchen sees them all, the stop-list reaches them all, and channel prices/visibility (channel_prices) differ per channel.
///
@JsonEnum()
enum OrderChannel {
  @JsonValue('telegram')
  telegram('telegram'),
  @JsonValue('mobile')
  mobile('mobile'),
  @JsonValue('pos_hall')
  posHall('pos_hall'),
  @JsonValue('pos_takeaway')
  posTakeaway('pos_takeaway'),
  @JsonValue('aggregator')
  aggregator('aggregator'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const OrderChannel(this.json);

  factory OrderChannel.fromJson(String json) =>
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
  static List<OrderChannel> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
