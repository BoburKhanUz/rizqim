// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user7.g.dart';

@JsonSerializable()
class User7 {
  const User7({required this.id, required this.name, required this.role});

  factory User7.fromJson(Map<String, Object?> json) => _$User7FromJson(json);

  final int id;
  final String name;
  final String role;

  Map<String, Object?> toJson() => _$User7ToJson(this);
}
