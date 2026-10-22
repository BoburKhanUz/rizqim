// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user24.g.dart';

@JsonSerializable()
class User24 {
  const User24({required this.id, required this.name});

  factory User24.fromJson(Map<String, Object?> json) => _$User24FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User24ToJson(this);
}
