// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'categories.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Categories _$CategoriesFromJson(Map<String, dynamic> json) => Categories(
  categoryId: (json['category_id'] as num).toInt(),
  stationId: (json['station_id'] as num).toInt(),
);

Map<String, dynamic> _$CategoriesToJson(Categories instance) =>
    <String, dynamic>{
      'category_id': instance.categoryId,
      'station_id': instance.stationId,
    };
