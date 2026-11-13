// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user26.g.dart';

@JsonSerializable()
class User26 {
  const User26({required this.id, required this.name});

  factory User26.fromJson(Map<String, Object?> json) => _$User26FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User26ToJson(this);
}
