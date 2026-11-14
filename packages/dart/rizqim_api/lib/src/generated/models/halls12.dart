// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tables6.dart';

part 'halls12.g.dart';

@JsonSerializable()
class Halls12 {
  const Halls12({
    required this.id,
    required this.name,
    required this.sortOrder,
    required this.isActive,
    required this.tables,
  });

  factory Halls12.fromJson(Map<String, Object?> json) =>
      _$Halls12FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Tables6> tables;

  Map<String, Object?> toJson() => _$Halls12ToJson(this);
}
