// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data20.g.dart';

@JsonSerializable()
class Data20 {
  const Data20({
    required this.inviteUrl,
    required this.inviteToken,
    required this.inviteExpiresAt,
  });

  factory Data20.fromJson(Map<String, Object?> json) => _$Data20FromJson(json);

  @JsonKey(name: 'invite_url')
  final String inviteUrl;
  @JsonKey(name: 'invite_token')
  final String inviteToken;
  @JsonKey(name: 'invite_expires_at')
  final String inviteExpiresAt;

  Map<String, Object?> toJson() => _$Data20ToJson(this);
}
