// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bbox2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bbox2 _$Bbox2FromJson(Map<String, dynamic> json) => Bbox2(
  minLat: json['min_lat'] as String,
  maxLat: json['max_lat'] as String,
  minLng: json['min_lng'] as String,
  maxLng: json['max_lng'] as String,
);

Map<String, dynamic> _$Bbox2ToJson(Bbox2 instance) => <String, dynamic>{
  'min_lat': instance.minLat,
  'max_lat': instance.maxLat,
  'min_lng': instance.minLng,
  'max_lng': instance.maxLng,
};
