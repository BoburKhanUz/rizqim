// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bbox.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bbox _$BboxFromJson(Map<String, dynamic> json) => Bbox(
  minLat: json['min_lat'] as String,
  maxLat: json['max_lat'] as String,
  minLng: json['min_lng'] as String,
  maxLng: json['max_lng'] as String,
);

Map<String, dynamic> _$BboxToJson(Bbox instance) => <String, dynamic>{
  'min_lat': instance.minLat,
  'max_lat': instance.maxLat,
  'min_lng': instance.minLng,
  'max_lng': instance.maxLng,
};
