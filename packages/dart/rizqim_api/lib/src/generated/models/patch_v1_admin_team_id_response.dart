// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data19.dart';

part 'patch_v1_admin_team_id_response.g.dart';

@JsonSerializable()
class PatchV1AdminTeamIdResponse {
  const PatchV1AdminTeamIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1AdminTeamIdResponse.fromJson(Map<String, Object?> json) =>
      _$PatchV1AdminTeamIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data19 data;

  Map<String, Object?> toJson() => _$PatchV1AdminTeamIdResponseToJson(this);
}
