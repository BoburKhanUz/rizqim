// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'halls5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Halls5 _$Halls5FromJson(Map<String, dynamic> json) => Halls5(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  isActive: json['is_active'] as bool,
  events: (json['events'] as List<dynamic>)
      .map((e) => Events2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Halls5ToJson(Halls5 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'is_active': instance.isActive,
  'events': instance.events,
};
