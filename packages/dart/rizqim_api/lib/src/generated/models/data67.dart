// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch3.dart';
import 'polygon.dart';
import 'bbox.dart';

part 'data67.g.dart';

@JsonSerializable()
class Data67 {
  const Data67({
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

  factory Data67.fromJson(Map<String, Object?> json) => _$Data67FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Branch3? branch;
  final Polygon polygon;
  final Bbox bbox;
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

  Map<String, Object?> toJson() => _$Data67ToJson(this);
}
