// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role3.dart';

part 'data124.g.dart';

@JsonSerializable()
class Data124 {
  const Data124({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory Data124.fromJson(Map<String, Object?> json) =>
      _$Data124FromJson(json);

  final Role3 role;
  final String permission;
  final bool allowed;

  Map<String, Object?> toJson() => _$Data124ToJson(this);
}
