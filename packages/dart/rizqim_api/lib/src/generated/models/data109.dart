// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role3.dart';

part 'data109.g.dart';

@JsonSerializable()
class Data109 {
  const Data109({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory Data109.fromJson(Map<String, Object?> json) =>
      _$Data109FromJson(json);

  final Role3 role;
  final String permission;
  final bool allowed;

  Map<String, Object?> toJson() => _$Data109ToJson(this);
}
