// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_delivery_zones_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerDeliveryZonesIdRequestBody {
  const V1OwnerDeliveryZonesIdRequestBody({
    this.name,
    this.branchId,
    this.priority,
    this.active,
    this.deliveryPrice,
    this.minOrderAmount,
    this.freeDeliveryFrom,
    this.etaMinutes,
    this.polygon,
  });

  factory V1OwnerDeliveryZonesIdRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerDeliveryZonesIdRequestBodyFromJson(json);

  final String? name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final int? priority;
  final bool? active;
  @JsonKey(name: 'delivery_price')
  final num? deliveryPrice;
  @JsonKey(name: 'min_order_amount')
  final num? minOrderAmount;
  @JsonKey(name: 'free_delivery_from')
  final num? freeDeliveryFrom;
  @JsonKey(name: 'eta_minutes')
  final int? etaMinutes;
  final dynamic polygon;

  Map<String, Object?> toJson() =>
      _$V1OwnerDeliveryZonesIdRequestBodyToJson(this);
}
