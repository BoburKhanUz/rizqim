// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data74.dart';

part 'post_v1_owner_staff_response.g.dart';

@JsonSerializable()
class PostV1OwnerStaffResponse {
  const PostV1OwnerStaffResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerStaffResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerStaffResponseFromJson(json);

  final bool success;
  final String message;
  final Data74 data;

  Map<String, Object?> toJson() => _$PostV1OwnerStaffResponseToJson(this);
}
