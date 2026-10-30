// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data167.dart';

part 'post_v1_owner_staff_pay_statements_uuid_close_response.g.dart';

@JsonSerializable()
class PostV1OwnerStaffPayStatementsUuidCloseResponse {
  const PostV1OwnerStaffPayStatementsUuidCloseResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerStaffPayStatementsUuidCloseResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerStaffPayStatementsUuidCloseResponseFromJson(json);

  final bool success;
  final String message;
  final Data167 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerStaffPayStatementsUuidCloseResponseToJson(this);
}
