// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'store4.g.dart';

@JsonSerializable()
class Store4 {
  const Store4({required this.id, required this.name});

  factory Store4.fromJson(Map<String, Object?> json) => _$Store4FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Store4ToJson(this);
}
