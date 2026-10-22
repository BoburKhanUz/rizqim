// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'categories.g.dart';

@JsonSerializable()
class Categories {
  const Categories({required this.categoryId, required this.stationId});

  factory Categories.fromJson(Map<String, Object?> json) =>
      _$CategoriesFromJson(json);

  @JsonKey(name: 'category_id')
  final int categoryId;
  @JsonKey(name: 'station_id')
  final int stationId;

  Map<String, Object?> toJson() => _$CategoriesToJson(this);
}
