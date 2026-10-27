// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data11.dart';

part 'get_v1_admin_auth_sessions_response.g.dart';

@JsonSerializable()
class GetV1AdminAuthSessionsResponse {
  const GetV1AdminAuthSessionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1AdminAuthSessionsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1AdminAuthSessionsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data11> data;

  Map<String, Object?> toJson() => _$GetV1AdminAuthSessionsResponseToJson(this);
}
