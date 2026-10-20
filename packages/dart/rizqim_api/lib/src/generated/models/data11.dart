// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user7.dart';

part 'data11.g.dart';

@JsonSerializable()
class Data11 {
  const Data11({
    required this.token,
    required this.expiresAt,
    required this.user,
    required this.permissions,
  });

  factory Data11.fromJson(Map<String, Object?> json) => _$Data11FromJson(json);

  final String token;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final User7 user;
  final List<String> permissions;

  Map<String, Object?> toJson() => _$Data11ToJson(this);
}
