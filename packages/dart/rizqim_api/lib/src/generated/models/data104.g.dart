// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data104.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data104 _$Data104FromJson(Map<String, dynamic> json) => Data104(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data104ToJson(Data104 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
