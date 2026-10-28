// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role.dart';

part 'data91.g.dart';

@JsonSerializable()
class Data91 {
  const Data91({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory Data91.fromJson(Map<String, Object?> json) => _$Data91FromJson(json);

  final Role role;
  final String permission;
  final bool allowed;

  Map<String, Object?> toJson() => _$Data91ToJson(this);
}
