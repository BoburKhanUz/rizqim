// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'roles3.dart';
import 'permissions3.dart';

part 'data110.g.dart';

@JsonSerializable()
class Data110 {
  const Data110({
    required this.roles,
    required this.permissions,
    required this.matrix,
    required this.defaults,
  });

  factory Data110.fromJson(Map<String, Object?> json) =>
      _$Data110FromJson(json);

  final List<Roles3> roles;
  final List<Permissions3> permissions;
  final Map<String, Map<String, bool>> matrix;
  final Map<String, Map<String, bool>> defaults;

  Map<String, Object?> toJson() => _$Data110ToJson(this);
}
