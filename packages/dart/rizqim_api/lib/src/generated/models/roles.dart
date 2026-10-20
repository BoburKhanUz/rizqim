// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'roles.g.dart';

@JsonSerializable()
class Roles {
  const Roles({
    required this.role,
    required this.label,
    required this.editable,
  });

  factory Roles.fromJson(Map<String, Object?> json) => _$RolesFromJson(json);

  final String role;
  final String label;
  final bool editable;

  Map<String, Object?> toJson() => _$RolesToJson(this);
}
