// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_kitchen_stations_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerKitchenStationsIdResponse
_$PatchV1OwnerKitchenStationsIdResponseFromJson(Map<String, dynamic> json) =>
    PatchV1OwnerKitchenStationsIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data96.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1OwnerKitchenStationsIdResponseToJson(
  PatchV1OwnerKitchenStationsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
