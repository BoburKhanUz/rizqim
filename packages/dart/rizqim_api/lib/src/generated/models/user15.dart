// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user15.g.dart';

@JsonSerializable()
class User15 {
  const User15({required this.id, required this.name});

  factory User15.fromJson(Map<String, Object?> json) => _$User15FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User15ToJson(this);
}
