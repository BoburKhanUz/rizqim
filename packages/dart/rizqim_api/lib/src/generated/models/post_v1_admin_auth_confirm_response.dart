// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data7.dart';

part 'post_v1_admin_auth_confirm_response.g.dart';

@JsonSerializable()
class PostV1AdminAuthConfirmResponse {
  const PostV1AdminAuthConfirmResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminAuthConfirmResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AdminAuthConfirmResponseFromJson(json);

  final bool success;
  final String message;
  final Data7 data;

  Map<String, Object?> toJson() => _$PostV1AdminAuthConfirmResponseToJson(this);
}
