// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store.dart';
import 'device6.dart';

part 'data5.g.dart';

@JsonSerializable()
class Data5 {
  const Data5({
    required this.token,
    required this.user,
    required this.store,
    required this.smsVerificationRequired,
    this.tokenExpiresAt,
    this.refreshToken,
    this.refreshTokenExpiresAt,
    this.device,
  });

  factory Data5.fromJson(Map<String, Object?> json) => _$Data5FromJson(json);

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
  final Device6? device;

  Map<String, Object?> toJson() => _$Data5ToJson(this);
}
