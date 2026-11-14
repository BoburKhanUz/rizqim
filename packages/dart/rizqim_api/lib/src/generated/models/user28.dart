// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user28.g.dart';

@JsonSerializable()
class User28 {
  const User28({required this.id, required this.name});

  factory User28.fromJson(Map<String, Object?> json) => _$User28FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User28ToJson(this);
}
