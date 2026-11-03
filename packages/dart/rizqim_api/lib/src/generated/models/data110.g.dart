// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data110.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data110 _$Data110FromJson(Map<String, dynamic> json) => Data110(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data110ToJson(Data110 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
