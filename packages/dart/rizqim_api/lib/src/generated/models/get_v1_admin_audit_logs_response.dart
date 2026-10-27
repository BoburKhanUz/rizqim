// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data2.dart';

part 'get_v1_admin_audit_logs_response.g.dart';

@JsonSerializable()
class GetV1AdminAuditLogsResponse {
  const GetV1AdminAuditLogsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1AdminAuditLogsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1AdminAuditLogsResponseFromJson(json);

  final bool success;
  final String message;
  final Data2 data;

  Map<String, Object?> toJson() => _$GetV1AdminAuditLogsResponseToJson(this);
}
