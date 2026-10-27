// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data20.dart';

part 'post_v1_admin_team_id_invite_response.g.dart';

@JsonSerializable()
class PostV1AdminTeamIdInviteResponse {
  const PostV1AdminTeamIdInviteResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminTeamIdInviteResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AdminTeamIdInviteResponseFromJson(json);

  final bool success;
  final String message;
  final Data20 data;

  Map<String, Object?> toJson() =>
      _$PostV1AdminTeamIdInviteResponseToJson(this);
}
