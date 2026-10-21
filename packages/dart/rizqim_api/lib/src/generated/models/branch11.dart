// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'branch11.g.dart';

@JsonSerializable()
class Branch11 {
  const Branch11({required this.id, required this.name});

  factory Branch11.fromJson(Map<String, Object?> json) =>
      _$Branch11FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Branch11ToJson(this);
}
