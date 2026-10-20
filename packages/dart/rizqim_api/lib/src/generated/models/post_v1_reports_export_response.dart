// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_v1_reports_export_response.g.dart';

@JsonSerializable()
class PostV1ReportsExportResponse {
  const PostV1ReportsExportResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1ReportsExportResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1ReportsExportResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() => _$PostV1ReportsExportResponseToJson(this);
}
