// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch6.dart';
import 'polygon4.dart';
import 'bbox4.dart';

part 'data39.g.dart';

@JsonSerializable()
class Data39 {
  const Data39({
    required this.id,
    required this.name,
    required this.branchId,
    required this.branch,
    required this.polygon,
    required this.bbox,
    required this.areaKm2,
    required this.deliveryPrice,
    required this.minOrderAmount,
    required this.freeDeliveryFrom,
    required this.etaMinutes,
    required this.priority,
    required this.active,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Data39.fromJson(Map<String, Object?> json) => _$Data39FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Branch6? branch;
  final Polygon4 polygon;
  final Bbox4 bbox;
  @JsonKey(name: 'area_km2')
  final String areaKm2;
  @JsonKey(name: 'delivery_price')
  final String deliveryPrice;
  @JsonKey(name: 'min_order_amount')
  final String minOrderAmount;
  @JsonKey(name: 'free_delivery_from')
  final String? freeDeliveryFrom;
  @JsonKey(name: 'eta_minutes')
  final int? etaMinutes;
  final int priority;
  final bool active;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data39ToJson(this);
}
