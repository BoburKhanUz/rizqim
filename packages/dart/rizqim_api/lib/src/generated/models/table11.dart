// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'table11.g.dart';

@JsonSerializable()
class Table11 {
  const Table11({required this.id, required this.name, required this.kind});

  factory Table11.fromJson(Map<String, Object?> json) =>
      _$Table11FromJson(json);

  final int id;
  final String name;
  final String kind;

  Map<String, Object?> toJson() => _$Table11ToJson(this);
}
