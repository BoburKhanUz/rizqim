// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_pickup_slots_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosPickupSlotsResponse _$GetV1PosPickupSlotsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosPickupSlotsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data252.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosPickupSlotsResponseToJson(
  GetV1PosPickupSlotsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
