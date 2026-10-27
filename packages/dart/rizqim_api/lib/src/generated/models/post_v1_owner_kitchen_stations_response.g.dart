// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_kitchen_stations_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerKitchenStationsResponse _$PostV1OwnerKitchenStationsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerKitchenStationsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data100.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerKitchenStationsResponseToJson(
  PostV1OwnerKitchenStationsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
