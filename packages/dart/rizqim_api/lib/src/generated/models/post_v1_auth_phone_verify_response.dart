// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data24.dart';

part 'post_v1_auth_phone_verify_response.g.dart';

@JsonSerializable()
class PostV1AuthPhoneVerifyResponse {
  const PostV1AuthPhoneVerifyResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AuthPhoneVerifyResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AuthPhoneVerifyResponseFromJson(json);

  final bool success;
  final String message;
  final Data24 data;

  Map<String, Object?> toJson() => _$PostV1AuthPhoneVerifyResponseToJson(this);
}
