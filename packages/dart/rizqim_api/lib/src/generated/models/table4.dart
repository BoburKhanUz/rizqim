// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'table4.g.dart';

@JsonSerializable()
class Table4 {
  const Table4({required this.id, required this.name, required this.kind});

  factory Table4.fromJson(Map<String, Object?> json) => _$Table4FromJson(json);

  final int id;
  final String name;
  final String kind;

  Map<String, Object?> toJson() => _$Table4ToJson(this);
}
