// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_admin_auth_sessions_id_response.g.dart';

@JsonSerializable()
class DeleteV1AdminAuthSessionsIdResponse {
  const DeleteV1AdminAuthSessionsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1AdminAuthSessionsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1AdminAuthSessionsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Map<String, dynamic> data;

  Map<String, Object?> toJson() =>
      _$DeleteV1AdminAuthSessionsIdResponseToJson(this);
}
