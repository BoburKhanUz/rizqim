// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'platform_role.dart';

part 'v1_admin_team_request_body.g.dart';

@JsonSerializable()
class V1AdminTeamRequestBody {
  const V1AdminTeamRequestBody({
    required this.name,
    required this.email,
    required this.platformRole,
  });

  factory V1AdminTeamRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AdminTeamRequestBodyFromJson(json);

  final String name;
  final String email;
  @JsonKey(name: 'platform_role')
  final PlatformRole platformRole;

  Map<String, Object?> toJson() => _$V1AdminTeamRequestBodyToJson(this);
}
