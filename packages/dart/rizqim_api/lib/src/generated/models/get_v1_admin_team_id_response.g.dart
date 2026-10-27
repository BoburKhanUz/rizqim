// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_team_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminTeamIdResponse _$GetV1AdminTeamIdResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminTeamIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data18.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1AdminTeamIdResponseToJson(
  GetV1AdminTeamIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
