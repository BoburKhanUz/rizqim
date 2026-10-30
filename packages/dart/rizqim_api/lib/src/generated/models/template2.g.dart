// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'template2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Template2 _$Template2FromJson(Map<String, dynamic> json) => Template2(
  profile: json['profile'] as String,
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations5.fromJson(e as Map<String, dynamic>))
      .toList(),
  dishes: (json['dishes'] as List<dynamic>)
      .map((e) => Dishes2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Template2ToJson(Template2 instance) => <String, dynamic>{
  'profile': instance.profile,
  'stations': instance.stations,
  'dishes': instance.dishes,
};
