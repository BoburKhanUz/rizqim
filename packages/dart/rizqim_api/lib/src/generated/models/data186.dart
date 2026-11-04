// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'approved_by.dart';

part 'data186.g.dart';

@JsonSerializable()
class Data186 {
  const Data186({
    required this.token,
    required this.permission,
    required this.expiresAt,
    required this.approvedBy,
  });

  factory Data186.fromJson(Map<String, Object?> json) =>
      _$Data186FromJson(json);

  final String token;
  final String permission;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  @JsonKey(name: 'approved_by')
  final ApprovedBy approvedBy;

  Map<String, Object?> toJson() => _$Data186ToJson(this);
}
