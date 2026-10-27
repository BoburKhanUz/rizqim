// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data11.g.dart';

@JsonSerializable()
class Data11 {
  const Data11({
    required this.id,
    required this.ip,
    required this.userAgent,
    required this.createdAt,
    required this.lastUsedAt,
    required this.expiresAt,
    required this.reauthenticatedUntil,
    required this.current,
  });

  factory Data11.fromJson(Map<String, Object?> json) => _$Data11FromJson(json);

  final int id;
  final String? ip;
  @JsonKey(name: 'user_agent')
  final String? userAgent;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'last_used_at')
  final String? lastUsedAt;
  @JsonKey(name: 'expires_at')
  final String? expiresAt;
  @JsonKey(name: 'reauthenticated_until')
  final String? reauthenticatedUntil;
  final bool current;

  Map<String, Object?> toJson() => _$Data11ToJson(this);
}
