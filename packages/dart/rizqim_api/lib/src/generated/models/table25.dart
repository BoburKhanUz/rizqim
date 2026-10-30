// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'table25.g.dart';

@JsonSerializable()
class Table25 {
  const Table25({required this.id, required this.name, required this.kind});

  factory Table25.fromJson(Map<String, Object?> json) =>
      _$Table25FromJson(json);

  final int id;
  final String name;
  final String kind;

  Map<String, Object?> toJson() => _$Table25ToJson(this);
}
