// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data149.dart';

part 'post_v1_owner_staff_pay_statements_response.g.dart';

@JsonSerializable()
class PostV1OwnerStaffPayStatementsResponse {
  const PostV1OwnerStaffPayStatementsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerStaffPayStatementsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerStaffPayStatementsResponseFromJson(json);

  final bool success;
  final String message;
  final Data149 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerStaffPayStatementsResponseToJson(this);
}
