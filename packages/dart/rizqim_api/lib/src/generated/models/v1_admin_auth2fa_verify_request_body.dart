// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_admin_auth2fa_verify_request_body.g.dart';

@JsonSerializable()
class V1AdminAuth2faVerifyRequestBody {
  const V1AdminAuth2faVerifyRequestBody({
    required this.challengeToken,
    this.code,
    this.recoveryCode,
  });

  factory V1AdminAuth2faVerifyRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AdminAuth2faVerifyRequestBodyFromJson(json);

  @JsonKey(name: 'challenge_token')
  final String challengeToken;
  final String? code;
  @JsonKey(name: 'recovery_code')
  final String? recoveryCode;

  Map<String, Object?> toJson() =>
      _$V1AdminAuth2faVerifyRequestBodyToJson(this);
}
