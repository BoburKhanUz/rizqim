// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'roles.dart';
import 'permissions.dart';

part 'data62.g.dart';

@JsonSerializable()
class Data62 {
  const Data62({
    required this.roles,
    required this.permissions,
    required this.matrix,
    required this.defaults,
  });

  factory Data62.fromJson(Map<String, Object?> json) => _$Data62FromJson(json);

  final List<Roles> roles;
  final List<Permissions> permissions;
  final Map<String, Map<String, bool>> matrix;
  final Map<String, Map<String, bool>> defaults;

  Map<String, Object?> toJson() => _$Data62ToJson(this);
}
