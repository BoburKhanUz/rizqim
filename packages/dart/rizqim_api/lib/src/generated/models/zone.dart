// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'zone.g.dart';

@JsonSerializable()
class Zone {
  const Zone({
    required this.id,
    required this.name,
    required this.deliveryPrice,
    required this.minOrderAmount,
    required this.freeDeliveryFrom,
    required this.etaMinutes,
  });

  factory Zone.fromJson(Map<String, Object?> json) => _$ZoneFromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'delivery_price')
  final String deliveryPrice;
  @JsonKey(name: 'min_order_amount')
  final String minOrderAmount;
  @JsonKey(name: 'free_delivery_from')
  final String? freeDeliveryFrom;
  @JsonKey(name: 'eta_minutes')
  final int? etaMinutes;

  Map<String, Object?> toJson() => _$ZoneToJson(this);
}
