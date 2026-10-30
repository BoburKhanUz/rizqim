// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data170.dart';

part 'post_v1_owner_staff_pay_statements_uuid_recompute_response.g.dart';

@JsonSerializable()
class PostV1OwnerStaffPayStatementsUuidRecomputeResponse {
  const PostV1OwnerStaffPayStatementsUuidRecomputeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerStaffPayStatementsUuidRecomputeResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerStaffPayStatementsUuidRecomputeResponseFromJson(json);

  final bool success;
  final String message;
  final Data170 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerStaffPayStatementsUuidRecomputeResponseToJson(this);
}
