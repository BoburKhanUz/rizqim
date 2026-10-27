// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_admin_team_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AdminTeamIdRequestBody _$V1AdminTeamIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AdminTeamIdRequestBody(
  platformRole: json['platform_role'] == null
      ? null
      : PlatformRole.fromJson(json['platform_role'] as String),
  disabled: json['disabled'] as bool?,
);

Map<String, dynamic> _$V1AdminTeamIdRequestBodyToJson(
  V1AdminTeamIdRequestBody instance,
) => <String, dynamic>{
  'platform_role': instance.platformRole,
  'disabled': instance.disabled,
};
