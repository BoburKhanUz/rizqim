// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_v1_auth_logout_response.g.dart';

@JsonSerializable()
class PostV1AuthLogoutResponse {
  const PostV1AuthLogoutResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AuthLogoutResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AuthLogoutResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() => _$PostV1AuthLogoutResponseToJson(this);
}
