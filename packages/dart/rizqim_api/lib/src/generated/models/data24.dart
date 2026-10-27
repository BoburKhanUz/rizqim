// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user.dart';
import 'store.dart';
import 'device4.dart';

part 'data24.g.dart';

@JsonSerializable()
class Data24 {
  const Data24({
    required this.token,
    required this.user,
    required this.store,
    required this.smsVerificationRequired,
    required this.isNewUser,
    this.tokenExpiresAt,
    this.refreshToken,
    this.refreshTokenExpiresAt,
    this.device,
  });

  factory Data24.fromJson(Map<String, Object?> json) => _$Data24FromJson(json);

  final String token;
  final User user;
  final Store? store;
  @JsonKey(name: 'sms_verification_required')
  final bool smsVerificationRequired;
  @JsonKey(name: 'is_new_user')
  final bool isNewUser;
  @JsonKey(name: 'token_expires_at')
  final String? tokenExpiresAt;
  @JsonKey(name: 'refresh_token')
  final String? refreshToken;
  @JsonKey(name: 'refresh_token_expires_at')
  final String? refreshTokenExpiresAt;
  final Device4? device;

  Map<String, Object?> toJson() => _$Data24ToJson(this);
}
