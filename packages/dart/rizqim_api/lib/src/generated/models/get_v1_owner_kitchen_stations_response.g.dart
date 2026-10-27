// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_kitchen_stations_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerKitchenStationsResponse _$GetV1OwnerKitchenStationsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerKitchenStationsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data78.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerKitchenStationsResponseToJson(
  GetV1OwnerKitchenStationsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
