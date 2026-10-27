// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'roles2.dart';
import 'permissions2.dart';

part 'data14.g.dart';

@JsonSerializable()
class Data14 {
  const Data14({
    required this.roles,
    required this.permissions,
    required this.grants,
    required this.defaults,
  });

  factory Data14.fromJson(Map<String, Object?> json) => _$Data14FromJson(json);

  final List<Roles2> roles;
  final List<Permissions2> permissions;
  final Map<String, List<String>> grants;
  final Map<String, List<String>> defaults;

  Map<String, Object?> toJson() => _$Data14ToJson(this);
}
