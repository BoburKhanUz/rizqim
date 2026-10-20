// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user9.g.dart';

@JsonSerializable()
class User9 {
  const User9({required this.id, required this.name, required this.role});

  factory User9.fromJson(Map<String, Object?> json) => _$User9FromJson(json);

  final int id;
  final String name;
  final String role;

  Map<String, Object?> toJson() => _$User9ToJson(this);
}
