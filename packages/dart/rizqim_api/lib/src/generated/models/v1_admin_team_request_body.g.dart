// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_team_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminTeamRequestBody _$V1AdminTeamRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminTeamRequestBody(
  name: json['name'] as String,
  email: json['email'] as String,
  platformRole: PlatformRole.fromJson(json['platform_role'] as String),
);

Map<String, dynamic> _$V1AdminTeamRequestBodyToJson(
  V1AdminTeamRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'email': instance.email,
  'platform_role': instance.platformRole,
};
