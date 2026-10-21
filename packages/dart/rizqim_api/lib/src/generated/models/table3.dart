// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'table3.g.dart';

@JsonSerializable()
class Table3 {
  const Table3({required this.id, required this.name, required this.kind});

  factory Table3.fromJson(Map<String, Object?> json) => _$Table3FromJson(json);

  final int id;
  final String name;
  final String kind;

  Map<String, Object?> toJson() => _$Table3ToJson(this);
}
