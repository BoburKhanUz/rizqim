// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data110.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data110 _$Data110FromJson(Map<String, dynamic> json) => Data110(
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

Map<String, dynamic> _$Data110ToJson(Data110 instance) => <String, dynamic>{
  'stations_created': instance.stationsCreated,
  'stations_skipped': instance.stationsSkipped,
  'categories_created': instance.categoriesCreated,
  'dishes_created': instance.dishesCreated,
  'dishes_skipped': instance.dishesSkipped,
  'format': instance.format,
};
