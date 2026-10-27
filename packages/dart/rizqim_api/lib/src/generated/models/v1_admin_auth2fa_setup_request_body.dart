// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_admin_auth2fa_setup_request_body.g.dart';

@JsonSerializable()
class V1AdminAuth2faSetupRequestBody {
  const V1AdminAuth2faSetupRequestBody({required this.challengeToken});

  factory V1AdminAuth2faSetupRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AdminAuth2faSetupRequestBodyFromJson(json);

  @JsonKey(name: 'challenge_token')
  final String challengeToken;

  Map<String, Object?> toJson() => _$V1AdminAuth2faSetupRequestBodyToJson(this);
}
