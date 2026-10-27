// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data16.dart';

part 'get_v1_admin_team_response.g.dart';

@JsonSerializable()
class GetV1AdminTeamResponse {
  const GetV1AdminTeamResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1AdminTeamResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1AdminTeamResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data16> data;

  Map<String, Object?> toJson() => _$GetV1AdminTeamResponseToJson(this);
}
