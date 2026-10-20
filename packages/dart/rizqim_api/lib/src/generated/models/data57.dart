// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'polygon.dart';

part 'data57.g.dart';

@JsonSerializable()
class Data57 {
  const Data57({
    required this.id,
    required this.name,
    required this.polygon,
    required this.deliveryPrice,
    required this.minOrderAmount,
    required this.freeDeliveryFrom,
    required this.etaMinutes,
    required this.priority,
  });

  factory Data57.fromJson(Map<String, Object?> json) => _$Data57FromJson(json);

  final int id;
  final String name;
  final Polygon polygon;
  @JsonKey(name: 'delivery_price')
  final String deliveryPrice;
  @JsonKey(name: 'min_order_amount')
  final String minOrderAmount;
  @JsonKey(name: 'free_delivery_from')
  final String? freeDeliveryFrom;
  @JsonKey(name: 'eta_minutes')
  final int? etaMinutes;
  final int priority;

  Map<String, Object?> toJson() => _$Data57ToJson(this);
}
