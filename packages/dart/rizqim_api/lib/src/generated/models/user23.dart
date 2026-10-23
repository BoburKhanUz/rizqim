// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user23.g.dart';

@JsonSerializable()
class User23 {
  const User23({required this.id, required this.name});

  factory User23.fromJson(Map<String, Object?> json) => _$User23FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User23ToJson(this);
}
