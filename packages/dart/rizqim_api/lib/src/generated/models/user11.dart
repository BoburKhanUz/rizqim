// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user11.g.dart';

@JsonSerializable()
class User11 {
  const User11({required this.id, required this.name, required this.role});

  factory User11.fromJson(Map<String, Object?> json) => _$User11FromJson(json);

  final int id;
  final String name;
  final String role;

  Map<String, Object?> toJson() => _$User11ToJson(this);
}
