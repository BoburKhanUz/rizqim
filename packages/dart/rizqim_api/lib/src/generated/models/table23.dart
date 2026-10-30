// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'table23.g.dart';

@JsonSerializable()
class Table23 {
  const Table23({required this.id, required this.name, required this.kind});

  factory Table23.fromJson(Map<String, Object?> json) =>
      _$Table23FromJson(json);

  final int id;
  final String name;
  final String kind;

  Map<String, Object?> toJson() => _$Table23ToJson(this);
}
