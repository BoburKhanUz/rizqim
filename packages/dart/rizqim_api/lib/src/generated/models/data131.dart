// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role3.dart';

part 'data131.g.dart';

@JsonSerializable()
class Data131 {
  const Data131({
    required this.role,
    required this.permission,
    required this.allowed,
  });

  factory Data131.fromJson(Map<String, Object?> json) =>
      _$Data131FromJson(json);

  final Role3 role;
  final String permission;
  final bool allowed;

  Map<String, Object?> toJson() => _$Data131ToJson(this);
}
