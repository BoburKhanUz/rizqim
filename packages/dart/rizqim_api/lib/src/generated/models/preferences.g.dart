// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'preferences.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Preferences _$PreferencesFromJson(Map<String, dynamic> json) => Preferences(
  type: json['type'] as String,
  label: json['label'] as String,
  description: json['description'] as String,
  channels: (json['channels'] as List<dynamic>)
      .map((e) => Channels.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$PreferencesToJson(Preferences instance) =>
    <String, dynamic>{
      'type': instance.type,
      'label': instance.label,
      'description': instance.description,
      'channels': instance.channels,
    };
