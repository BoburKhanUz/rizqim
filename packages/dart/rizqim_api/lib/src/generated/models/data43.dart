// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data43.g.dart';

@JsonSerializable()
class Data43 {
  const Data43({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory Data43.fromJson(Map<String, Object?> json) => _$Data43FromJson(json);

  final String role;
  final String permission;
  final bool allowed;

  Map<String, Object?> toJson() => _$Data43ToJson(this);
}
