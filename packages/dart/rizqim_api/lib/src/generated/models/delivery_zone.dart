// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch.dart';
import 'polygon6.dart';
import 'store.dart';

part 'delivery_zone.g.dart';

@JsonSerializable()
class DeliveryZone {
  const DeliveryZone({
    required this.id,
    required this.storeId,
    required this.branchId,
    required this.name,
    required this.polygon,
    required this.minLat,
    required this.maxLat,
    required this.minLng,
    required this.maxLng,
    required this.areaKm2,
    required this.deliveryPrice,
    required this.minOrderAmount,
    required this.freeDeliveryFrom,
    required this.etaMinutes,
    required this.priority,
    required this.active,
    required this.createdAt,
    required this.updatedAt,
    this.store,
    this.branch,
  });

  factory DeliveryZone.fromJson(Map<String, Object?> json) =>
      _$DeliveryZoneFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String name;
  final Polygon6 polygon;
  @JsonKey(name: 'min_lat')
  final String minLat;
  @JsonKey(name: 'max_lat')
  final String maxLat;
  @JsonKey(name: 'min_lng')
  final String minLng;
  @JsonKey(name: 'max_lng')
  final String maxLng;
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
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Store? store;
  final Branch? branch;

  Map<String, Object?> toJson() => _$DeliveryZoneToJson(this);
}
