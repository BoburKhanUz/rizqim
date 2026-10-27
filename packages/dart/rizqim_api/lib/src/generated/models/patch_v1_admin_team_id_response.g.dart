// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_admin_team_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1AdminTeamIdResponse _$PatchV1AdminTeamIdResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1AdminTeamIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data19.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1AdminTeamIdResponseToJson(
  PatchV1AdminTeamIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
