// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data118.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data118 _$Data118FromJson(Map<String, dynamic> json) => Data118(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data118ToJson(Data118 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
