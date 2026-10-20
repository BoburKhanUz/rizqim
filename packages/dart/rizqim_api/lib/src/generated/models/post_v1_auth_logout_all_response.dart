// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_v1_auth_logout_all_response.g.dart';

@JsonSerializable()
class PostV1AuthLogoutAllResponse {
  const PostV1AuthLogoutAllResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AuthLogoutAllResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AuthLogoutAllResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() => _$PostV1AuthLogoutAllResponseToJson(this);
}
