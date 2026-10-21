// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'station_routes.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StationRoutes _$StationRoutesFromJson(Map<String, dynamic> json) =>
    StationRoutes(
      categories: (json['categories'] as List<dynamic>)
          .map((e) => Categories.fromJson(e as Map<String, dynamic>))
          .toList(),
      products: (json['products'] as List<dynamic>)
          .map((e) => Products2.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$StationRoutesToJson(StationRoutes instance) =>
    <String, dynamic>{
      'categories': instance.categories,
      'products': instance.products,
    };
