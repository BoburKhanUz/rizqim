// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_room_tariffs_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerRoomTariffsIdResponse _$PatchV1OwnerRoomTariffsIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerRoomTariffsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data136.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerRoomTariffsIdResponseToJson(
  PatchV1OwnerRoomTariffsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
