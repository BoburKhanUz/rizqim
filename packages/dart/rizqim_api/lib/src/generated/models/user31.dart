// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user31.g.dart';

@JsonSerializable()
class User31 {
  const User31({required this.id, required this.name});

  factory User31.fromJson(Map<String, Object?> json) => _$User31FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User31ToJson(this);
}
