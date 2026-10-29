// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_delivery_zones_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerDeliveryZonesResponse _$PostV1OwnerDeliveryZonesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerDeliveryZonesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data53.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerDeliveryZonesResponseToJson(
  PostV1OwnerDeliveryZonesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
