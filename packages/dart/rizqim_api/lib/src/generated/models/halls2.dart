// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tables6.dart';

part 'halls2.g.dart';

@JsonSerializable()
class Halls2 {
  const Halls2({
    required this.id,
    required this.name,
    required this.sortOrder,
    required this.isActive,
    required this.tables,
  });

  factory Halls2.fromJson(Map<String, Object?> json) => _$Halls2FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Tables6> tables;

  Map<String, Object?> toJson() => _$Halls2ToJson(this);
}
