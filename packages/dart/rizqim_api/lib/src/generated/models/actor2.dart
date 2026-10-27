// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'actor2.g.dart';

@JsonSerializable()
class Actor2 {
  const Actor2({required this.id, required this.name, required this.role});

  factory Actor2.fromJson(Map<String, Object?> json) => _$Actor2FromJson(json);

  final int id;
  final String name;
  final String role;

  Map<String, Object?> toJson() => _$Actor2ToJson(this);
}
