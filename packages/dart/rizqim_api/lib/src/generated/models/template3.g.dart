// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'template3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Template3 _$Template3FromJson(Map<String, dynamic> json) => Template3(
  profile: json['profile'] as String,
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations6.fromJson(e as Map<String, dynamic>))
      .toList(),
  dishes: (json['dishes'] as List<dynamic>)
      .map((e) => Dishes3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Template3ToJson(Template3 instance) => <String, dynamic>{
  'profile': instance.profile,
  'stations': instance.stations,
  'dishes': instance.dishes,
};
