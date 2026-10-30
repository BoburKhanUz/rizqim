// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_delivery_zones_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerDeliveryZonesIdResponse _$GetV1OwnerDeliveryZonesIdResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerDeliveryZonesIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data56.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerDeliveryZonesIdResponseToJson(
  GetV1OwnerDeliveryZonesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
