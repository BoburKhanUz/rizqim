// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data127.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data127 _$Data127FromJson(Map<String, dynamic> json) => Data127(
  stationsCreated: (json['stations_created'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  stationsSkipped: (json['stations_skipped'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  categoriesCreated: (json['categories_created'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  dishesCreated: (json['dishes_created'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  dishesSkipped: (json['dishes_skipped'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  format: Format.fromJson(json['format'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data127ToJson(Data127 instance) => <String, dynamic>{
  'stations_created': instance.stationsCreated,
  'stations_skipped': instance.stationsSkipped,
  'categories_created': instance.categoriesCreated,
  'dishes_created': instance.dishesCreated,
  'dishes_skipped': instance.dishesSkipped,
  'format': instance.format,
};
