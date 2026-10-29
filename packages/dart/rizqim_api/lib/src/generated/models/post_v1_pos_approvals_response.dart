// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data137.dart';

part 'post_v1_pos_approvals_response.g.dart';

@JsonSerializable()
class PostV1PosApprovalsResponse {
  const PostV1PosApprovalsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosApprovalsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosApprovalsResponseFromJson(json);

  final bool success;
  final String message;
  final Data137 data;

  Map<String, Object?> toJson() => _$PostV1PosApprovalsResponseToJson(this);
}
