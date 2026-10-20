// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_v1_phone_send_code_response.g.dart';

@JsonSerializable()
class PostV1PhoneSendCodeResponse {
  const PostV1PhoneSendCodeResponse({
    required this.success,
    required this.message,
    required this.code,
  });

  factory PostV1PhoneSendCodeResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PhoneSendCodeResponseFromJson(json);

  final bool success;
  final String message;
  final String? code;

  Map<String, Object?> toJson() => _$PostV1PhoneSendCodeResponseToJson(this);
}
