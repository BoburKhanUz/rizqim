// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data63.g.dart';

@JsonSerializable()
class Data63 {
  const Data63({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory Data63.fromJson(Map<String, Object?> json) => _$Data63FromJson(json);

  final String role;
  final String permission;
  final bool allowed;

  Map<String, Object?> toJson() => _$Data63ToJson(this);
}
