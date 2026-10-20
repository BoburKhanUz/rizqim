// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'preferences2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Preferences2 _$Preferences2FromJson(Map<String, dynamic> json) => Preferences2(
  type: json['type'] as String,
  label: json['label'] as String,
  description: json['description'] as String,
  channels: (json['channels'] as List<dynamic>)
      .map((e) => Channels2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Preferences2ToJson(Preferences2 instance) =>
    <String, dynamic>{
      'type': instance.type,
      'label': instance.label,
      'description': instance.description,
      'channels': instance.channels,
    };
