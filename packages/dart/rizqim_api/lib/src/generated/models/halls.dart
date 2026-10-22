// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tables5.dart';

part 'halls.g.dart';

@JsonSerializable()
class Halls {
  const Halls({
    required this.id,
    required this.name,
    required this.sortOrder,
    required this.isActive,
    required this.tables,
  });

  factory Halls.fromJson(Map<String, Object?> json) => _$HallsFromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Tables5> tables;

  Map<String, Object?> toJson() => _$HallsToJson(this);
}
