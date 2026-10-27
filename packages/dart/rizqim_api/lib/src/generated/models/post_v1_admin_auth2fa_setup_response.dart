// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data5.dart';

part 'post_v1_admin_auth2fa_setup_response.g.dart';

@JsonSerializable()
class PostV1AdminAuth2faSetupResponse {
  const PostV1AdminAuth2faSetupResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminAuth2faSetupResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AdminAuth2faSetupResponseFromJson(json);

  final bool success;
  final String message;
  final Data5 data;

  Map<String, Object?> toJson() =>
      _$PostV1AdminAuth2faSetupResponseToJson(this);
}
