// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_room_tariffs_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerRoomTariffsResponse _$GetV1OwnerRoomTariffsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerRoomTariffsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data119.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerRoomTariffsResponseToJson(
  GetV1OwnerRoomTariffsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
