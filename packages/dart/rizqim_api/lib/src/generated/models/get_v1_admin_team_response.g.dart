// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_admin_team_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AdminTeamResponse _$GetV1AdminTeamResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AdminTeamResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data16.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1AdminTeamResponseToJson(
  GetV1AdminTeamResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
