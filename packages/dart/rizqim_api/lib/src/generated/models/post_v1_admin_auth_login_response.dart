// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data9.dart';

part 'post_v1_admin_auth_login_response.g.dart';

@JsonSerializable()
class PostV1AdminAuthLoginResponse {
  const PostV1AdminAuthLoginResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminAuthLoginResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AdminAuthLoginResponseFromJson(json);

  final bool success;
  final String message;
  final Data9 data;

  Map<String, Object?> toJson() => _$PostV1AdminAuthLoginResponseToJson(this);
}
