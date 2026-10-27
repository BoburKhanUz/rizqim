// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data4.dart';

part 'post_v1_admin_auth2fa_recovery_codes_response.g.dart';

@JsonSerializable()
class PostV1AdminAuth2faRecoveryCodesResponse {
  const PostV1AdminAuth2faRecoveryCodesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminAuth2faRecoveryCodesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1AdminAuth2faRecoveryCodesResponseFromJson(json);

  final bool success;
  final String message;
  final Data4 data;

  Map<String, Object?> toJson() =>
      _$PostV1AdminAuth2faRecoveryCodesResponseToJson(this);
}
