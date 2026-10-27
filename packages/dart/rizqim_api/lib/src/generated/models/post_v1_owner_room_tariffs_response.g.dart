// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_room_tariffs_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerRoomTariffsResponse _$PostV1OwnerRoomTariffsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerRoomTariffsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data121.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerRoomTariffsResponseToJson(
  PostV1OwnerRoomTariffsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
