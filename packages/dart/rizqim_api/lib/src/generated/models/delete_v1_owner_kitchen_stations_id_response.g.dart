// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_kitchen_stations_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerKitchenStationsIdResponse
_$DeleteV1OwnerKitchenStationsIdResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerKitchenStationsIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'],
    );

Map<String, dynamic> _$DeleteV1OwnerKitchenStationsIdResponseToJson(
  DeleteV1OwnerKitchenStationsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
