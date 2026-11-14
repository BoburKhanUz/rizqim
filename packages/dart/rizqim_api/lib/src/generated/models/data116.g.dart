// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data116.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data116 _$Data116FromJson(Map<String, dynamic> json) => Data116(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data116ToJson(Data116 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
