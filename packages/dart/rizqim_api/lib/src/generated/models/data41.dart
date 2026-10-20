// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'roles.dart';
import 'permissions.dart';

part 'data41.g.dart';

@JsonSerializable()
class Data41 {
  const Data41({
    required this.roles,
    required this.permissions,
    required this.matrix,
    required this.defaults,
  });

  factory Data41.fromJson(Map<String, Object?> json) => _$Data41FromJson(json);

  final List<Roles> roles;
  final List<Permissions> permissions;
  final Map<String, Map<String, bool>> matrix;
  final Map<String, Map<String, bool>> defaults;

  Map<String, Object?> toJson() => _$Data41ToJson(this);
}
