// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'halls11.g.dart';

@JsonSerializable()
class Halls11 {
  const Halls11({
    required this.id,
    required this.name,
    required this.sortOrder,
  });

  factory Halls11.fromJson(Map<String, Object?> json) =>
      _$Halls11FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$Halls11ToJson(this);
}
