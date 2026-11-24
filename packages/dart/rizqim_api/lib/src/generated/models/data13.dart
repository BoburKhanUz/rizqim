// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user8.dart';

part 'data13.g.dart';

@JsonSerializable()
class Data13 {
  const Data13({
    required this.token,
    required this.expiresAt,
    required this.user,
    required this.permissions,
  });

  factory Data13.fromJson(Map<String, Object?> json) => _$Data13FromJson(json);

  final String token;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final User8 user;
  final List<String> permissions;

  Map<String, Object?> toJson() => _$Data13ToJson(this);
}
