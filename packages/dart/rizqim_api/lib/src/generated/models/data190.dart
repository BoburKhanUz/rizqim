// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'polygon5.dart';

part 'data190.g.dart';

@JsonSerializable()
class Data190 {
  const Data190({
    required this.id,
    required this.name,
    required this.polygon,
    required this.deliveryPrice,
    required this.minOrderAmount,
    required this.freeDeliveryFrom,
    required this.etaMinutes,
    required this.priority,
  });

  factory Data190.fromJson(Map<String, Object?> json) =>
      _$Data190FromJson(json);

  final int id;
  final String name;
  final Polygon5 polygon;
  @JsonKey(name: 'delivery_price')
  final String deliveryPrice;
  @JsonKey(name: 'min_order_amount')
  final String minOrderAmount;
  @JsonKey(name: 'free_delivery_from')
  final String? freeDeliveryFrom;
  @JsonKey(name: 'eta_minutes')
  final int? etaMinutes;
  final int priority;

  Map<String, Object?> toJson() => _$Data190ToJson(this);
}
