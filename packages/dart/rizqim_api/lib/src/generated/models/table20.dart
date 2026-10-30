// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'table20.g.dart';

@JsonSerializable()
class Table20 {
  const Table20({required this.id, required this.name, required this.kind});

  factory Table20.fromJson(Map<String, Object?> json) =>
      _$Table20FromJson(json);

  final int id;
  final String name;
  final String kind;

  Map<String, Object?> toJson() => _$Table20ToJson(this);
}
