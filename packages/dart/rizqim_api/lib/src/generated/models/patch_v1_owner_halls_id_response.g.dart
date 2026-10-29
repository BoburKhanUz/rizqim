// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_owner_halls_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1OwnerHallsIdResponse _$PatchV1OwnerHallsIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1OwnerHallsIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data87.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1OwnerHallsIdResponseToJson(
  PatchV1OwnerHallsIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
