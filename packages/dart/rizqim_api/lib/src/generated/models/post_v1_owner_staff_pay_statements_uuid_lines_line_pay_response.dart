// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data153.dart';

part 'post_v1_owner_staff_pay_statements_uuid_lines_line_pay_response.g.dart';

@JsonSerializable()
class PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse {
  const PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerStaffPayStatementsUuidLinesLinePayResponseFromJson(json);

  final bool success;
  final String message;
  final Data153 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerStaffPayStatementsUuidLinesLinePayResponseToJson(this);
}
