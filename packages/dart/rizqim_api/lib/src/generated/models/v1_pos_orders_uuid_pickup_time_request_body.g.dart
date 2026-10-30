// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_pickup_time_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidPickupTimeRequestBody
_$V1PosOrdersUuidPickupTimeRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosOrdersUuidPickupTimeRequestBody(
      version: (json['version'] as num).toInt(),
      pickupAt: json['pickup_at'] == null
          ? null
          : DateTime.parse(json['pickup_at'] as String),
    );

Map<String, dynamic> _$V1PosOrdersUuidPickupTimeRequestBodyToJson(
  V1PosOrdersUuidPickupTimeRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'pickup_at': instance.pickupAt?.toIso8601String(),
};
