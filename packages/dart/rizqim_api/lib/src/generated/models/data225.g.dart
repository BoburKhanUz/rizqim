// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data225.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data225 _$Data225FromJson(Map<String, dynamic> json) => Data225(
  events: (json['events'] as List<dynamic>)
      .map((e) => Events2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data225ToJson(Data225 instance) => <String, dynamic>{
  'events': instance.events,
};
