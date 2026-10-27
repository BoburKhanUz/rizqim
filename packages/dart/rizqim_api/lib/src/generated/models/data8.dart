// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data8.g.dart';

@JsonSerializable()
class Data8 {
  const Data8({
    required this.status,
    required this.challengeToken,
    required this.expiresAt,
  });

  factory Data8.fromJson(Map<String, Object?> json) => _$Data8FromJson(json);

  final String status;
  @JsonKey(name: 'challenge_token')
  final String challengeToken;
  @JsonKey(name: 'expires_at')
  final String expiresAt;

  Map<String, Object?> toJson() => _$Data8ToJson(this);
}
