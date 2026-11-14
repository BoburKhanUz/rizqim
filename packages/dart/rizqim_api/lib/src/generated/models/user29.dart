// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user29.g.dart';

@JsonSerializable()
class User29 {
  const User29({required this.id, required this.name});

  factory User29.fromJson(Map<String, Object?> json) => _$User29FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User29ToJson(this);
}
