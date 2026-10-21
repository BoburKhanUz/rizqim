// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'layout5.dart';

part 'tables.g.dart';

@JsonSerializable()
class Tables {
  const Tables({
    required this.id,
    required this.layout,
    this.hallId,
    this.sortOrder,
  });

  factory Tables.fromJson(Map<String, Object?> json) => _$TablesFromJson(json);

  final int id;
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;
  final Layout5 layout;

  Map<String, Object?> toJson() => _$TablesToJson(this);
}
