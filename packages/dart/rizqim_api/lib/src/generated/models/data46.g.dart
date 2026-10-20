// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data46.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data46 _$Data46FromJson(Map<String, dynamic> json) => Data46(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data46ToJson(Data46 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
