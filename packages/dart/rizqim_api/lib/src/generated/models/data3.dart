// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device3.dart';

part 'data3.g.dart';

@JsonSerializable()
class Data3 {
  const Data3({
    required this.token,
    required this.tokenExpiresAt,
    required this.refreshToken,
    required this.refreshTokenExpiresAt,
    required this.device,
  });

  factory Data3.fromJson(Map<String, Object?> json) => _$Data3FromJson(json);

  final String token;
  @JsonKey(name: 'token_expires_at')
  final String tokenExpiresAt;
  @JsonKey(name: 'refresh_token')
  final String refreshToken;
  @JsonKey(name: 'refresh_token_expires_at')
  final String refreshTokenExpiresAt;
  final Device3 device;

  Map<String, Object?> toJson() => _$Data3ToJson(this);
}
