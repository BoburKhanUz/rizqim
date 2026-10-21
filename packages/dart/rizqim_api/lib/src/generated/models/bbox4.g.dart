// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bbox4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bbox4 _$Bbox4FromJson(Map<String, dynamic> json) => Bbox4(
  minLat: json['min_lat'] as String,
  maxLat: json['max_lat'] as String,
  minLng: json['min_lng'] as String,
  maxLng: json['max_lng'] as String,
);

Map<String, dynamic> _$Bbox4ToJson(Bbox4 instance) => <String, dynamic>{
  'min_lat': instance.minLat,
  'max_lat': instance.maxLat,
  'min_lng': instance.minLng,
  'max_lng': instance.maxLng,
};
