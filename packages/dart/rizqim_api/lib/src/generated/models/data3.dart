// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'member.dart';

part 'data3.g.dart';

@JsonSerializable()
class Data3 {
  const Data3({
    required this.token,
    required this.expiresAt,
    required this.member,
    required this.recoveryCodes,
  });

  factory Data3.fromJson(Map<String, Object?> json) => _$Data3FromJson(json);

  final String token;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final Member member;
  @JsonKey(name: 'recovery_codes')
  final List<String> recoveryCodes;

  Map<String, Object?> toJson() => _$Data3ToJson(this);
}
