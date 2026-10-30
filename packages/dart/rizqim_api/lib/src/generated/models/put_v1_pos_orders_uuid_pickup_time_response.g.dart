// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_pos_orders_uuid_pickup_time_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1PosOrdersUuidPickupTimeResponse
_$PutV1PosOrdersUuidPickupTimeResponseFromJson(Map<String, dynamic> json) =>
    PutV1PosOrdersUuidPickupTimeResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data244.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1PosOrdersUuidPickupTimeResponseToJson(
  PutV1PosOrdersUuidPickupTimeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
