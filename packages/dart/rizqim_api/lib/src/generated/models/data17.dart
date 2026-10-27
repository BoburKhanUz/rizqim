// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member5.dart';

part 'data17.g.dart';

@JsonSerializable()
class Data17 {
  const Data17({
    required this.member,
    required this.inviteUrl,
    required this.inviteToken,
    required this.inviteExpiresAt,
  });

  factory Data17.fromJson(Map<String, Object?> json) => _$Data17FromJson(json);

  final Member5 member;
  @JsonKey(name: 'invite_url')
  final String inviteUrl;
  @JsonKey(name: 'invite_token')
  final String inviteToken;
  @JsonKey(name: 'invite_expires_at')
  final String inviteExpiresAt;

  Map<String, Object?> toJson() => _$Data17ToJson(this);
}
