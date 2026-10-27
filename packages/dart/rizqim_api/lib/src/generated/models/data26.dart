// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store.dart';
import 'device7.dart';

part 'data26.g.dart';

@JsonSerializable()
class Data26 {
  const Data26({
    required this.token,
    required this.user,
    required this.store,
    required this.smsVerificationRequired,
    this.tokenExpiresAt,
    this.refreshToken,
    this.refreshTokenExpiresAt,
    this.device,
  });

  factory Data26.fromJson(Map<String, Object?> json) => _$Data26FromJson(json);

  final String token;
  final dynamic user;
  final Store? store;
  @JsonKey(name: 'sms_verification_required')
  final bool smsVerificationRequired;
  @JsonKey(name: 'token_expires_at')
  final String? tokenExpiresAt;
  @JsonKey(name: 'refresh_token')
  final String? refreshToken;
  @JsonKey(name: 'refresh_token_expires_at')
  final String? refreshTokenExpiresAt;
  final Device7? device;

  Map<String, Object?> toJson() => _$Data26ToJson(this);
}
