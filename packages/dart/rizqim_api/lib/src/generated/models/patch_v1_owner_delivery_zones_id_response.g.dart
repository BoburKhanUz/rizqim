// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_delivery_zones_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerDeliveryZonesIdResponse
_$PatchV1OwnerDeliveryZonesIdResponseFromJson(Map<String, dynamic> json) =>
    PatchV1OwnerDeliveryZonesIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data45.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1OwnerDeliveryZonesIdResponseToJson(
  PatchV1OwnerDeliveryZonesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
