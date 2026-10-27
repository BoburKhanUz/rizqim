// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data23.dart';

part 'post_v1_auth_phone_send_code_response.g.dart';

@JsonSerializable()
class PostV1AuthPhoneSendCodeResponse {
  const PostV1AuthPhoneSendCodeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AuthPhoneSendCodeResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AuthPhoneSendCodeResponseFromJson(json);

  final bool success;
  final String message;
  final Data23 data;

  Map<String, Object?> toJson() =>
      _$PostV1AuthPhoneSendCodeResponseToJson(this);
}
