// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data90.g.dart';

@JsonSerializable()
class Data90 {
  const Data90({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory Data90.fromJson(Map<String, Object?> json) => _$Data90FromJson(json);

  final String role;
  final String permission;
  final bool allowed;

  Map<String, Object?> toJson() => _$Data90ToJson(this);
}
