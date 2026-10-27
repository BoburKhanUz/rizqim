// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'platform_role.dart';

part 'v1_admin_team_id_request_body.g.dart';

@JsonSerializable()
class V1AdminTeamIdRequestBody {
  const V1AdminTeamIdRequestBody({this.platformRole, this.disabled});

  factory V1AdminTeamIdRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AdminTeamIdRequestBodyFromJson(json);

  @JsonKey(name: 'platform_role')
  final PlatformRole? platformRole;
  final bool? disabled;

  Map<String, Object?> toJson() => _$V1AdminTeamIdRequestBodyToJson(this);
}
