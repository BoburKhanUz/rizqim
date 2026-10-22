// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'user16.g.dart';

@JsonSerializable()
class User16 {
  const User16({required this.id, required this.name});

  factory User16.fromJson(Map<String, Object?> json) => _$User16FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$User16ToJson(this);
}
