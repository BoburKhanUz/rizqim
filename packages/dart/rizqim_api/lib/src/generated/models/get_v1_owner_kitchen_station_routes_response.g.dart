// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_kitchen_station_routes_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerKitchenStationRoutesResponse
_$GetV1OwnerKitchenStationRoutesResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerKitchenStationRoutesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: (json['data'] as List<dynamic>)
          .map((e) => Data97.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$GetV1OwnerKitchenStationRoutesResponseToJson(
  GetV1OwnerKitchenStationRoutesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
