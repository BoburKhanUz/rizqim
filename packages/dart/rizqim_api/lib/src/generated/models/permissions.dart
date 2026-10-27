// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'permissions.g.dart';

@JsonSerializable()
class Permissions {
  const Permissions({
    required this.key,
    required this.label,
    required this.grantable,
  });

  factory Permissions.fromJson(Map<String, Object?> json) =>
      _$PermissionsFromJson(json);

  final String key;
  final String label;
  final bool grantable;

  Map<String, Object?> toJson() => _$PermissionsToJson(this);
}
