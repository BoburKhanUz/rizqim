// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data18.dart';

part 'get_v1_admin_team_id_response.g.dart';

@JsonSerializable()
class GetV1AdminTeamIdResponse {
  const GetV1AdminTeamIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1AdminTeamIdResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1AdminTeamIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data18 data;

  Map<String, Object?> toJson() => _$GetV1AdminTeamIdResponseToJson(this);
}
