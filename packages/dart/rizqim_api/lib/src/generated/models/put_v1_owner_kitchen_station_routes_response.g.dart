// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_kitchen_station_routes_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerKitchenStationRoutesResponse
_$PutV1OwnerKitchenStationRoutesResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerKitchenStationRoutesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: (json['data'] as List<dynamic>)
          .map((e) => Data77.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$PutV1OwnerKitchenStationRoutesResponseToJson(
  PutV1OwnerKitchenStationRoutesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
