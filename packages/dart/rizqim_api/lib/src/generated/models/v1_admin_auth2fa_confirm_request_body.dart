// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_admin_auth2fa_confirm_request_body.g.dart';

@JsonSerializable()
class V1AdminAuth2faConfirmRequestBody {
  const V1AdminAuth2faConfirmRequestBody({
    required this.challengeToken,
    required this.code,
  });

  factory V1AdminAuth2faConfirmRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1AdminAuth2faConfirmRequestBodyFromJson(json);

  @JsonKey(name: 'challenge_token')
  final String challengeToken;
  final String code;

  Map<String, Object?> toJson() =>
      _$V1AdminAuth2faConfirmRequestBodyToJson(this);
}
