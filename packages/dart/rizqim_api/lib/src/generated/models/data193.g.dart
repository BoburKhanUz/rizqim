// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data193.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data193 _$Data193FromJson(Map<String, dynamic> json) => Data193(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data193ToJson(Data193 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
