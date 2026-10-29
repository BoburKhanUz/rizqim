// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'products3.g.dart';

@JsonSerializable()
class Products3 {
  const Products3({
    required this.id,
    required this.name,
    required this.categoryId,
    required this.categoryName,
    required this.dailyRotation,
    required this.onMenu,
    required this.isSet,
  });

  factory Products3.fromJson(Map<String, Object?> json) =>
      _$Products3FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'category_id')
  final int categoryId;
  @JsonKey(name: 'category_name')
  final String categoryName;
  @JsonKey(name: 'daily_rotation')
  final String dailyRotation;
  @JsonKey(name: 'on_menu')
  final String onMenu;
  @JsonKey(name: 'is_set')
  final bool isSet;

  Map<String, Object?> toJson() => _$Products3ToJson(this);
}
