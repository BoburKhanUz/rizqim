// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data6.dart';

part 'post_v1_admin_auth2fa_verify_response.g.dart';

@JsonSerializable()
class PostV1AdminAuth2faVerifyResponse {
  const PostV1AdminAuth2faVerifyResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminAuth2faVerifyResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1AdminAuth2faVerifyResponseFromJson(json);

  final bool success;
  final String message;
  final Data6 data;

  Map<String, Object?> toJson() =>
      _$PostV1AdminAuth2faVerifyResponseToJson(this);
}
