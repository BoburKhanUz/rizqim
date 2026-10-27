// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data21.dart';

part 'post_v1_admin_team_id_reset2fa_response.g.dart';

@JsonSerializable()
class PostV1AdminTeamIdReset2faResponse {
  const PostV1AdminTeamIdReset2faResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminTeamIdReset2faResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1AdminTeamIdReset2faResponseFromJson(json);

  final bool success;
  final String message;
  final Data21 data;

  Map<String, Object?> toJson() =>
      _$PostV1AdminTeamIdReset2faResponseToJson(this);
}
