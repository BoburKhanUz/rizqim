// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'polygon.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Polygon _$PolygonFromJson(Map<String, dynamic> json) => Polygon(
  type: json['type'] as String,
  coordinates: json['coordinates'] as List<dynamic>,
);

Map<String, dynamic> _$PolygonToJson(Polygon instance) => <String, dynamic>{
  'type': instance.type,
  'coordinates': instance.coordinates,
};
