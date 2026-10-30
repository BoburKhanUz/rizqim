// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data157.dart';

part 'post_v1_owner_staff_pay_work_sessions_response.g.dart';

@JsonSerializable()
class PostV1OwnerStaffPayWorkSessionsResponse {
  const PostV1OwnerStaffPayWorkSessionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerStaffPayWorkSessionsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerStaffPayWorkSessionsResponseFromJson(json);

  final bool success;
  final String message;
  final Data157 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerStaffPayWorkSessionsResponseToJson(this);
}
