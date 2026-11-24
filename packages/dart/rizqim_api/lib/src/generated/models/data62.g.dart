// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data62.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data62 _$Data62FromJson(Map<String, dynamic> json) => Data62(
  rates: (json['rates'] as List<dynamic>)
      .map((e) => Rates2.fromJson(e as Map<String, dynamic>))
      .toList(),
  channels: json['channels'] as List<dynamic>,
);

Map<String, dynamic> _$Data62ToJson(Data62 instance) => <String, dynamic>{
  'rates': instance.rates,
  'channels': instance.channels,
};
